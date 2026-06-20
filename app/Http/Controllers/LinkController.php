<?php

namespace App\Http\Controllers;

use App\Jobs\ScanLink;
use App\Models\Link;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Ai\ClaudeClient;
use App\Services\Billing\PlanGate;
use App\Services\Linking\AliasGenerator;
use App\Services\Linking\DomainResolver;
use App\Services\Safety\LinkSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LinkController extends Controller
{
    public function __construct(
        private AliasGenerator $aliases,
        private DomainResolver $domains,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $campaignId = $request->integer('campaign') ?: null;
        $tag = strtolower(preg_replace('/[^a-z0-9\- ]/i', '', (string) $request->query('tag', '')));

        $links = $user->links()
            ->with(['domain', 'campaign'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('alias', 'like', "%{$q}%")
                        ->orWhere('long_url', 'like', "%{$q}%")
                        ->orWhere('title', 'like', "%{$q}%");
                });
            })
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->when($tag !== '', fn ($query) => $query->where('tags', 'like', '%"'.$tag.'"%'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('links.index', [
            'links' => $links,
            'q' => $q,
            'domain' => $this->domains->default(),
            'campaigns' => $user->campaigns()->orderBy('name')->get(),
            'campaignId' => $campaignId,
            'tag' => $tag,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $domains = $this->selectableDomains($user);
        $domain = $domains->first();

        return view('links.create', [
            'domain' => $domain,
            'domains' => $domains,
            'suggestion' => $domain ? $this->aliases->generate($domain->id) : '',
            'pixels' => $user->pixels()->get(),
            'attachedPixelIds' => [],
            'campaigns' => $user->campaigns()->orderBy('name')->get(),
            'canDeepLink' => app(PlanGate::class)->allows($user, 'deep_links'),
            'aiEnabled' => app(ClaudeClient::class)->enabled(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $domain = $this->selectableDomains($user)->firstWhere('id', (int) $request->input('domain_id'))
            ?: $this->domains->default();
        abort_unless($domain, 500, 'No default domain configured.');

        if (! app(PlanGate::class)->canCreate($user, 'max_links')) {
            return back()->withInput()->with('error', "You've reached your plan's link limit. Upgrade to create more.");
        }

        $data = $this->validateLink($request);

        $alias = trim((string) ($data['alias'] ?? ''));
        if ($alias !== '') {
            if ($error = $this->aliases->validateCustom($alias, $domain->id)) {
                return back()->withInput()->withErrors(['alias' => $error]);
            }
        } else {
            $alias = $this->aliases->generate($domain->id);
        }

        $link = $user->links()->create([
            'domain_id' => $domain->id,
            'campaign_id' => $this->resolveCampaignId($request),
            'alias' => $alias,
            'long_url' => $data['long_url'],
            'params' => $this->buildParams($data),
            'title' => $data['title'] ?? null,
            'tags' => $this->parseTags($data['tags'] ?? null),
            'meta' => $this->buildMeta($request, $user, null),
            'type' => $data['type'] ?? 'direct',
            'password' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'expires_at' => $data['expires_at'] ?? null,
            'click_limit' => $data['click_limit'] ?? null,
            'safety_status' => 'pending',
        ]);

        $link->setRelation('domain', $domain);
        $this->syncRules($request, $link);
        $this->syncPixels($request, $link);
        ScanLink::dispatchSync($link->id);
        $this->fireWebhooks($user, 'link.created', [
            'id' => $link->id,
            'alias' => $link->alias,
            'short_url' => $link->shortUrl(),
            'long_url' => $link->long_url,
        ]);
        Link::forgetCache($domain->id, $alias);

        return redirect()->route('links.index')->with('status', 'Link created: '.$link->shortUrl());
    }

    public function edit(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);
        $link->load('domain');

        // Always offer the link's current domain, even if it is no longer active.
        $domains = $this->selectableDomains($request->user());
        if ($link->domain && ! $domains->contains('id', $link->domain->id)) {
            $domains = $domains->prepend($link->domain);
        }

        return view('links.edit', [
            'link' => $link,
            'domain' => $link->domain,
            'domains' => $domains,
            'pixels' => $request->user()->pixels()->get(),
            'attachedPixelIds' => $link->pixels()->pluck('pixels.id')->all(),
            'campaigns' => $request->user()->campaigns()->orderBy('name')->get(),
            'canDeepLink' => app(PlanGate::class)->allows($request->user(), 'deep_links'),
            'aiEnabled' => app(ClaudeClient::class)->enabled(),
        ]);
    }

    public function update(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);

        $oldDomainId = $link->domain_id;
        $domain = $request->has('domain_id')
            ? ($this->selectableDomains($request->user())->firstWhere('id', (int) $request->input('domain_id'))
                ?: ($link->domain ?: $this->domains->default()))
            : ($link->domain ?: $this->domains->default());
        abort_unless($domain, 500, 'No default domain configured.');

        $data = $this->validateLink($request);

        $oldAlias = $link->alias;
        $alias = trim((string) ($data['alias'] ?? '')) ?: $oldAlias;
        // Re-check uniqueness when the alias OR the domain changes.
        if ($alias !== $oldAlias || $domain->id !== $oldDomainId) {
            if ($error = $this->aliases->validateCustom($alias, $domain->id, $link->id)) {
                return back()->withInput()->withErrors(['alias' => $error]);
            }
        }

        $link->fill([
            'domain_id' => $domain->id,
            'campaign_id' => $this->resolveCampaignId($request),
            'alias' => $alias,
            'long_url' => $data['long_url'],
            'params' => $this->buildParams($data),
            'title' => $data['title'] ?? null,
            'tags' => $this->parseTags($data['tags'] ?? null),
            'meta' => $this->buildMeta($request, $request->user(), $link),
            'type' => $data['type'] ?? 'direct',
            'expires_at' => $data['expires_at'] ?? null,
            'click_limit' => $data['click_limit'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $link->password = Hash::make($data['password']);
        }

        $link->save();
        $link->setRelation('domain', $domain);
        $this->syncRules($request, $link);
        $this->syncPixels($request, $link);
        ScanLink::dispatchSync($link->id);

        Link::forgetCache($oldDomainId, $oldAlias);
        Link::forgetCache($domain->id, $alias);

        return redirect()->route('links.index')->with('status', 'Link updated.');
    }

    /**
     * Domains a user may publish links on: the system default plus their own
     * verified (active) custom domains, but only while their plan allows custom
     * domains. The default is always first.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Domain>
     */
    private function selectableDomains(User $user): \Illuminate\Support\Collection
    {
        $domains = collect();
        if ($default = $this->domains->default()) {
            $domains->push($default);
        }
        if (app(PlanGate::class)->allows($user, 'custom_domains')) {
            $domains = $domains->concat(
                $user->domains()->where('is_default', false)->where('status', 'active')->orderBy('host')->get()
            );
        }

        return $domains->unique('id')->values();
    }

    public function destroy(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);

        $domainId = $link->domain_id;
        $alias = $link->alias;
        $link->delete();

        Link::forgetCache($domainId, $alias);

        return redirect()->route('links.index')->with('status', 'Link deleted.');
    }

    /** @return array<string, mixed> */
    private function validateLink(Request $request): array
    {
        $data = $request->validate([
            'long_url' => ['required', 'url', 'max:2048'],
            'alias' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:300'],
            'campaign_id' => ['nullable', 'integer'],
            'deep_link_ios' => ['nullable', 'string', 'max:2048'],
            'deep_link_android' => ['nullable', 'string', 'max:2048'],
            'type' => ['nullable', 'in:direct,frame,splash,overlay,cta'],
            'expires_at' => ['nullable', 'date'],
            'password' => ['nullable', 'string', 'max:100'],
            'click_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'utm_source' => ['nullable', 'string', 'max:150'],
            'utm_medium' => ['nullable', 'string', 'max:150'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'custom_params' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($error = app(LinkSafety::class)->screen($data['long_url'])) {
            throw ValidationException::withMessages(['long_url' => $error]);
        }

        return $data;
    }

    /** Resolve the posted campaign to one the user owns, or null. */
    private function resolveCampaignId(Request $request): ?int
    {
        $id = $request->integer('campaign_id') ?: null;
        if ($id && ! $request->user()->campaigns()->whereKey($id)->exists()) {
            return null;
        }

        return $id;
    }

    /**
     * Parse a comma/newline list of tags into a normalised array (lowercase,
     * deduped, max 10 tags of 30 chars), or null when empty.
     *
     * @return array<int, string>|null
     */
    private function parseTags(?string $raw): ?array
    {
        return Link::normalizeTags($raw);
    }

    /**
     * Build the link's meta JSON. Mobile deep links are a paid feature; the inputs
     * are ignored (and existing meta preserved) when the plan doesn't include them.
     *
     * @return array<string, mixed>|null
     */
    private function buildMeta(Request $request, User $user, ?Link $link): ?array
    {
        $meta = $link?->meta ?? [];

        if (app(PlanGate::class)->allows($user, 'deep_links')) {
            $deep = array_filter([
                'ios' => trim((string) $request->input('deep_link_ios', '')),
                'android' => trim((string) $request->input('deep_link_android', '')),
            ]);

            if ($deep) {
                $meta['deep_link'] = $deep;
            } else {
                unset($meta['deep_link']);
            }
        }

        return $meta ?: null;
    }

    /**
     * Assemble the UTM + custom query parameters from the form into a flat map,
     * or null when none are set. Custom params are "key=value" per line.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function buildParams(array $data): ?array
    {
        $params = [];
        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $k) {
            $v = trim((string) ($data["utm_{$k}"] ?? ''));
            if ($v !== '') {
                $params["utm_{$k}"] = $v;
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) ($data['custom_params'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key !== '') {
                $params[$key] = $value;
            }
        }

        return $params ?: null;
    }

    /** Replace a link's targeting / rotation rules from the submitted form. */
    private function syncRules(Request $request, Link $link): void
    {
        $link->rules()->delete();

        $rows = $request->input('rules', []);
        if (! is_array($rows)) {
            return;
        }

        $sort = 0;
        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            $target = trim((string) ($row['target_url'] ?? ''));

            if (! in_array($type, ['geo', 'device', 'os', 'language', 'time', 'rotation'], true)) {
                continue;
            }
            if ($target === '' || ! filter_var($target, FILTER_VALIDATE_URL)) {
                continue;
            }

            $link->rules()->create([
                'type' => $type,
                'match_value' => $this->parseRuleMatch($type, (string) ($row['match'] ?? '')),
                'target_url' => $target,
                'weight' => $type === 'rotation' ? max(1, (int) ($row['weight'] ?? 1)) : null,
                'sort' => $sort++,
            ]);
        }
    }

    /** Sync the visitor-retargeting pixels attached to a link (owner's pixels only). */
    private function syncPixels(Request $request, Link $link): void
    {
        $ids = array_map('intval', (array) $request->input('pixels', []));
        $valid = $request->user()->pixels()->whereIn('id', $ids)->pluck('id')->all();
        $link->pixels()->sync($valid);
    }

    /** Queue any of the user's active webhooks that subscribe to the given event. */
    private function fireWebhooks(User $user, string $event, array $payload): void
    {
        Webhook::fire($user->id, $event, $payload);
    }

    /** @return array<string, mixed>|null */
    private function parseRuleMatch(string $type, string $match): ?array
    {
        $match = trim($match);

        if ($type === 'rotation') {
            return null;
        }

        if ($type === 'time') {
            [$from, $to] = array_pad(array_map('trim', explode('-', $match, 2)), 2, null);

            return ['from' => $from ?: null, 'to' => $to ?: null];
        }

        $values = array_values(array_filter(array_map('trim', explode(',', $match))));
        if ($type === 'geo') {
            $values = array_map('strtoupper', $values);
        }

        return ['values' => $values];
    }
}
