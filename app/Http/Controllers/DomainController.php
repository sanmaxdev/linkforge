<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\Billing\PlanGate;
use App\Services\Linking\DomainResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainController extends Controller
{
    public function __construct(private PlanGate $gate, private DomainResolver $resolver) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('domains.index', [
            'domains' => $user->domains()->latest()->get(),
            'allowed' => $this->gate->allows($user, 'custom_domains'),
            'canAdd' => $this->gate->allows($user, 'custom_domains') && $this->gate->canCreate($user, 'max_domains'),
            'token' => $this->verifyToken($user->id),
            'appHost' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost(),
            'serverIp' => $this->serverIp($request),
            'docRoot' => public_path(),
        ]);
    }

    /** Best-effort public IP of this server, for the buyer's A record. */
    private function serverIp(Request $request): ?string
    {
        if ($ip = $request->server('SERVER_ADDR')) {
            return $ip;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost();
        $resolved = @gethostbyname($host);

        return ($resolved && $resolved !== $host) ? $resolved : null;
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $this->gate->allows($user, 'custom_domains')) {
            return back()->with('error', 'Custom domains are available on paid plans.');
        }
        if (! $this->gate->canCreate($user, 'max_domains')) {
            return back()->with('error', "You've reached your plan's custom-domain limit.");
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', Rule::unique('domains', 'host')],
        ]);

        $host = strtolower($data['host']);
        $user->domains()->create(['host' => $host, 'status' => 'pending', 'is_default' => false]);
        $this->resolver->forget($host);

        return back()->with('status', 'Domain added. Add the DNS records below, then verify.');
    }

    public function verify(Request $request, Domain $domain)
    {
        abort_unless((int) $domain->user_id === (int) $request->user()->id, 403);

        if ($this->dnsHasToken($domain->host, $this->verifyToken($request->user()->id))) {
            $domain->update(['status' => 'active']);
            $this->resolver->forget($domain->host);

            return back()->with('status', 'Domain verified and active.');
        }

        return back()->with('error', 'Verification TXT record not found yet. DNS changes can take a few minutes.');
    }

    public function destroy(Request $request, Domain $domain)
    {
        abort_unless((int) $domain->user_id === (int) $request->user()->id, 403);

        $host = $domain->host;
        $domain->delete();
        $this->resolver->forget($host);

        return back()->with('status', 'Domain removed.');
    }

    private function verifyToken(int $userId): string
    {
        return 'linkforge-verify='.substr(hash('sha256', $userId.'|'.config('app.key')), 0, 24);
    }

    private function dnsHasToken(string $host, string $token): bool
    {
        try {
            foreach (@dns_get_record($host, DNS_TXT) ?: [] as $record) {
                if (isset($record['txt']) && str_contains($record['txt'], $token)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // network / lookup failure — treat as unverified
        }

        return false;
    }
}
