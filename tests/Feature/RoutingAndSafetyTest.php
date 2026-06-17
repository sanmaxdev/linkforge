<?php

namespace Tests\Feature;

use App\Jobs\ScanLink;
use App\Models\Domain;
use App\Models\Link;
use App\Models\User;
use App\Services\Linking\RuleResolver;
use App\Services\Safety\ThreatScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoutingAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function makeLink(array $attrs = []): Link
    {
        $user = $attrs['user'] ?? User::factory()->create();
        unset($attrs['user']);

        return Link::create(array_merge([
            'user_id' => $user->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'a'.Str::random(6),
            'long_url' => 'https://example.com',
            'type' => 'direct',
            'safety_status' => 'safe',
        ], $attrs));
    }

    private function ctx(array $over = []): array
    {
        return array_merge([
            'country' => null, 'device' => 'desktop', 'os' => 'Windows', 'language' => 'en', 'now' => now(),
        ], $over);
    }

    public function test_rule_resolver_targets_by_device_then_falls_back(): void
    {
        $link = $this->makeLink(['long_url' => 'https://desktop.example.com']);
        $link->rules()->create(['type' => 'device', 'match_value' => ['values' => ['mobile']], 'target_url' => 'https://m.example.com', 'sort' => 0]);
        $link->load('rules');

        $resolver = app(RuleResolver::class);
        $this->assertSame('https://m.example.com', $resolver->resolve($link, $this->ctx(['device' => 'mobile'])));
        $this->assertSame('https://desktop.example.com', $resolver->resolve($link, $this->ctx(['device' => 'desktop'])));
    }

    public function test_rule_resolver_rotation_returns_a_configured_target(): void
    {
        $link = $this->makeLink();
        $link->rules()->create(['type' => 'rotation', 'target_url' => 'https://a.example.com', 'weight' => 1, 'sort' => 0]);
        $link->rules()->create(['type' => 'rotation', 'target_url' => 'https://b.example.com', 'weight' => 1, 'sort' => 1]);
        $link->load('rules');

        $resolver = app(RuleResolver::class);
        for ($i = 0; $i < 15; $i++) {
            $this->assertContains($resolver->resolve($link, $this->ctx()), ['https://a.example.com', 'https://b.example.com']);
        }
    }

    public function test_creating_a_link_persists_targeting_rules(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/links', [
            'long_url' => 'https://example.com',
            'rules' => [
                ['type' => 'geo', 'match' => 'us, ca', 'target_url' => 'https://na.example.com'],
                ['type' => 'rotation', 'match' => '', 'target_url' => 'https://b.example.com', 'weight' => 3],
            ],
        ])->assertRedirect(route('links.index'));

        $link = $user->links()->firstOrFail();
        $this->assertSame(2, $link->rules()->count());
        $this->assertSame(['US', 'CA'], $link->rules()->where('type', 'geo')->first()->match_value['values']);
        $this->assertSame(3, $link->rules()->where('type', 'rotation')->first()->weight);
    }

    public function test_redirect_routes_by_device_rule(): void
    {
        $link = $this->makeLink(['alias' => 'go', 'long_url' => 'https://desktop.example.com']);
        $link->rules()->create(['type' => 'device', 'match_value' => ['values' => ['mobile']], 'target_url' => 'https://m.example.com', 'sort' => 0]);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari'])
            ->get('/go')->assertRedirect('https://m.example.com');
    }

    public function test_blocked_domain_is_rejected_at_create(): void
    {
        config()->set('linkforge.safety.blocked_domains', ['evil.test']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/links', ['long_url' => 'https://evil.test/phish'])
            ->assertSessionHasErrors('long_url');

        $this->assertSame(0, $user->links()->count());
    }

    public function test_scan_blocks_malicious_url_from_threat_feed(): void
    {
        config()->set('linkforge.safety.providers.urlhaus', true);
        Http::fake([
            'urlhaus-api.abuse.ch/*' => Http::response(['query_status' => 'ok', 'threat' => 'malware_download'], 200),
        ]);

        $link = $this->makeLink(['long_url' => 'https://bad.test', 'safety_status' => 'pending']);
        (new ScanLink($link->id))->handle(app(ThreatScanner::class));

        $this->assertSame('blocked', $link->fresh()->safety_status);
        $this->assertDatabaseHas('safety_scans', ['link_id' => $link->id, 'provider' => 'urlhaus', 'verdict' => 'malicious']);
    }

    public function test_blocked_link_shows_interstitial_on_redirect(): void
    {
        $this->makeLink(['alias' => 'danger', 'safety_status' => 'blocked']);

        $this->get('/danger')->assertStatus(403)->assertSee('Security warning');
    }

    public function test_registration_blocks_honeypot_and_disposable_email(): void
    {
        $this->post('/register', [
            'name' => 'Bot', 'email' => 'bot@example.com',
            'password' => 'forge-strong-pass-1', 'password_confirmation' => 'forge-strong-pass-1',
            'company' => 'ACME Corp',
        ])->assertSessionHasErrors();
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);

        $this->post('/register', [
            'name' => 'Temp', 'email' => 'temp@mailinator.com',
            'password' => 'forge-strong-pass-1', 'password_confirmation' => 'forge-strong-pass-1',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'temp@mailinator.com']);
    }

    public function test_abuse_report_is_stored(): void
    {
        $this->post('/report', ['reason' => 'This is a phishing link', 'alias' => 'whatever'])
            ->assertRedirect(route('report.create'));

        $this->assertDatabaseHas('abuse_reports', ['reason' => 'This is a phishing link', 'status' => 'open']);
    }

    public function test_geo_resolver_prefers_cloudflare_country_header(): void
    {
        $geo = app(\App\Services\Analytics\GeoResolver::class);

        $this->assertSame('DE', $geo->country('1.2.3.4', 'DE'));
        $this->assertSame('GB', $geo->country(null, 'gb'));   // case-normalized
        $this->assertNull($geo->country('127.0.0.1', 'XX'));  // Cloudflare "unknown" ignored; localhost has no DB result
        $this->assertNull($geo->country('127.0.0.1', null));  // local IP, no header, no DB
        $this->assertSame('US', $geo->country('8.8.8.8'));    // falls back to the bundled DB
    }

    public function test_redirect_geo_targets_using_cloudflare_header(): void
    {
        $link = $this->makeLink(['alias' => 'geo', 'long_url' => 'https://global.example.com']);
        $link->rules()->create(['type' => 'geo', 'match_value' => ['values' => ['DE']], 'target_url' => 'https://de.example.com', 'sort' => 0]);

        $this->withHeaders(['CF-IPCountry' => 'DE'])->get('/geo')->assertRedirect('https://de.example.com');
        $this->withHeaders(['CF-IPCountry' => 'US'])->get('/geo')->assertRedirect('https://global.example.com');
    }

    public function test_click_records_country_from_cloudflare_header(): void
    {
        $link = $this->makeLink(['alias' => 'cc']);

        $this->withHeaders(['CF-IPCountry' => 'FR'])->get('/cc');

        $this->assertDatabaseHas('clicks', ['link_id' => $link->id, 'country' => 'FR']);
    }
}
