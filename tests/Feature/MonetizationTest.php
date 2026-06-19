<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Cache::flush(); // avoid settings/ad cache bleeding across tests
    }

    private function freeUser(): User
    {
        return User::factory()->create();
    }

    private function premiumUser(): User
    {
        return User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);
    }

    private function linkFor(User $u, string $alias): Link
    {
        return Link::create([
            'user_id' => $u->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => $alias,
            'long_url' => 'https://example.com/dest',
            'type' => 'direct',
            'safety_status' => 'safe',
            'is_active' => true,
        ]);
    }

    private function houseAd(string $placement = 'interstitial', string $code = '<b>BUYNOW</b>'): Advertisement
    {
        $ad = Advertisement::create(['name' => 'House', 'placement' => $placement, 'code' => $code, 'is_active' => true]);
        Advertisement::forgetCache();

        return $ad;
    }

    public function test_advertisement_manager_requires_admin_and_creates_ads(): void
    {
        $this->actingAs($this->freeUser())->get(route('admin.ads'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.ads'))->assertOk()->assertSee('Advertisement');
        $this->actingAs($admin)->post(route('admin.ads.store'), [
            'name' => 'AdSense Top', 'placement' => 'interstitial', 'code' => '<b>x</b>', 'is_active' => '1',
        ])->assertRedirect(route('admin.ads'));

        $this->assertDatabaseHas('advertisements', ['name' => 'AdSense Top', 'placement' => 'interstitial']);
    }

    public function test_free_users_link_shows_operator_ad_and_counts_impression(): void
    {
        Setting::put('ads_enabled', '1');
        $ad = $this->houseAd();
        $this->linkFor($this->freeUser(), 'freead');

        $this->get('/freead')->assertOk()->assertSee('Advertisement')->assertSee('BUYNOW', false);
        $this->assertSame(1, (int) $ad->fresh()->impressions);
    }

    public function test_premium_users_link_is_ad_free_and_redirects_instantly(): void
    {
        Setting::put('ads_enabled', '1');
        $this->houseAd();
        $this->linkFor($this->premiumUser(), 'premad');

        // Ad-free plan + no own ad code => straight 302, never the operator ad.
        $res = $this->get('/premad');
        $res->assertRedirect('https://example.com/dest');
    }

    public function test_premium_user_can_run_their_own_ad_code(): void
    {
        Setting::put('ads_enabled', '1');
        $this->houseAd(); // operator ad exists but must be suppressed for premium
        $u = $this->premiumUser();

        $this->actingAs($u)->put(route('monetization.update'), ['ad_code' => '<i>MYADSENSE</i>'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame('<i>MYADSENSE</i>', data_get($u->fresh()->settings, 'ad_code'));

        $this->linkFor($u, 'ownad');
        $this->get('/ownad')->assertOk()
            ->assertSee('MYADSENSE', false)        // the member's code (inside the sandboxed iframe)
            ->assertDontSee('BUYNOW', false);      // never the operator's ad
    }

    public function test_free_user_cannot_set_own_ad_code_and_sees_upsell(): void
    {
        $u = $this->freeUser();
        $this->actingAs($u)->put(route('monetization.update'), ['ad_code' => '<i>x</i>'])->assertSessionHas('error');
        $this->assertNull(data_get($u->fresh()->settings, 'ad_code'));
        $this->actingAs($u)->get(route('monetization.index'))->assertOk()->assertSee('View plans');
    }

    public function test_disabled_monetization_redirects_directly(): void
    {
        Setting::put('ads_enabled', '0');
        $this->houseAd();
        $this->linkFor($this->freeUser(), 'offad');

        $this->get('/offad')->assertRedirect('https://example.com/dest');
    }

    public function test_paid_plans_are_seeded_ad_free(): void
    {
        $this->assertTrue(Plan::where('slug', 'pro')->first()->allows('ad_free'));
        $this->assertFalse(Plan::where('slug', 'free')->first()->allows('ad_free'));
    }

    public function test_paid_user_sees_the_monetization_form_not_the_upsell(): void
    {
        // Regression: paid users were wrongly sent to the upgrade page.
        $this->actingAs($this->premiumUser())->get(route('monetization.index'))
            ->assertOk()
            ->assertSee('Your ad code')
            ->assertDontSee('View plans');
    }

    public function test_free_user_sees_dashboard_sidebar_and_popup_ads(): void
    {
        Setting::put('ads_enabled', '1');
        $this->houseAd('dashboard', '<b>DASHAD</b>');
        $this->houseAd('sidebar', '<b>SIDEAD</b>');
        $this->houseAd('popup', '<b>POPAD</b>');

        $this->actingAs($this->freeUser())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('DASHAD', false)
            ->assertSee('SIDEAD', false)
            ->assertSee('POPAD', false);
    }

    public function test_premium_user_sees_no_platform_ads_in_the_app(): void
    {
        Setting::put('ads_enabled', '1');
        $this->houseAd('dashboard', '<b>DASHAD</b>');
        $this->houseAd('sidebar', '<b>SIDEAD</b>');

        $this->actingAs($this->premiumUser())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('DASHAD', false)
            ->assertDontSee('SIDEAD', false);
    }
}
