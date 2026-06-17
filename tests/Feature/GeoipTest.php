<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Analytics\GeoipUpdater;
use App\Services\Analytics\GeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->clearManaged();
    }

    protected function tearDown(): void
    {
        $this->clearManaged();
        parent::tearDown();
    }

    /** Remove any operator/updater-managed DB so tests fall back to the bundled seed. */
    private function clearManaged(): void
    {
        foreach (glob(storage_path('app/geoip/*.mmdb')) ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob(storage_path('app/geoip/.download-*')) ?: [] as $f) {
            @unlink($f);
        }
    }

    public function test_bundled_country_database_resolves_a_country(): void
    {
        // The ~8 MB DB-IP country DB ships in database/geoip/ and the resolver
        // falls back to it, so country geo works with zero setup.
        $this->assertSame('US', app(GeoResolver::class)->country('8.8.8.8'));
    }

    public function test_updater_rejects_maxmind_without_a_license_key(): void
    {
        Setting::put('geoip_maxmind_key', '');

        $this->expectException(\RuntimeException::class);
        app(GeoipUpdater::class)->update('maxmind', 'country');
    }

    public function test_updater_downloads_decompresses_and_installs_a_dbip_database(): void
    {
        $seed = (string) file_get_contents(base_path('database/geoip/dbip-country-lite.mmdb'));
        Http::fake(['download.db-ip.com/*' => Http::response(gzencode($seed), 200)]);

        $message = app(GeoipUpdater::class)->update('dbip', 'country');

        $this->assertStringContainsString('Country', $message);
        $this->assertFileExists(storage_path('app/geoip/geoip.mmdb'));
        $this->assertSame('US', app(GeoResolver::class)->country('8.8.8.8'));
        $this->assertSame('dbip', Setting::get('geoip_provider'));
    }

    public function test_geo_tab_renders_and_saves_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings', ['tab' => 'geo']))
            ->assertOk()->assertSee('GeoIP database');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'geo', 'geoip_provider' => 'dbip', 'geoip_edition' => 'city',
        ])->assertRedirect(route('admin.settings', ['tab' => 'geo']));

        $this->assertSame('city', Setting::get('geoip_edition'));
    }

    public function test_geo_update_route_is_admin_only_and_fails_gracefully(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response('', 404)]);

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('admin.settings.geo.update'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.geo.update'))
            ->assertRedirect(route('admin.settings', ['tab' => 'geo']))
            ->assertSessionHas('error');
    }
}
