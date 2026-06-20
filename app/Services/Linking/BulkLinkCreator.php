<?php

namespace App\Services\Linking;

use App\Jobs\ScanLink;
use App\Models\Link;
use App\Models\User;
use App\Services\Billing\PlanGate;
use App\Services\Safety\LinkSafety;

/**
 * Creates many links in one pass for bulk-shorten and CSV/Bitly import. Each
 * input row is ['long_url' => ..., 'alias' => ?, 'title' => ?, 'tags' => ?].
 * Respects the user's plan link quota, screens for safety, de-dupes within the
 * batch, and falls back to a generated alias when a requested one is taken.
 */
class BulkLinkCreator
{
    /** Hard ceiling on rows processed per request (shared-hosting safety). */
    public const MAX_ROWS = 1000;

    public function __construct(
        private AliasGenerator $aliases,
        private DomainResolver $domains,
        private LinkSafety $safety,
        private PlanGate $plans,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>|null  $batchTags  applied to every created link
     * @return array<string, mixed>
     */
    public function create(User $user, array $rows, ?int $campaignId = null, ?array $batchTags = null): array
    {
        $summary = [
            'created' => 0,
            'renamed' => 0,
            'links' => [],
            'skipped' => ['invalid' => 0, 'duplicate' => 0, 'unsafe' => 0, 'limit' => 0],
            'truncated' => false,
        ];

        $domain = $this->domains->default();
        if (! $domain) {
            return $summary;
        }

        if (count($rows) > self::MAX_ROWS) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
            $summary['truncated'] = true;
        }

        $remaining = $this->plans->remaining($user, 'max_links'); // null = unlimited
        $seen = [];

        foreach ($rows as $row) {
            $url = trim((string) ($row['long_url'] ?? ''));
            if ($url === '' || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
                $summary['skipped']['invalid']++;

                continue;
            }

            $requestedAlias = trim((string) ($row['alias'] ?? ''));
            $key = $requestedAlias !== '' ? 'a:'.strtolower($requestedAlias) : 'u:'.$url;
            if (isset($seen[$key])) {
                $summary['skipped']['duplicate']++;

                continue;
            }
            $seen[$key] = true;

            if ($remaining !== null && $summary['created'] >= $remaining) {
                $summary['skipped']['limit']++;

                continue;
            }

            if ($this->safety->screen($url)) {
                $summary['skipped']['unsafe']++;

                continue;
            }

            // Use the requested alias when it is valid and free, otherwise generate one.
            $alias = $requestedAlias;
            if ($alias === '' || $this->aliases->validateCustom($alias, $domain->id) !== null) {
                if ($alias !== '') {
                    $summary['renamed']++;
                }
                $alias = $this->aliases->generate($domain->id);
            }

            $tags = Link::normalizeTags(array_merge(
                Link::normalizeTags($row['tags'] ?? null) ?? [],
                $batchTags ?? [],
            ));

            $link = $user->links()->create([
                'domain_id' => $domain->id,
                'campaign_id' => $campaignId,
                'alias' => $alias,
                'long_url' => $url,
                'title' => ($t = trim((string) ($row['title'] ?? ''))) !== '' ? $t : null,
                'tags' => $tags,
                'type' => 'direct',
                'safety_status' => 'pending',
            ]);
            Link::forgetCache($domain->id, $alias);
            ScanLink::dispatch($link->id); // async; the link is usable immediately as "pending"

            $summary['created']++;
            $summary['links'][] = $link;
        }

        return $summary;
    }
}
