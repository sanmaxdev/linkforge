<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Analytics\GeoipUpdater;
use App\Services\Analytics\GeoResolver;
use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use GeoIp2\Model\Country;
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
        foreach (['.chunk-data.part', '.chunk-data.mmdb', '.chunk-state.json'] as $f) {
            @unlink(storage_path('app/geoip/'.$f));
        }
    }

    public function test_bundled_country_database_resolves_a_country(): void
    {
        // The ~8 MB DB-IP country DB ships in database/geoip/ and the resolver
        // falls back to it, so country geo works with zero setup.
        $this->assertSame('US', app(GeoResolver::class)->country('8.8.8.8'));
    }

    public function test_country_resolves_from_a_city_database(): void
    {
        // A City-type .mmdb (DB-IP / GeoLite2 City) answers ->city() but throws
        // BadMethodCallException on ->country(); the country must be read off the
        // city record instead, or "Top countries" + the world map stay empty while
        // cities populate. Regression for exactly that production bug.
        $cityReader = new class extends Reader
        {
            public function __construct() {}

            public function city(string $ipAddress): City
            {
                return new City([
                    'country' => ['iso_code' => 'GB', 'names' => ['en' => 'United Kingdom']],
                    'city' => ['names' => ['en' => 'London']],
                    'traits' => ['ip_address' => $ipAddress],
                ], ['en']);
            }

            public function country(string $ipAddress): Country
            {
                throw new \BadMethodCallException('The country method cannot be used to open a GeoIP2-City database');
            }
        };

        $geo = $this->withReader($cityReader);

        $this->assertSame('GB', $geo->country('81.2.69.142'));
        $this->assertSame('London', $geo->city('81.2.69.142'));
    }

    public function test_country_only_database_still_resolves_country(): void
    {
        // A Country-type .mmdb answers ->country() but throws on ->city(); country
        // must still resolve (via the fallback) and city is simply null.
        $countryReader = new class extends Reader
        {
            public function __construct() {}

            public function city(string $ipAddress): City
            {
                throw new \BadMethodCallException('The city method cannot be used to open a GeoIP2-Country database');
            }

            public function country(string $ipAddress): Country
            {
                return new Country([
                    'country' => ['iso_code' => 'US', 'names' => ['en' => 'United States']],
                    'traits' => ['ip_address' => $ipAddress],
                ], ['en']);
            }
        };

        $geo = $this->withReader($countryReader);

        $this->assertSame('US', $geo->country('8.8.8.8'));
        $this->assertNull($geo->city('8.8.8.8'));
    }

    /** Build a GeoResolver wired to a fake mmdb reader (no file on disk). */
    private function withReader(Reader $reader): GeoResolver
    {
        $geo = new GeoResolver;
        $rc = new \ReflectionClass($geo);
        $rc->getProperty('reader')->setValue($geo, $reader);
        $rc->getProperty('readerResolved')->setValue($geo, true);

        return $geo;
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

    public function test_city_database_downloads_in_resumable_chunks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::putMany(['geoip_provider' => 'dbip', 'geoip_edition' => 'city']);

        // A real gzip the finish step can decompress; served with HTTP Range support.
        $gz = gzencode((string) file_get_contents(base_path('database/geoip/dbip-country-lite.mmdb')));
        Http::fake(function ($request) use ($gz) {
            if ($request->method() === 'HEAD') {
                return Http::response('', 200, ['Content-Length' => strlen($gz), 'Accept-Ranges' => 'bytes']);
            }
            $range = $request->header('Range')[0] ?? '';
            if (preg_match('/bytes=(\d+)-(\d+)/', $range, $m)) {
                return Http::response(substr($gz, (int) $m[1], (int) $m[2] - (int) $m[1] + 1), 206);
            }

            return Http::response($gz, 200);
        });

        $this->actingAs($admin);

        $start = $this->postJson(route('admin.settings.geo.download.start'))->assertOk()->json();
        $this->assertTrue($start['chunked']);
        $this->assertSame(strlen($gz), $start['total']);

        $guard = 0;
        do {
            $chunk = $this->postJson(route('admin.settings.geo.download.chunk'))->assertOk()->json();
        } while (empty($chunk['done']) && ++$guard < 100);
        $this->assertTrue($chunk['done']);

        $this->postJson(route('admin.settings.geo.download.finish'))->assertOk()->assertJson(['finished' => true]);

        $this->assertFileExists(storage_path('app/geoip/geoip.mmdb'));
        $this->assertSame('US', app(GeoResolver::class)->country('8.8.8.8'));
    }

    public function test_chunk_endpoints_require_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson(route('admin.settings.geo.download.start'))->assertForbidden();
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
