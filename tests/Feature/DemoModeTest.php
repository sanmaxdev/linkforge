<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function enableDemo(): void
    {
        config(['linkforge.demo' => true]);
    }

    public function test_demo_routes_404_when_disabled(): void
    {
        $this->get('/demo/login/admin')->assertNotFound();
        $this->get('/login')->assertDontSee('Enter as Admin');
    }

    public function test_demo_reset_creates_accounts_and_sample_data(): void
    {
        $this->artisan('demo:reset', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => Demo::ADMIN_EMAIL, 'role' => 'admin']);
        $user = User::where('email', Demo::USER_EMAIL)->firstOrFail();
        $this->assertGreaterThan(0, $user->links()->count());
        $this->assertGreaterThan(0, $user->campaigns()->count());
        $this->assertGreaterThan(0, $user->pixels()->count());
        $this->assertGreaterThan(0, $user->qrCodes()->count());
        $this->assertGreaterThan(0, $user->bioPages()->count());
        $this->assertGreaterThan(0, $user->bioPages()->first()->blocks()->count());

        // The seeded bio page is public at the root slug and renders without error.
        $this->get('/'.$user->bioPages()->first()->slug)->assertOk();

        // A full starter Help Center (20+ articles) is seeded.
        $this->assertGreaterThanOrEqual(20, \App\Models\HelpArticle::where('status', 'published')->count());

        // Analytics history is seeded + rolled up (clicks → daily + country/city dimensions).
        $linkIds = $user->links()->pluck('id');
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('clicks')->whereIn('link_id', $linkIds)->count());
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('stat_daily')->whereIn('link_id', $linkIds)->sum('clicks'));
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('stat_dimension')->whereIn('link_id', $linkIds)->where('dimension', 'country')->count());
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('stat_dimension')->whereIn('link_id', $linkIds)->where('dimension', 'city')->count());
    }

    public function test_one_click_admin_login(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();

        $this->get('/demo/login/admin')->assertRedirect(route('admin.dashboard'));
        $this->assertSame(Demo::ADMIN_EMAIL, auth()->user()->email);
    }

    public function test_one_click_customer_login(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();

        $this->get('/demo/login/user')->assertRedirect(route('dashboard'));
        $this->assertSame(Demo::USER_EMAIL, auth()->user()->email);
    }

    public function test_demo_blocks_settings_writes(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $admin = User::where('email', Demo::ADMIN_EMAIL)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.settings.update'), ['section' => 'general', 'site_name' => 'HACKED'])
            ->assertRedirect();

        $this->assertNotSame('HACKED', Setting::get('site_name'));
    }

    public function test_demo_blocks_every_settings_save_including_appearance_and_seo(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $admin = User::where('email', Demo::ADMIN_EMAIL)->firstOrFail();

        // Appearance and SEO are no longer special - every settings save is blocked.
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'appearance', 'theme_scheme' => 'dark',
        ])->assertRedirect();
        $this->assertNotSame('dark', Setting::get('theme_scheme'));

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'general', 'site_name' => 'HACKED',
        ])->assertRedirect();
        $this->assertNotSame('HACKED', Setting::get('site_name'));
    }

    public function test_demo_shows_all_settings_tabs_but_locks_them_and_masks_infra(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $admin = User::where('email', Demo::ADMIN_EMAIL)->firstOrFail();

        // Every tab is now visible so visitors can explore the full admin - including
        // the ones that used to be hidden (their secret values are masked, not the tabs).
        foreach (['general', 'login', 'billing', 'email', 'geo', 'domains', 'seo'] as $tab) {
            $this->actingAs($admin)->get(route('admin.settings', ['tab' => $tab]))->assertOk();
        }

        // The forms are read-only (disabled fieldset) and the real document root is
        // masked to a placeholder, never the server's actual path.
        $res = $this->actingAs($admin)->get(route('admin.settings', ['tab' => 'domains']));
        $res->assertSee('<fieldset disabled', false);
        $res->assertSee('/home/your-account', false);
        $res->assertDontSee(public_path(), false);
    }

    public function test_demo_blocks_updater_upload(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $admin = User::where('email', Demo::ADMIN_EMAIL)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.updates.upload'))->assertSessionHas('error');
    }

    public function test_demo_allows_real_features_like_creating_links(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $user = User::where('email', Demo::USER_EMAIL)->firstOrFail();
        $before = $user->links()->count();

        $this->actingAs($user)->post('/links', ['long_url' => 'https://example.com/demo-test'])
            ->assertRedirect(route('links.index'));

        $this->assertSame($before + 1, $user->links()->count());
    }

    public function test_one_click_login_self_seeds_when_accounts_missing(): void
    {
        $this->enableDemo();
        // The hourly demo:reset hasn't run yet — accounts don't exist.
        $this->assertDatabaseMissing('users', ['email' => Demo::ADMIN_EMAIL]);

        $this->get('/demo/login/admin')->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('users', ['email' => Demo::ADMIN_EMAIL]);
        $this->assertSame(Demo::ADMIN_EMAIL, auth()->user()->email);
    }

    public function test_demo_blocks_config_and_destructive_actions(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $user = User::where('email', Demo::USER_EMAIL)->firstOrFail();
        $admin = User::where('email', Demo::ADMIN_EMAIL)->firstOrFail();

        // Add a custom domain — blocked.
        $this->actingAs($user)->post(route('domains.store'), ['host' => 'evil.example.com']);
        $this->assertDatabaseMissing('domains', ['host' => 'evil.example.com']);

        // Create an API token — blocked.
        $this->actingAs($user)->post(route('tokens.store'), ['name' => 'demo-token']);
        $this->assertSame(0, $user->apiKeys()->count());

        // Delete own account — blocked.
        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'whatever']);
        $this->assertDatabaseHas('users', ['email' => Demo::USER_EMAIL]);

        // Admin deleting a user — blocked.
        $this->actingAs($admin)->delete(route('admin.users.destroy', $user));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_demo_marks_sensitive_pages_read_only(): void
    {
        $this->artisan('demo:reset', ['--force' => true]);
        $this->enableDemo();
        $user = User::where('email', Demo::USER_EMAIL)->firstOrFail();

        $this->actingAs($user)->get(route('domains.index'))->assertSee('disabled in the live demo', false);
        $this->actingAs($user)->get(route('developer.index'))->assertSee('disabled in the live demo', false);
    }

    public function test_demo_blocks_registration(): void
    {
        $this->enableDemo();

        $this->post('/register', [
            'name' => 'Sprawl', 'email' => 'sprawl@example.com',
            'password' => 'forge-strong-pass-1', 'password_confirmation' => 'forge-strong-pass-1',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'sprawl@example.com']);
    }

    public function test_login_page_shows_one_click_logins_and_buy_cta(): void
    {
        $this->enableDemo();

        $this->get('/login')
            ->assertSee('Enter as Admin')
            ->assertSee('Enter as Customer')
            ->assertSee('live demo', false); // the demo bar
    }
}
