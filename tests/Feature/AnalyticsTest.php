<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalyticsController;
use App\Models\Domain;
use App\Models\Link;
use App\Models\QrCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function link(?User $user = null): Link
    {
        $user ??= User::factory()->create();

        return Link::create([
            'user_id' => $user->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'a'.Str::random(5),
            'long_url' => 'https://example.com',
            'type' => 'direct',
            'safety_status' => 'safe',
        ]);
    }

    private function click(int $linkId, array $attrs = []): void
    {
        DB::table('clicks')->insert(array_merge([
            'link_id' => $linkId,
            'ip_hash' => 'ip1',
            'country' => 'US',
            'device' => 'mobile',
            'os' => 'iOS',
            'browser' => 'Safari',
            'referer_host' => 't.co',
            'language' => 'en',
            'is_bot' => false,
            'created_at' => now(),
        ], $attrs));
    }

    public function test_rollup_aggregates_daily_and_dimensions(): void
    {
        $link = $this->link();
        $this->click($link->id, ['ip_hash' => 'ip1']);
        $this->click($link->id, ['ip_hash' => 'ip1']);
        $this->click($link->id, ['ip_hash' => 'ip2', 'is_bot' => true, 'device' => 'bot']);

        $this->artisan('clicks:rollup')->assertSuccessful();

        $day = today()->toDateString();
        $this->assertDatabaseHas('stat_daily', [
            'link_id' => $link->id, 'day' => $day, 'clicks' => 3, 'uniques' => 2, 'bots' => 1,
        ]);
        $this->assertDatabaseHas('stat_dimension', [
            'link_id' => $link->id, 'day' => $day, 'dimension' => 'country', 'label' => 'US', 'clicks' => 3,
        ]);
        $this->assertDatabaseHas('stat_dimension', [
            'link_id' => $link->id, 'day' => $day, 'dimension' => 'device', 'label' => 'mobile', 'clicks' => 2,
        ]);

        $this->assertSame((string) DB::table('clicks')->max('id'), Setting::get('clicks_rollup_cursor'));
    }

    public function test_rollup_is_idempotent_and_incremental(): void
    {
        $link = $this->link();
        $this->click($link->id, ['ip_hash' => 'ip1']);
        $this->click($link->id, ['ip_hash' => 'ip2']);

        $this->artisan('clicks:rollup');
        $this->artisan('clicks:rollup'); // no new clicks: must not double-count

        $day = today()->toDateString();
        $this->assertSame(2, (int) DB::table('stat_daily')->where('link_id', $link->id)->where('day', $day)->value('clicks'));

        $this->click($link->id, ['ip_hash' => 'ip3']);
        $this->artisan('clicks:rollup');

        $this->assertSame(3, (int) DB::table('stat_daily')->where('link_id', $link->id)->where('day', $day)->value('clicks'));
    }

    public function test_prune_removes_old_clicks_but_keeps_rollups(): void
    {
        $link = $this->link();
        $this->click($link->id, ['ip_hash' => 'old', 'created_at' => now()->subDays(100)]);
        $this->click($link->id, ['ip_hash' => 'new', 'created_at' => now()]);

        $this->artisan('clicks:rollup');
        $this->artisan('clicks:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('clicks')->count()); // only the recent click remains
        $this->assertDatabaseHas('stat_daily', ['link_id' => $link->id, 'day' => now()->subDays(100)->toDateString()]);
    }

    public function test_analytics_pages_render(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link->id, ['ip_hash' => 'x']);
        $this->artisan('clicks:rollup');

        $this->actingAs($user)->get('/analytics')->assertOk()->assertSee('Clicks over time');
        $this->actingAs($user)->get(route('links.stats', $link))->assertOk()->assertSee('Clicks over time');
    }

    public function test_custom_date_range_and_visual_breakdowns_render(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link->id, ['ip_hash' => 'a', 'country' => 'US']);
        $this->artisan('clicks:rollup');

        $from = now()->subDays(5)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($user)->get("/analytics?from={$from}&to={$to}")
            ->assertOk()
            ->assertSee('Clicks by country')
            ->assertSee('Top countries')
            ->assertSee('Top cities')
            ->assertSee('Platforms')
            ->assertSee('Browsers')
            ->assertSee('vendor/flags/us.svg')        // real flag for a present country
            ->assertSee('value="'.$from.'"', false);  // custom range reflected in the picker
    }

    public function test_city_detail_rolls_up_and_renders(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link->id, ['ip_hash' => 'a', 'country' => 'US', 'region' => 'California', 'city' => 'San Francisco']);
        $this->click($link->id, ['ip_hash' => 'b', 'country' => 'US', 'region' => 'California', 'city' => 'San Francisco']);
        $this->click($link->id, ['ip_hash' => 'c', 'country' => 'GB', 'city' => 'London']);
        $this->artisan('clicks:rollup');

        // The city dimension is rolled up alongside country.
        $this->assertSame(2, (int) DB::table('stat_dimension')
            ->where('dimension', 'city')->where('label', 'San Francisco')->sum('clicks'));

        // And it surfaces in the analytics UI.
        $this->actingAs($user)->get('/analytics?range=30')
            ->assertOk()
            ->assertSee('Top cities')
            ->assertSee('San Francisco')
            ->assertSee('London');
    }

    public function test_analytics_source_filter_renders_links_bio_and_qr(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/analytics')->assertOk()->assertSee('Total clicks')->assertSee('Bio Pages');
        $this->actingAs($user)->get('/analytics?source=bio')->assertOk()->assertSee('Page views')->assertSee('Views over time');
        $this->actingAs($user)->get('/analytics?source=qr')->assertOk()->assertSee('QR scans');
    }

    public function test_csv_export_returns_data(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link->id, ['ip_hash' => 'x']);
        $this->artisan('clicks:rollup');

        $response = $this->actingAs($user)->get('/analytics/export?range=30');
        $response->assertOk();
        $this->assertStringContainsString('date,clicks', $response->streamedContent());
    }

    public function test_per_link_stats_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('links.stats', $link))->assertForbidden();
        $this->actingAs($intruder)->get(route('links.stats.export', $link))->assertForbidden();
    }

    public function test_ownership_check_tolerates_id_type_differences(): void
    {
        // On some shared hosts PDO returns ids as numeric strings, so a model's
        // user_id and the authenticated user's id can be DIFFERENT php types with
        // the SAME value. A strict (===) owner check then wrongly returns 403 on
        // every per-item page. The check must compare by value, not type.
        $owner = User::factory()->create();
        $link = $this->link($owner);

        // Simulate the host: the link's user_id arrives as the string "1".
        $link->user_id = (string) $link->user_id;
        $this->assertNotSame($owner->id, $link->user_id, 'precondition: the ids are different php types');

        $request = Request::create('/');
        $request->setUserResolver(fn () => $owner); // $owner->id is an int

        // With a strict === check this aborts 403; the int-cast comparison passes.
        $response = app(AnalyticsController::class)->exportLink($request, $link);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_analytics_rolls_up_on_read_when_no_cron_has_run(): void
    {
        // Clicks are recorded raw + the denormalised counter, but the charts read
        // the rollup tables. On shared hosting without a working cron the rollup
        // never ran, so analytics showed 0 despite real clicks. The read must
        // self-heal by rolling up on demand.
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $this->click($link->id, ['ip_hash' => 'a']);
        $this->click($link->id, ['ip_hash' => 'b']);
        // Deliberately do NOT run clicks:rollup here.

        $this->actingAs($owner)->get(route('links.stats', $link))->assertOk();

        // Viewing analytics rolled the clicks up on its own.
        $this->assertDatabaseHas('stat_daily', ['link_id' => $link->id, 'clicks' => 2, 'uniques' => 2]);
    }

    public function test_qr_code_has_its_own_analytics_scoped_to_its_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $this->click($link->id, ['ip_hash' => 'x']);
        $this->click($link->id, ['ip_hash' => 'y']);
        $this->artisan('clicks:rollup');

        $qr = QrCode::create([
            'user_id' => $owner->id, 'name' => 'My QR', 'type' => 'link', 'is_dynamic' => true,
            'link_id' => $link->id, 'content' => 'https://example.com', 'data' => [], 'design' => [], 'scans' => 0,
        ]);

        $this->actingAs($owner)->get(route('qr.stats', $qr))->assertOk()
            ->assertSee('My QR')->assertSee('QR scans')->assertSee('Scans over time');

        // Export + ownership.
        $res = $this->actingAs($owner)->get(route('qr.stats.export', $qr));
        $res->assertOk();
        $this->assertStringContainsString('date,clicks', $res->streamedContent());

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('qr.stats', $qr))->assertForbidden();
        $this->actingAs($intruder)->get(route('qr.stats.export', $qr))->assertForbidden();
    }

    public function test_bio_page_has_its_own_analytics(): void
    {
        $owner = User::factory()->create();
        $page = $owner->bioPages()->create(['slug' => 'mine', 'title' => 'Mine', 'is_published' => true]);

        $this->actingAs($owner)->get(route('bio.stats', $page))->assertOk()
            ->assertSee('Page views')->assertSee('Views over time');

        $res = $this->actingAs($owner)->get(route('bio.stats.export', $page));
        $res->assertOk();
        $this->assertStringContainsString('date,clicks', $res->streamedContent());

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('bio.stats', $page))->assertForbidden();
        $this->actingAs($intruder)->get(route('bio.stats.export', $page))->assertForbidden();
    }
}
