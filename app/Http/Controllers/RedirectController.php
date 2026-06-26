<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\Setting;
use App\Services\Analytics\BioAnalytics;
use App\Services\Analytics\GeoResolver;
use App\Services\Analytics\RecordClick;
use App\Services\Analytics\UaParser;
use App\Services\Billing\PlanGate;
use App\Services\Linking\DomainResolver;
use App\Services\Linking\RuleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RedirectController extends Controller
{
    public function __construct(private DomainResolver $domains) {}

    /**
     * The redirect hot-path. Registered as the route fallback so it only fires
     * for paths no other route claims. Kept lean: one cached lookup, guard
     * checks, then an after-response click record.
     */
    public function handle(Request $request)
    {
        $alias = trim($request->path(), '/');

        if ($alias === '' || str_contains($alias, '/')) {
            abort(404);
        }

        $domain = $this->domains->resolve($request->getHost());
        $link = $domain ? $this->lookup($domain->id, $alias) : null;

        if (! $link) {
            // A slug can also be a published bio page (shared root namespace).
            return $this->renderBio($request, $alias) ?? abort(404);
        }

        if (! $link->is_active) {
            return response()->view('redirect.unavailable', ['reason' => 'inactive'], 410);
        }
        if ($link->isExpired()) {
            return response()->view('redirect.unavailable', ['reason' => 'expired'], 410);
        }
        if ($link->isOverLimit()) {
            return response()->view('redirect.unavailable', ['reason' => 'limit'], 410);
        }
        if ($link->safety_status === 'blocked') {
            return response()->view('redirect.blocked', ['link' => $link], 403);
        }
        if ($link->password && ! $request->session()->get("lf_unlocked:{$link->id}")) {
            return response()->view('redirect.password', ['alias' => $alias, 'error' => null]);
        }

        // Smart routing: geo / device / os / language / time targeting + weighted rotation.
        // Cloudflare visitor headers are only trusted when the operator has confirmed the
        // site sits behind Cloudflare (Admin > Settings > Geo). Otherwise any client could
        // spoof CF-IPCountry to defeat geo-targeting and poison analytics, so we ignore them
        // and resolve geo from the bundled database against the real connecting IP.
        $cf = Setting::get('geo_cf_headers') === '1';
        $cfCountry = $cf ? $request->headers->get('CF-IPCountry') : null;
        $ip = $cf ? ($request->headers->get('CF-Connecting-IP') ?: $request->ip()) : $request->ip();
        $parsed = UaParser::parse($request->userAgent());
        $routeCtx = [
            'country' => app(GeoResolver::class)->country($ip, $cfCountry),
            'device' => $parsed['device'],
            'os' => $parsed['os'],
            'language' => $request->getPreferredLanguage() ? substr((string) $request->getPreferredLanguage(), 0, 5) : null,
            'now' => now(),
        ];
        $target = $link->appendParams(app(RuleResolver::class)->resolve($link, $routeCtx));

        $ctx = [
            'link_id' => $link->id,
            'user_id' => $link->user_id,
            'alias' => $link->alias,
            'short_url' => $request->url(),
            'target' => $target,
            'ip' => $ip,
            'cf_country' => $cfCountry,
            'cf_city' => $cf ? $request->headers->get('CF-IPCity') : null,
            'cf_region' => $cf ? $request->headers->get('CF-Region') : null,
            'ua' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'language' => substr((string) $request->getPreferredLanguage(), 0, 10) ?: null,
        ];
        app()->terminating(fn () => app(RecordClick::class)($ctx));

        // Render the interstitial splash for non-direct link types, OR whenever the link
        // has retargeting pixels attached. Pixels are client-side scripts and need an HTML
        // page to fire on, so even a "direct" link must pass through the splash to track.
        $pixels = $link->relationLoaded('pixels') ? $link->pixels : $link->pixels()->get();

        // Mobile deep link (Pro feature): try to open the native app, with a web fallback.
        if ($appUrl = $this->resolveDeepLink($link, $routeCtx['os'])) {
            return response()->view('redirect.deeplink', [
                'target' => $target,
                'appUrl' => $appUrl,
                'pixels' => $pixels,
            ]);
        }

        $ad = $this->resolveAd($link);
        if ($link->type !== 'direct' || $pixels->isNotEmpty() || $ad) {
            return response()->view('redirect.splash', [
                'target' => $target,
                'pixels' => $pixels,
                'ad' => $ad,
                'skipSeconds' => $ad ? max(0, (int) Setting::get('ads_skip_seconds', 5)) : 0,
            ]);
        }

        return redirect()->away($target, 302);
    }

    /**
     * The app deep-link URI to attempt for this visitor, or null. Only fires on
     * iOS/Android, when the link has a target for that OS, and the owner's plan
     * includes deep links.
     */
    private function resolveDeepLink(Link $link, ?string $os): ?string
    {
        if (! in_array($os, ['iOS', 'Android'], true) || ! $link->hasDeepLinks()) {
            return null;
        }

        $owner = $link->relationLoaded('user') ? $link->user : $link->user()->first();
        if (! $owner || ! app(PlanGate::class)->allows($owner, 'deep_links')) {
            return null;
        }

        return $link->deepLinkFor($os);
    }

    /**
     * Which ad (if any) shows on the interstitial for this link:
     *   - owner on an ad-free plan  -> their OWN ad code (the member monetizes their traffic)
     *   - otherwise (free user)     -> the operator's ad unit (the operator monetizes the free tier)
     * Returns a render spec ['code'=>?, 'image'=>?, 'url'=>?, 'own'=>bool] or null.
     */
    private function resolveAd(Link $link): ?array
    {
        if (Setting::get('ads_enabled') !== '1') {
            return null;
        }

        $owner = $link->relationLoaded('user') ? $link->user : $link->user()->first();
        if (! $owner) {
            return null;
        }

        // Premium / ad-free: never show operator ads; show the member's own ad slots if set.
        if (app(PlanGate::class)->allows($owner, 'ad_free')) {
            $slots = MonetizationController::slotsFor($owner);
            $slots = array_values(array_filter($slots)); // drop the form padding / empties

            return $slots ? ['own' => true, 'slots' => $slots] : null;
        }

        // Free tier: the operator's ad. Count an impression after the response.
        $op = Advertisement::activeFor('interstitial');
        if (! $op) {
            return null;
        }
        app()->terminating(fn () => $op->recordImpression());

        if ($op->code) {
            return ['code' => $op->code, 'own' => false];
        }

        return $op->imageUrl() ? ['image' => $op->imageUrl(), 'url' => $op->target_url, 'own' => false] : null;
    }

    /** Verify the password for a protected link and unlock it for the session. */
    public function unlock(Request $request, string $alias)
    {
        $domain = $this->domains->resolve($request->getHost());
        $link = $domain ? Link::where('domain_id', $domain->id)->where('alias', $alias)->first() : null;

        if (! $link || ! $link->password) {
            abort(404);
        }

        if (! Hash::check((string) $request->input('password'), $link->password)) {
            return response()->view('redirect.password', [
                'alias' => $alias,
                'error' => 'Incorrect password. Please try again.',
            ], 422);
        }

        $request->session()->put("lf_unlocked:{$link->id}", true);

        return redirect('/'.$alias);
    }

    private function renderBio(Request $request, string $slug)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort')])
            ->first();

        if (! $page) {
            return null;
        }

        // Password gate.
        if ($page->setting('password') && ! session("bio_unlocked.{$page->id}")) {
            return response()->view('bio.gate', ['page' => $page, 'mode' => 'password', 'error' => session('bio_gate_error')]);
        }

        // Sensitive-content warning.
        if ($page->setting('sensitive') && ! session("bio_ack.{$page->id}")) {
            return response()->view('bio.gate', ['page' => $page, 'mode' => 'sensitive', 'error' => null]);
        }

        $bio = app(BioAnalytics::class);
        app()->terminating(function () use ($page, $bio, $request) {
            DB::table('bio_pages')->where('id', $page->id)->increment('views');
            $bio->record($page->id, null, 'view', $request);
        });

        return response()->view('bio.show', ['page' => $page]);
    }

    private function lookup(int $domainId, string $alias): ?Link
    {
        return Cache::remember(
            Link::cacheKey($domainId, $alias),
            300,
            // Eager-load the owner + plan so monetization's resolveAd() reads them from the
            // warmed cache payload instead of querying users + plans on every redirect.
            fn () => Link::with(['rules', 'pixels', 'user.plan'])->where('domain_id', $domainId)->where('alias', $alias)->first()
        );
    }
}
