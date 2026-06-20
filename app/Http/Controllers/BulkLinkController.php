<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Billing\PlanGate;
use App\Services\Linking\BulkLinkCreator;
use Illuminate\Http\Request;

/**
 * Bulk shortening (paste a list of URLs) and CSV/Bitly import. Both funnel into
 * BulkLinkCreator, which enforces the plan quota and safety screening. A summary
 * of what was created / skipped is flashed back to the page.
 */
class BulkLinkController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        return view('links.bulk', [
            'campaigns' => $user->campaigns()->orderBy('name')->get(),
            'remaining' => app(PlanGate::class)->remaining($user, 'max_links'),
            'maxRows' => BulkLinkCreator::MAX_ROWS,
        ]);
    }

    public function store(Request $request, BulkLinkCreator $creator)
    {
        $data = $request->validate([
            'urls' => ['required', 'string', 'max:100000'],
            'campaign_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'string', 'max:300'],
        ]);

        $rows = collect(preg_split('/\r\n|\r|\n/', $data['urls']))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->map(fn ($l) => ['long_url' => $l])
            ->all();

        $summary = $creator->create(
            $request->user(),
            $rows,
            $this->resolveCampaignId($request),
            Link::normalizeTags($data['tags'] ?? null),
        );

        return back()->with('bulk', $summary);
    }

    public function import(Request $request, BulkLinkCreator $creator)
    {
        // Validate as a plain file (CSV mime detection is unreliable across hosts);
        // the parser is content-based, so a non-CSV simply yields no rows.
        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
            'campaign_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'string', 'max:300'],
        ]);

        $rows = $this->parseCsv((string) file_get_contents($request->file('file')->getRealPath()));

        $summary = $creator->create(
            $request->user(),
            $rows,
            $this->resolveCampaignId($request),
            Link::normalizeTags($request->input('tags')),
        );

        return back()->with('bulk', $summary);
    }

    private function resolveCampaignId(Request $request): ?int
    {
        $id = $request->integer('campaign_id') ?: null;
        if ($id && ! $request->user()->campaigns()->whereKey($id)->exists()) {
            return null;
        }

        return $id;
    }

    /**
     * Parse a CSV/TXT export into link rows. Detects a header row and maps common
     * column names (including Bitly's "Bitlink" / "Long URL" / "Title" / "Tags");
     * falls back to positional columns [long_url, alias, title, tags] when there
     * is no header.
     *
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // strip UTF-8 BOM
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $records[] = str_getcsv($line);
        }
        if (! $records) {
            return [];
        }

        $map = $this->detectHeader($records[0]);
        if ($map !== null) {
            array_shift($records);
        } else {
            $map = ['long_url' => 0, 'alias' => 1, 'title' => 2, 'tags' => 3];
        }

        $rows = [];
        foreach ($records as $r) {
            $get = fn (string $f) => isset($map[$f], $r[$map[$f]]) ? trim((string) $r[$map[$f]]) : '';
            if (($long = $get('long_url')) === '') {
                continue;
            }
            $rows[] = [
                'long_url' => $long,
                'alias' => $this->aliasFromValue($get('alias')),
                'title' => $get('title'),
                'tags' => $get('tags'),
            ];
        }

        return $rows;
    }

    /**
     * Map a header row to field => column index, or null when the row looks like
     * data (i.e. it has no header).
     *
     * @param  array<int, string>  $row
     * @return array<string, int>|null
     */
    private function detectHeader(array $row): ?array
    {
        $known = [
            'long_url' => ['long url', 'long_url', 'longurl', 'destination', 'url', 'original url', 'original_url', 'link', 'target'],
            'alias' => ['alias', 'keyword', 'custom', 'custom back-half', 'back-half', 'backhalf', 'short', 'short url', 'bitlink', 'slug', 'code'],
            'title' => ['title', 'name'],
            'tags' => ['tags', 'tag', 'label', 'labels'],
        ];

        $map = [];
        foreach ($row as $i => $cell) {
            $c = strtolower(trim((string) $cell));
            if (str_contains($c, '://')) {
                return null; // a URL in row 1 means there is no header
            }
            foreach ($known as $field => $names) {
                if (! isset($map[$field]) && in_array($c, $names, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return isset($map['long_url']) ? $map : null;
    }

    /** Reduce a full short URL (e.g. Bitly "Bitlink") to its back-half. */
    private function aliasFromValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! str_contains($value, '/')) {
            return $value;
        }

        $path = trim((string) (parse_url($value, PHP_URL_PATH) ?: $value), '/');

        return ($pos = strrpos($path, '/')) !== false ? substr($path, $pos + 1) : $path;
    }
}
