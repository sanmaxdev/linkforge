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
