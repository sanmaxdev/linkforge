<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingController;
use App\Models\AbuseReport;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Providers\SettingsServiceProvider;
use App\Services\Billing\BillingService;
use App\Support\ThemePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaaSTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_subscribing_changes_plan_via_offline_gateway(): void
    {
        $user = User::factory()->create();
        $pro = Plan::where('slug', 'pro')->first();

        $this->actingAs($user)->post(route('billing.subscribe', $pro))->assertRedirect(route('billing.index'));

        $this->assertSame($pro->id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'plan_id' => $pro->id, 'status' => 'active', 'gateway' => 'offline']);
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'plan_id' => $pro->id, 'status' => 'completed']);
    }

    public function test_link_limit_is_enforced(): void
    {
        $plan = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price' => 0, 'interval' => 'free', 'limits' => ['max_links' => 1], 'features' => [], 'sort' => 9]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $this->actingAs($user)->post('/links', ['long_url' => 'https://a.example.com'])->assertRedirect(route('links.index'));
        $this->actingAs($user)->post('/links', ['long_url' => 'https://b.example.com'])->assertSessionHas('error');

        $this->assertSame(1, $user->links()->count());
    }

    public function test_custom_domains_require_a_paid_plan(): void
    {
        $free = User::factory()->create();
        $this->actingAs($free)->post('/domains', ['host' => 'go.example.com'])->assertSessionHas('error');
        $this->assertSame(0, $free->domains()->count());

        $pro = User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);
        $this->actingAs($pro)->post('/domains', ['host' => 'go.example.com'])->assertSessionHas('status');
        $this->assertDatabaseHas('domains', ['host' => 'go.example.com', 'user_id' => $pro->id, 'status' => 'pending']);
    }

    public function test_customer_domains_page_shows_clean_dns_only(): void
    {
        $pro = User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        $this->actingAs($pro)->get(route('domains.index'))
            ->assertOk()
            ->assertSee('CNAME')
            ->assertSee('linkforge-verify=', false)     // the TXT token
            // Operator/server internals must NOT leak to the customer dashboard.
            ->assertDontSee(public_path())
            ->assertDontSee('Document root')
            ->assertDontSee('cPanel');
    }

    public function test_operator_sets_the_custom_domain_target_customers_see(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pro = User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        // Admin Domains tab renders with the CNAME-target field (the server-path
        // infra notice was removed; the server's real path is never shown here).
        $this->actingAs($admin)->get(route('admin.settings', ['tab' => 'domains']))
            ->assertOk()->assertSee('CNAME target')->assertDontSee(public_path());

        // Operator sets the CNAME target; customers are then told to use it.
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'domains', 'custom_domain_target' => 'cname.brand.test', 'custom_domain_ip' => '203.0.113.10',
        ])->assertRedirect(route('admin.settings', ['tab' => 'domains']));

        $this->assertSame('cname.brand.test', Setting::get('custom_domain_target'));

        $this->actingAs($pro)->get(route('domains.index'))
            ->assertOk()->assertSee('cname.brand.test')->assertSee('203.0.113.10');
    }

    public function test_admin_area_requires_admin_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Admin overview');
    }

    public function test_content_moderation_lists_and_acts_with_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['email' => 'owner@mod.test']);
        $page = $owner->bioPages()->create(['slug' => 'modme', 'title' => 'Mod Me', 'is_published' => true]);

        // List renders and finds the page.
        $this->actingAs($admin)->get(route('admin.moderation', ['tab' => 'bio']))->assertOk()->assertSee('owner@mod.test');

        // Unpublish it -> persisted + audit recorded.
        $this->actingAs($admin)->put(route('admin.moderation.bio.update', $page), ['action' => 'unpublish'])->assertRedirect();
        $this->assertFalse($page->fresh()->is_published);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bio.unpublish', 'user_id' => $admin->id, 'target_type' => 'BioPage']);

        // Delete it.
        $this->actingAs($admin)->put(route('admin.moderation.bio.update', $page), ['action' => 'delete'])->assertRedirect();
        $this->assertDatabaseMissing('bio_pages', ['id' => $page->id]);
    }

    public function test_moderation_can_verify_a_domain_but_not_the_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $default = Domain::where('is_default', true)->firstOrFail();
        $custom = User::factory()->create()->domains()->create(['host' => 'go.mod.test', 'status' => 'pending', 'is_default' => false]);

        $this->actingAs($admin)->put(route('admin.moderation.domains.update', $custom), ['action' => 'verify'])->assertRedirect();
        $this->assertSame('active', $custom->fresh()->status);

        // The system default domain is protected.
        $this->actingAs($admin)->put(route('admin.moderation.domains.update', $default), ['action' => 'delete'])->assertForbidden();
        $this->assertDatabaseHas('domains', ['id' => $default->id]);
    }

    public function test_audit_log_records_and_renders_admin_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Boss']);
        $target = User::factory()->create();

        // An auditable action.
        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Renamed', 'email' => $target->email, 'status' => 'active',
            'plan_id' => null, 'role' => 'user', 'ai_credits' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.update', 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.audit'))->assertOk()
            ->assertSee('Boss')->assertSee('user.update');
    }

    public function test_moderation_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.moderation'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.audit'))->assertForbidden();
    }

    public function test_admin_dashboard_shows_insights(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('MRR')
            ->assertSee('New signups')
            ->assertSee('Plan distribution')
            ->assertSee('Top links');
    }

    public function test_admin_can_block_a_reported_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $link = Link::create([
            'user_id' => User::factory()->create()->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'bad', 'long_url' => 'https://bad.example.com', 'type' => 'direct', 'safety_status' => 'safe',
        ]);
        $report = AbuseReport::create(['link_id' => $link->id, 'reason' => 'phishing', 'status' => 'open']);

        $this->actingAs($admin)->put(route('admin.reports.update', $report), ['action' => 'block'])->assertRedirect();

        $this->assertSame('blocked', $link->fresh()->safety_status);
        $this->assertSame('actioned', $report->fresh()->status);
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->unverified()->create(['name' => 'Old', 'ai_credits' => 0]);
        $pro = Plan::where('slug', 'pro')->first();

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'New Name', 'email' => 'new@example.com', 'status' => 'suspended',
            'plan_id' => $pro->id, 'role' => 'user', 'ai_credits' => 250, 'verified' => '1',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertSame('suspended', $target->status);
        $this->assertSame($pro->id, $target->plan_id);
        $this->assertSame(250, $target->ai_credits);
        $this->assertNotNull($target->email_verified_at);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'status' => 'suspended',
            'plan_id' => null, 'role' => 'user', 'ai_credits' => 0,
        ])->assertRedirect();

        $admin->refresh();
        $this->assertSame('admin', $admin->role);   // role change on self ignored
        $this->assertSame('active', $admin->status); // status change on self ignored
    }

    public function test_admin_user_detail_page_renders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['email' => 'detail@example.com']);

        $this->actingAs($admin)->get(route('admin.users.show', $target))->assertOk()
            ->assertSee('detail@example.com')->assertSee('Danger zone')->assertSee('AI credits');
    }

    public function test_admin_can_impersonate_a_user_and_return(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target))->assertRedirect(route('dashboard'));
        $this->assertSame($target->id, auth()->id());
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post(route('impersonate.leave'))->assertRedirect(route('admin.users'));
        $this->assertSame($admin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_admins_cannot_be_impersonated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.impersonate', $other))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $admin))->assertForbidden();
    }

    public function test_admin_can_delete_a_user_and_their_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $target->links()->create([
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'gone', 'long_url' => 'https://example.com', 'type' => 'direct', 'safety_status' => 'safe',
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $target))->assertRedirect(route('admin.users'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('links', ['alias' => 'gone']);

        // Self-delete is blocked.
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_user_export_is_csv_and_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@x.test']);
        User::factory()->create(['email' => 'suspended@x.test', 'status' => 'suspended']);

        $res = $this->actingAs($admin)->get(route('admin.users.export', ['status' => 'suspended']));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('suspended@x.test', $body);
        $this->assertStringNotContainsString('admin@x.test', $body); // filtered out
    }

    public function test_plan_management_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.plans'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.plans.create'))->assertForbidden();
    }

    public function test_admin_can_create_and_edit_a_plan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Growth', 'slug' => 'growth', 'price' => '19.00', 'currency' => 'usd', 'interval' => 'month', 'sort' => 5, 'is_active' => '1',
            'limits' => ['max_links' => '500', 'max_clicks' => '20000', 'max_domains' => '2', 'max_team' => '1', 'max_qr' => '20', 'max_bio' => '2', 'ai_credits' => '100'],
            'unlimited' => ['max_clicks' => '1'], // unlimited overrides the number
            'features' => ['api' => '1', 'custom_domains' => '1'],
        ])->assertRedirect(route('admin.plans'));

        $plan = Plan::where('slug', 'growth')->firstOrFail();
        $this->assertSame('USD', $plan->currency);
        $this->assertSame(500, $plan->limit('max_links'));
        $this->assertNull($plan->limit('max_clicks'));        // unlimited
        $this->assertTrue($plan->allows('api'));
        $this->assertFalse($plan->allows('white_label'));

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Growth', 'slug' => 'growth', 'price' => '25', 'currency' => 'USD', 'interval' => 'year',
            'limits' => ['max_links' => '999'], 'features' => ['white_label' => '1'],
        ])->assertRedirect(route('admin.plans'));

        $plan->refresh();
        $this->assertSame('year', $plan->interval);
        $this->assertTrue($plan->allows('white_label'));
        $this->assertFalse($plan->allows('api'));            // unchecked => off
        $this->assertSame(999, $plan->limit('max_links'));
        $this->assertSame(0, $plan->limit('max_domains'));    // not submitted, not unlimited => 0
    }

    public function test_plan_slug_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Dup', 'slug' => 'pro', 'price' => '1', 'currency' => 'USD', 'interval' => 'month',
        ])->assertSessionHasErrors('slug');
    }

    public function test_admin_cannot_delete_free_plan_or_plan_with_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $free = Plan::where('slug', 'free')->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.plans.destroy', $free))->assertSessionHas('error');
        $this->assertDatabaseHas('plans', ['id' => $free->id]);

        $pro = Plan::where('slug', 'pro')->firstOrFail();
        User::factory()->create(['plan_id' => $pro->id]);
        $this->actingAs($admin)->delete(route('admin.plans.destroy', $pro))->assertSessionHas('error');
        $this->assertDatabaseHas('plans', ['id' => $pro->id]);

        $temp = Plan::create(['name' => 'Temp', 'slug' => 'temp', 'price' => 5, 'interval' => 'month', 'limits' => [], 'features' => [], 'sort' => 9]);
        $this->actingAs($admin)->delete(route('admin.plans.destroy', $temp))->assertRedirect();
        $this->assertDatabaseMissing('plans', ['id' => $temp->id]);
    }

    // Settings hub -----------------------------------------------------------

    public function test_settings_require_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings'))->assertForbidden();
    }

    public function test_admin_settings_page_renders_both_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()
            ->assertSee('Site identity')->assertSee('Maintenance mode');

        $this->actingAs($admin)->get(route('admin.settings', ['tab' => 'appearance']))->assertOk()
            ->assertSee('Colour theme')->assertSee('Forge')->assertSee('Ocean');
    }

    public function test_admin_can_save_general_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'general', 'site_name' => 'Acme Links', 'site_tagline' => 'Tag', 'site_description' => 'Desc',
            'allow_registration' => '1', 'maintenance_mode' => '1', 'maintenance_message' => 'brb',
        ])->assertRedirect();

        $this->assertSame('Acme Links', Setting::get('site_name'));
        $this->assertSame('1', Setting::get('maintenance_mode'));
        $this->assertSame('brb', Setting::get('maintenance_message'));
    }

    public function test_admin_can_save_appearance_settings_and_rejects_bad_preset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'appearance', 'theme_preset' => 'graphite', 'theme_font' => 'Lora', 'brand_logo' => 'https://cdn.test/logo.svg',
        ])->assertRedirect(route('admin.settings', ['tab' => 'appearance']));

        $this->assertSame('graphite', Setting::get('theme_preset'));
        $this->assertSame('Lora', Setting::get('theme_font'));

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'appearance', 'theme_preset' => 'bogus', 'theme_font' => 'Lora',
        ])->assertSessionHasErrors('theme_preset');
    }

    public function test_admin_can_set_default_colour_scheme_and_it_overlays(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'appearance', 'theme_preset' => 'forge', 'theme_font' => 'Lora', 'theme_scheme' => 'dark',
        ])->assertRedirect();

        $this->assertSame('dark', Setting::get('theme_scheme'));

        // The no-flash scheme script + toggle are present on authenticated pages.
        $this->actingAs($admin)->withoutVite()->get('/dashboard')
            ->assertOk()
            ->assertSee("localStorage.getItem('lf-theme')", false)
            ->assertSee('data-theme-toggle', false);
    }

    public function test_new_theme_preset_applies_via_overlay(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['section' => 'appearance', 'theme_preset' => 'midnight', 'theme_font' => 'Lora'])
            ->assertRedirect();
        $this->assertSame('midnight', Setting::get('theme_preset'));

        (new SettingsServiceProvider($this->app))->boot();
        $this->assertSame(ThemePalette::COLORS['slate']['500'], config('linkforge.theme.brand.500'));
    }

    public function test_appearance_logo_upload_resizes_and_stores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'appearance', 'theme_preset' => 'forge', 'theme_font' => 'Poppins',
            'logo_file' => UploadedFile::fake()->image('logo.png', 600, 200),
        ])->assertRedirect();

        $url = Setting::get('brand_logo');
        $this->assertStringContainsString('/uploads/branding/', $url);

        $path = public_path('uploads/branding/'.basename($url));
        $this->assertFileExists($path);
        [$w, $h] = getimagesize($path);
        $this->assertLessThanOrEqual(256, max($w, $h)); // downscaled
        @unlink($path);
    }

    public function test_settings_overlay_drives_config(): void
    {
        Setting::putMany(['site_name' => 'Acme Links', 'theme_preset' => 'ocean', 'theme_font' => 'Poppins']);

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame('Acme Links', config('linkforge.name'));
        $this->assertSame(ThemePalette::COLORS['blue']['500'], config('linkforge.theme.brand.500'));
        $this->assertSame('Poppins', config('linkforge.theme.font'));
    }

    public function test_registration_can_be_disabled_from_settings(): void
    {
        $this->get('/register')->assertOk();

        Setting::put('allow_registration', '0');
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_maintenance_mode_gates_visitors_but_not_admins_or_links(): void
    {
        Link::create([
            'user_id' => User::factory()->create()->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'mtn', 'long_url' => 'https://example.com', 'type' => 'direct', 'safety_status' => 'safe', 'is_active' => true,
        ]);

        Setting::put('maintenance_mode', '1');

        // Visitor: marketing/home is gated, but live short links keep redirecting.
        $this->get('/')->assertStatus(503);
        $this->get('/mtn')->assertRedirect('https://example.com');

        // A signed-out admin must still reach the login page AND be able to submit it
        // during maintenance, or they can never get back in to lift it.
        User::factory()->create([
            'role' => 'admin', 'status' => 'active', 'email_verified_at' => now(),
            'email' => 'gate@admin.test', 'password' => Hash::make('secret-pass'),
        ]);
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => 'gate@admin.test', 'password' => 'secret-pass'])
            ->assertRedirect()->assertSessionHasNoErrors(); // signed in, not a 503

        // And an authenticated admin bypasses the gate entirely.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/')->assertOk();
    }

    // Settings hub pass 2 — integration tabs -------------------------------

    public function test_all_settings_tabs_render(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (array_keys(SettingController::TABS) as $tab) {
            $this->actingAs($admin)->get(route('admin.settings', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_admin_can_save_safety_settings_and_overlay_applies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'safety',
            'safety_blocked_domains' => "bad.example\nevil.example\n",
            'safety_blocked_keywords' => 'casino',
            'safety_urlhaus' => '1',
            'turnstile_site' => 'sitekey123',
            'turnstile_secret' => 'secretkey123',
        ])->assertRedirect(route('admin.settings', ['tab' => 'safety']));

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame(['bad.example', 'evil.example'], config('linkforge.safety.blocked_domains'));
        $this->assertTrue(config('linkforge.safety.providers.urlhaus'));
        $this->assertSame('sitekey123', config('linkforge.safety.turnstile.site'));
        $this->assertSame('secretkey123', config('linkforge.safety.turnstile.secret'));
    }

    public function test_secret_settings_are_preserved_unless_replaced_or_cleared(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = ['section' => 'ai', 'ai_provider' => 'anthropic', 'ai_cost_alias' => 1, 'ai_cost_ask' => 1, 'ai_cost_insight' => 1];

        // Set a secret.
        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['ai_model' => 'claude-opus-4-8', 'ai_key' => 'sk-secret-1']);
        $this->assertSame('sk-secret-1', Setting::get('ai_key'));

        // Re-save with no key value: preserved.
        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['ai_model' => 'claude-haiku-4-5']);
        $this->assertSame('sk-secret-1', Setting::get('ai_key'));
        $this->assertSame('claude-haiku-4-5', Setting::get('ai_model'));

        // Explicit clear.
        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['ai_model' => 'claude-opus-4-8', 'ai_key_clear' => '1']);
        $this->assertSame('', Setting::get('ai_key'));
    }

    public function test_billing_and_mail_overlays_apply(): void
    {
        Setting::putMany([
            'billing_gateway' => 'stripe', 'billing_currency' => 'EUR', 'stripe_secret' => 'sk_test_x',
            'mail_host' => 'smtp.test', 'mail_port' => '465', 'mail_encryption' => 'ssl', 'mail_from_address' => 'a@b.com',
        ]);

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame('stripe', config('linkforge.billing.gateway'));
        $this->assertSame('EUR', config('linkforge.billing.currency'));
        $this->assertSame('sk_test_x', config('linkforge.billing.stripe.secret'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.test', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('a@b.com', config('mail.from.address'));
    }

    public function test_seo_analytics_inject_on_public_pages(): void
    {
        $this->get('/')->assertDontSee('googletagmanager.com/gtag', false);

        Setting::put('seo_ga_id', 'G-TEST123');
        $this->get('/')->assertSee('G-TEST123', false)->assertSee('googletagmanager.com/gtag/js', false);
    }

    public function test_seo_og_meta_renders_on_public_pages(): void
    {
        Setting::putMany([
            'seo_meta_description' => 'Meta desc here',
            'seo_og_title' => 'Share Title Here',
            'seo_og_description' => 'Share desc here',
            'seo_og_image' => 'https://cdn.example.com/og.png',
            'seo_twitter_handle' => '@mybrand',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description" content="Meta desc here">', false)
            ->assertSee('<meta property="og:title" content="Share Title Here">', false)
            ->assertSee('<meta property="og:description" content="Share desc here">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/og.png">', false)
            ->assertSee('content="summary_large_image"', false)
            ->assertSee('<meta name="twitter:site" content="@mybrand">', false);
    }

    public function test_admin_saves_og_settings_and_normalizes_handle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'seo',
            'seo_og_title' => 'Hello',
            'seo_twitter_handle' => 'brandx',
        ])->assertRedirect(route('admin.settings', ['tab' => 'seo']));

        $this->assertSame('Hello', Setting::get('seo_og_title'));
        $this->assertSame('@brandx', Setting::get('seo_twitter_handle')); // @ is added automatically
    }

    // Billing & revenue ------------------------------------------------------

    public function test_admin_billing_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.billing'))->assertForbidden();
    }

    public function test_admin_billing_shows_revenue_and_can_cancel_and_refund(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $pro = Plan::where('slug', 'pro')->first(); // 29 / month

        app(BillingService::class)->activate($user, $pro, 'offline');

        $this->actingAs($admin)->get(route('admin.billing'))->assertOk()
            ->assertSee('USD 29.00')          // MRR + total revenue
            ->assertSee($user->email);

        // Cancel the subscription -> user is moved back to Free.
        $sub = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.billing.subscriptions.update', $sub), ['action' => 'cancel'])->assertRedirect();
        $this->assertSame('canceled', $sub->fresh()->status);
        $this->assertSame(Plan::where('slug', 'free')->value('id'), $user->fresh()->plan_id);

        // Record a refund (bookkeeping only).
        $payment = $user->payments()->where('status', 'completed')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.billing.payments.update', $payment), ['action' => 'refund'])->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_admin_billing_mrr_normalises_yearly_plans(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $yearly = Plan::create(['name' => 'Annual', 'slug' => 'annual', 'price' => 120, 'currency' => 'USD', 'interval' => 'year', 'limits' => [], 'features' => [], 'sort' => 8]);
        User::factory()->create()->subscriptions()->create(['plan_id' => $yearly->id, 'gateway' => 'offline', 'status' => 'active']);

        // 120 / 12 = 10.00 MRR.
        $this->actingAs($admin)->get(route('admin.billing'))->assertOk()->assertSee('USD 10.00');
    }
}
