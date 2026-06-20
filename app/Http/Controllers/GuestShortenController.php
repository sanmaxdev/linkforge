<?php

namespace App\Http\Controllers;

use App\Jobs\ScanLink;
use App\Models\Link;
use App\Models\Setting;
use App\Models\User;
use App\Services\Linking\AliasGenerator;
use App\Services\Linking\DomainResolver;
use App\Services\Safety\LinkSafety;
use Illuminate\Http\Request;

/**
 * Anonymous shortening from the landing page. Links are owned by the seeded
 * "guest@system.local" account, safety-screened, rate-limited, and remembered in
 * the visitor's session so they can copy their recent links. Gated by the
 * `guest_shorten` setting (default on).
 */
class GuestShortenController extends Controller
{
    public function store(Request $request, AliasGenerator $aliases, DomainResolver $domains)
    {
        if (Setting::get('guest_shorten', '1') !== '1') {
            return response()->json(['error' => 'Public shortening is turned off. Please sign up to create links.'], 403);
        }

        $data = $request->validate(['long_url' => ['required', 'url', 'max:2048']]);

        if ($error = app(LinkSafety::class)->screen($data['long_url'])) {
            return response()->json(['error' => $error], 422);
        }

        $domain = $domains->default();
        $guest = User::where('email', 'guest@system.local')->first();
        if (! $domain || ! $guest) {
            return response()->json(['error' => 'Shortening is unavailable right now.'], 503);
        }

        $alias = $aliases->generate($domain->id);
        $link = $guest->links()->create([
            'domain_id' => $domain->id,
            'alias' => $alias,
            'long_url' => $data['long_url'],
            'type' => 'direct',
            'safety_status' => 'pending',
        ]);
        $link->setRelation('domain', $domain);
        ScanLink::dispatchSync($link->id);
        Link::forgetCache($domain->id, $alias);

        $short = $request->getScheme().'://'.$link->shortUrl();

        // Remember the visitor's recent links (most recent first, capped).
        $recent = array_values(array_unique(array_merge([$short], $request->session()->get('guest_links', []))));
        $request->session()->put('guest_links', array_slice($recent, 0, 5));

        return response()->json(['short_url' => $short, 'long_url' => $data['long_url']]);
    }
}
