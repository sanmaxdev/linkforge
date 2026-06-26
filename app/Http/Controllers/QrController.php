<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\User;
use App\Services\Billing\PlanGate;
use App\Services\Linking\AliasGenerator;
use App\Services\Linking\DomainResolver;
use App\Services\Qr\QrService;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function __construct(private QrService $qr) {}

    public function index(Request $request)
    {
        $codes = $request->user()->qrCodes()->with('link')->latest()->paginate(12);

        return view('qr.index', compact('codes'));
    }

    public function create(Request $request)
    {
        if (! app(PlanGate::class)->canCreate($request->user(), 'max_qr')) {
            return redirect()->route('qr.index')->with('error', "You've reached your plan's QR code limit. Upgrade to create more.");
        }

        return view('qr.builder', [
            'qr' => null,
            'action' => route('qr.store'),
            'method' => 'POST',
            'templates' => $request->user()->qrTemplates()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (! app(PlanGate::class)->canCreate($request->user(), 'max_qr')) {
            return back()->withInput()->with('error', "You've reached your plan's QR code limit. Upgrade to create more.");
        }

        $qr = new QrCode(['user_id' => $request->user()->id]);
        $qr->user_id = $request->user()->id;
        $this->fill($qr, $this->validateQr($request), $request->user());
        $qr->save();

        return redirect()->route('qr.index')->with('status', 'QR code saved.');
    }

    public function edit(Request $request, QrCode $qr)
    {
        abort_unless((int) $qr->user_id === (int) $request->user()->id, 403);

        return view('qr.builder', [
            'qr' => $qr,
            'action' => route('qr.update', $qr),
            'method' => 'PUT',
            'templates' => $request->user()->qrTemplates()->latest()->get(),
        ]);
    }

    public function update(Request $request, QrCode $qr)
    {
        abort_unless((int) $qr->user_id === (int) $request->user()->id, 403);

        $this->fill($qr, $this->validateQr($request), $request->user());
        $qr->save();

        return redirect()->route('qr.index')->with('status', 'QR code updated.');
    }

    public function destroy(Request $request, QrCode $qr)
    {
        abort_unless((int) $qr->user_id === (int) $request->user()->id, 403);
        $qr->delete();

        return redirect()->route('qr.index')->with('status', 'QR code deleted.');
    }

    // Per-link QR (legacy quick action from the links table) ----------------

    public function show(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);
        $link->load('domain');

        // Open the full builder bound to this link (its existing design, if any).
        $qr = $request->user()->qrCodes()->where('link_id', $link->id)->latest()->first();

        return view('qr.builder', [
            'qr' => $qr,
            'boundLink' => $link,
            'action' => $qr ? route('qr.update', $qr) : route('qr.store'),
            'method' => $qr ? 'PUT' : 'POST',
            'templates' => $request->user()->qrTemplates()->latest()->get(),
        ]);
    }

    // Design templates ------------------------------------------------------

    public function storeTemplate(Request $request)
    {
        $attrs = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'design' => ['nullable', 'string', 'max:3000000'],
        ]);

        $template = $request->user()->qrTemplates()->create([
            'name' => $attrs['name'],
            'design' => json_decode($attrs['design'] ?? '[]', true) ?: [],
        ]);

        return response()->json(['id' => $template->id, 'name' => $template->name, 'design' => $template->design]);
    }

    public function destroyTemplate(Request $request, QrTemplate $template)
    {
        abort_unless((int) $template->user_id === (int) $request->user()->id, 403);
        $template->delete();

        return response()->json(['ok' => true]);
    }

    // Bulk generation -------------------------------------------------------

    public function bulkForm(Request $request)
    {
        return view('qr.bulk', [
            'templates' => $request->user()->qrTemplates()->latest()->get(),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $attrs = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
            'template_id' => ['nullable', 'integer'],
        ]);

        $design = [];
        if (! empty($attrs['template_id'])) {
            $design = $request->user()->qrTemplates()->find($attrs['template_id'])?->design ?? [];
        }

        $gate = app(PlanGate::class);
        $rows = array_map('str_getcsv', file($request->file('csv')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $created = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            $url = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            // Skip a header row and anything that isn't a URL.
            if ($i === 0 && ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $skipped++;

                continue;
            }
            if (! $gate->canCreate($request->user(), 'max_qr')) {
                break;
            }

            $qr = new QrCode(['user_id' => $request->user()->id]);
            $qr->user_id = $request->user()->id;
            $this->fill($qr, [
                'name' => $name ?: parse_url($url, PHP_URL_HOST),
                'type' => 'link',
                'is_dynamic' => '1',
                'content' => $url,
                'data' => json_encode(['url' => $url]),
                'design' => json_encode($design),
            ], $request->user());
            $qr->save();
            $created++;
        }

        return redirect()->route('qr.index')->with('status', "Generated {$created} QR code(s)".($skipped ? ", skipped {$skipped} invalid row(s)" : '').'.');
    }

    public function render(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);
        $link->load('domain');

        $url = $request->getScheme().'://'.$link->shortUrl();
        $design = $request->only(['fg', 'bg', 'size', 'margin', 'format']);
        $out = $this->qr->render($url, $design);

        $headers = ['Content-Type' => $out['mime'], 'Cache-Control' => 'no-store'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="qr-'.$link->alias.'.'.$out['format'].'"';
        }

        return response($out['data'], 200, $headers);
    }

    // ----------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validateQr(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:30'],
            'is_dynamic' => ['nullable', 'boolean'],
            'content' => ['nullable', 'string', 'max:4096'],
            'data' => ['nullable', 'string', 'max:8192'],
            'design' => ['nullable', 'string', 'max:3000000'], // allows an embedded logo data-URL
            'bound_link_id' => ['nullable', 'integer'],
        ]);
    }

    /** Apply the submitted builder state, creating/refreshing a tracked link for dynamic codes. */
    private function fill(QrCode $qr, array $attrs, User $user): void
    {
        $data = json_decode($attrs['data'] ?? '[]', true) ?: [];
        $design = json_decode($attrs['design'] ?? '[]', true) ?: [];
        $type = $attrs['type'];
        $content = (string) ($attrs['content'] ?? '');
        $isDynamic = false;

        $boundId = $attrs['bound_link_id'] ?? null;

        if ($boundId && $link = $user->links()->find($boundId)) {
            // QR bound to an existing tracked link (the per-link QR action).
            $link->load('domain');
            $type = 'link';
            $isDynamic = true;
            $qr->link_id = $link->id;
            $content = request()->getScheme().'://'.$link->shortUrl();
            $data['url'] = $content;
            $design['_short'] = $content;
        } elseif ($type === 'link' && ! empty($attrs['is_dynamic'])) {
            // Dynamic code: mint a fresh tracked link.
            $link = $this->resolveDynamicLink($qr, $user, $data['url'] ?? $content, $attrs['name'] ?? null);
            $isDynamic = true;
            $qr->link_id = $link->id;
            $content = request()->getScheme().'://'.$link->shortUrl();
            $design['_short'] = $content; // so the saved thumbnail encodes the short link
        } else {
            $qr->link_id = null;
        }

        $qr->fill([
            'name' => $attrs['name'] ?? null,
            'type' => $type,
            'is_dynamic' => $isDynamic,
            'content' => $content,
            'data' => $data,
            'design' => $design,
            'format' => 'png',
        ]);
    }

    private function resolveDynamicLink(QrCode $qr, User $user, ?string $url, ?string $name): Link
    {
        $domain = app(DomainResolver::class)->default();
        abort_unless($domain, 500, 'No default domain configured.');

        $url = $url ?: 'https://example.com';

        if ($qr->link_id && $link = Link::find($qr->link_id)) {
            $link->update(['long_url' => $url, 'title' => $name]);
            Link::forgetCache($link->domain_id, $link->alias);

            return $link;
        }

        $alias = app(AliasGenerator::class)->generate($domain->id);
        $link = $user->links()->create([
            'domain_id' => $domain->id,
            'alias' => $alias,
            'long_url' => $url,
            'title' => $name,
            'type' => 'direct',
            'safety_status' => 'safe',
        ]);
        $link->setRelation('domain', $domain);

        return $link;
    }
}
