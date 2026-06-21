<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private string $code = 'a1b2c3d4-1234-5678-9abc-def012345678';

    private string $domain = 'buyer.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Ephemeral relay-signing keypair; bake its public key as the app's trust root.
        $kp = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($kp);

        config([
            'linkforge.license.verify_public_key' => base64_encode(sodium_crypto_sign_publickey($kp)),
            'linkforge.license.relay_url' => 'https://relay.test',
            'linkforge.license.item_id' => '',
        ]);
    }

    private function sign(string $issuedAt, ?string $secret = null, ?string $domain = null): string
    {
        $canonical = 'lf-license-v1|'.strtolower(trim($this->code)).'|'.($domain ?? $this->domain).'|valid|'.$issuedAt;

        return base64_encode(sodium_crypto_sign_detached($canonical, $secret ?? $this->secret));
    }

    public function test_signed_valid_response_is_trusted_as_active(): void
    {
        $issuedAt = gmdate('c');
        Http::fake(['https://relay.test/verify' => Http::response([
            'valid' => true, 'license' => ['source' => 'envato'], 'issued_at' => $issuedAt, 'signature' => $this->sign($issuedAt),
        ])]);

        $svc = app(LicenseService::class);
        $result = $svc->verify($this->code, $this->domain);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('unverified', $result);

        $svc->store($this->code, $result);
        $this->assertSame('active', Setting::get('license_status'));
        $this->assertNotNull(Setting::get('license_ok_at'));
    }

    public function test_unsigned_valid_response_is_only_unverified(): void
    {
        Http::fake(['https://relay.test/verify' => Http::response(['valid' => true, 'license' => []])]);

        $result = app(LicenseService::class)->verify($this->code, $this->domain);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['unverified']);
    }

    public function test_signature_from_a_fake_relay_key_is_not_trusted(): void
    {
        $issuedAt = gmdate('c');
        $fake = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        Http::fake(['https://relay.test/verify' => Http::response([
            'valid' => true, 'issued_at' => $issuedAt, 'signature' => $this->sign($issuedAt, $fake),
        ])]);

        $this->assertTrue(app(LicenseService::class)->verify($this->code, $this->domain)['unverified']);
    }

    public function test_stale_signature_is_rejected(): void
    {
        $old = gmdate('c', time() - 3600); // beyond the 15-minute window
        Http::fake(['https://relay.test/verify' => Http::response([
            'valid' => true, 'issued_at' => $old, 'signature' => $this->sign($old),
        ])]);

        $this->assertTrue(app(LicenseService::class)->verify($this->code, $this->domain)['unverified']);
    }

    public function test_definitive_invalid_is_a_hard_fail(): void
    {
        Http::fake(['https://relay.test/verify' => Http::response(['valid' => false, 'message' => 'revoked'], 422)]);

        $this->assertFalse(app(LicenseService::class)->verify($this->code, $this->domain)['valid']);
    }

    public function test_transient_error_fails_open_as_unverified(): void
    {
        Http::fake(['https://relay.test/verify' => Http::response('boom', 500)]);

        $result = app(LicenseService::class)->verify($this->code, $this->domain);
        $this->assertTrue($result['valid']);
        $this->assertTrue($result['unverified']);
    }

    public function test_recheck_keeps_a_known_good_license_on_an_outage(): void
    {
        Setting::putMany(['license_code' => $this->code, 'license_status' => 'active', 'license_ok_at' => now()->toIso8601String()]);
        config(['app.url' => 'https://'.$this->domain]);

        // An unreachable relay must NEVER downgrade a known-good license (fail-open).
        Http::fake(['https://relay.test/verify' => Http::response('down', 500)]);
        app(LicenseService::class)->recheck();

        $this->assertSame('active', Setting::get('license_status'));
        $this->assertFalse(app(LicenseService::class)->hasProblem());
    }

    public function test_recheck_flips_to_invalid_on_a_definitive_revoke(): void
    {
        Setting::putMany(['license_code' => $this->code, 'license_status' => 'active', 'license_ok_at' => now()->toIso8601String()]);
        config(['app.url' => 'https://'.$this->domain]);

        // A definitive "no" (revoked/refunded) flips to invalid and lights the admin banner.
        Http::fake(['https://relay.test/verify' => Http::response(['valid' => false], 422)]);
        app(LicenseService::class)->recheck();

        $this->assertSame('invalid', Setting::get('license_status'));
        $this->assertTrue(app(LicenseService::class)->hasProblem());
    }

    public function test_admin_license_page_requires_admin_and_hides_the_relay_url(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.license'))->assertForbidden();

        $res = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withoutVite()->get(route('admin.license'));
        $res->assertOk()->assertSee('License status');
        // The license server is author infrastructure — it must never be shown to buyers.
        $res->assertDontSee('relay.test');
    }
}
