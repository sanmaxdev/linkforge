<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\ReferralCommission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Affiliate\ReferralService;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AffiliateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Setting::putMany([
            'affiliate_enabled' => '1',
            'affiliate_commission_type' => 'percent',
            'affiliate_commission_value' => '20',
            'affiliate_min_payout' => '50',
            'affiliate_cookie_days' => '30',
        ]);
        Cache::flush();
    }

    private function referrer(): User
    {
        $u = User::factory()->create();
        app(ReferralService::class)->codeFor($u);

        return $u->fresh();
    }

    private function paidPlan(): Plan
    {
        return Plan::where('price', '>', 0)->orderBy('price')->firstOrFail();
    }

    public function test_referral_link_sets_cookie_and_counts_click(): void
    {
        $ref = $this->referrer();

        $this->get('/ref/'.$ref->referral_code)
            ->assertRedirect(route('register'))
            ->assertCookie('affiliate_ref', $ref->referral_code);

        $this->assertSame(1, $ref->fresh()->referral_clicks);
    }

    public function test_full_flow_register_via_link_then_upgrade_pays_commission(): void
    {
        $ref = $this->referrer();

        // Register carrying the attribution cookie that /ref/{code} sets.
        // withCookie() encrypts the value for us, mirroring a real browser cookie.
        $this->withCookie('affiliate_ref', $ref->referral_code)->post('/register', [
            'name' => 'Referred User',
            'email' => 'referred@example.com',
            'password' => 'forge-strong-pass-1',
            'password_confirmation' => 'forge-strong-pass-1',
        ])->assertRedirect('/dashboard');

        $newUser = User::firstWhere('email', 'referred@example.com');
        $this->assertSame($ref->id, $newUser->referred_by);

        // When they upgrade, the referrer earns a commission.
        app(BillingService::class)->activate($newUser, $this->paidPlan(), 'offline', 'CONV1');
        $this->assertSame(1, ReferralCommission::where('referrer_id', $ref->id)
            ->where('referred_user_id', $newUser->id)->count());
    }

    public function test_signup_via_referral_is_attributed(): void
    {
        $ref = $this->referrer();
        $new = User::factory()->create();

        app(ReferralService::class)->attributeSignup($new, $ref->referral_code);

        $this->assertSame($ref->id, $new->fresh()->referred_by);
    }

    public function test_self_referral_is_ignored(): void
    {
        $ref = $this->referrer();
        app(ReferralService::class)->attributeSignup($ref, $ref->referral_code);

        $this->assertNull($ref->fresh()->referred_by);
    }

    public function test_no_attribution_when_program_disabled(): void
    {
        Setting::put('affiliate_enabled', '0');
        Cache::flush();

        $ref = $this->referrer();
        $new = User::factory()->create();
        app(ReferralService::class)->attributeSignup($new, $ref->referral_code);

        $this->assertNull($new->fresh()->referred_by);
    }

    public function test_commission_created_on_referred_users_payment(): void
    {
        $ref = $this->referrer();
        $buyer = User::factory()->create(['referred_by' => $ref->id]);
        $plan = $this->paidPlan();

        app(BillingService::class)->activate($buyer, $plan, 'offline', 'TESTREF');

        $commission = ReferralCommission::where('referrer_id', $ref->id)->first();
        $this->assertNotNull($commission);
        $this->assertEquals(round((float) $plan->price * 0.2, 2), (float) $commission->amount);
        $this->assertSame('pending', $commission->status);

        // Idempotent: confirming the same payment again must not double-record.
        app(ReferralService::class)->commissionForPayment($commission->payment);
        $this->assertSame(1, ReferralCommission::where('referrer_id', $ref->id)->count());
    }

    public function test_no_commission_when_buyer_not_referred(): void
    {
        $buyer = User::factory()->create();
        app(BillingService::class)->activate($buyer, $this->paidPlan(), 'offline', 'X');

        $this->assertSame(0, ReferralCommission::count());
    }

    public function test_fixed_commission_amount(): void
    {
        Setting::putMany(['affiliate_commission_type' => 'fixed', 'affiliate_commission_value' => '7.50']);
        Cache::flush();

        $ref = $this->referrer();
        $buyer = User::factory()->create(['referred_by' => $ref->id]);
        app(BillingService::class)->activate($buyer, $this->paidPlan(), 'offline', 'F1');

        $this->assertEquals(7.50, (float) ReferralCommission::where('referrer_id', $ref->id)->value('amount'));
    }

    public function test_payout_requires_minimum_approved_balance(): void
    {
        $ref = $this->referrer();
        $ref->commissions()->create(['amount' => 10, 'currency' => 'USD', 'status' => 'approved']);

        $this->actingAs($ref)->post(route('affiliate.payout'), ['method' => 'paypal', 'details' => 'me@paypal.com']);
        $this->assertSame(0, $ref->payoutRequests()->count()); // below the 50 minimum

        $ref->commissions()->create(['amount' => 50, 'currency' => 'USD', 'status' => 'approved']);
        $this->actingAs($ref)->post(route('affiliate.payout'), ['method' => 'paypal', 'details' => 'me@paypal.com']);

        $this->assertSame(1, $ref->payoutRequests()->count());
        $this->assertEquals(60, (float) $ref->payoutRequests()->first()->amount);
    }

    public function test_admin_approves_commission_and_pays_payout(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $ref = $this->referrer();
        $c = $ref->commissions()->create(['amount' => 60, 'currency' => 'USD', 'status' => 'pending']);

        $this->actingAs($admin)->put(route('admin.affiliate.commission', $c), ['status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $c->fresh()->status);

        $payout = app(ReferralService::class)->requestPayout($ref->fresh(), 'paypal', 'x');
        $this->assertNotNull($payout);

        $this->actingAs($admin)->put(route('admin.affiliate.payout', $payout), ['status' => 'paid'])->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('paid', $c->fresh()->status); // settling the payout settles its commissions
    }

    public function test_rejecting_a_payout_returns_commissions_to_the_pool(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $ref = $this->referrer();
        $ref->commissions()->create(['amount' => 60, 'currency' => 'USD', 'status' => 'approved']);
        $payout = app(ReferralService::class)->requestPayout($ref->fresh(), 'paypal', 'x');

        $this->actingAs($admin)->put(route('admin.affiliate.payout', $payout), ['status' => 'rejected']);

        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertEquals(60, app(ReferralService::class)->payableBalance($ref->fresh())); // back to payable
    }

    public function test_affiliate_page_visibility_follows_the_toggle(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('affiliate.index'))->assertOk()->assertSee('referral link');

        Setting::put('affiliate_enabled', '0');
        Cache::flush();
        $this->actingAs($user)->get(route('affiliate.index'))->assertNotFound();
    }
}
