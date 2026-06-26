<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Affiliate\ReferralService;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\Billing\Gateways\CoinPaymentsGateway;
use App\Services\Billing\Gateways\CryptoComGateway;
use App\Services\Billing\Gateways\OfflineGateway;
use App\Services\Billing\Gateways\PayPalGateway;
use App\Services\Billing\Gateways\StripeGateway;
use App\Services\Mail\Postman;

class BillingService
{
    /** All hosted gateways, keyed by their key. */
    public function gateways(): array
    {
        return [
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            'coinpayments' => app(CoinPaymentsGateway::class),
            'cryptocom' => app(CryptoComGateway::class),
        ];
    }

    /** The active gateway: the configured one when its keys are set, else offline. */
    public function gateway(): PaymentGateway
    {
        $g = $this->gateways()[config('linkforge.billing.gateway')] ?? null;

        return $g && $g->configured() ? $g : app(OfflineGateway::class);
    }

    /** A specific gateway by key (used for return/webhook handling), or null. */
    public function namedGateway(string $key): ?PaymentGateway
    {
        return $this->gateways()[$key] ?? null;
    }

    /**
     * Apply a plan immediately (offline checkout, Stripe/PayPal confirmation that
     * routes here, and the admin panel). Records a completed payment for paid plans.
     */
    public function activate(User $user, Plan $plan, string $gateway, ?string $reference = null): void
    {
        $this->applyPlan($user, $plan, $gateway, $reference);

        if ((float) $plan->price > 0) {
            $payment = $user->payments()->create([
                'plan_id' => $plan->id,
                'gateway' => $gateway,
                'gateway_ref' => $reference,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => 'completed',
            ]);
            app(ReferralService::class)->commissionForPayment($payment);
        }
    }

    /** Record a pending payment for a hosted checkout, to confirm on return/webhook. */
    public function recordPending(User $user, Plan $plan, string $gateway, string $reference): Payment
    {
        return $user->payments()->create([
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'gateway_ref' => $reference,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);
    }

    /**
     * Confirm a previously-recorded pending payment (idempotent): mark it completed
     * and apply the plan. Returns the payment, or null if nothing pending matched.
     */
    public function confirmPending(string $gateway, string $reference): ?Payment
    {
        $payment = Payment::where('gateway', $gateway)->where('gateway_ref', $reference)->where('status', 'pending')->first();
        if (! $payment) {
            return null;
        }

        $payment->update(['status' => 'completed']);
        if ($payment->user && $payment->plan) {
            $this->applyPlan($payment->user, $payment->plan, $gateway, $reference);
        }
        app(ReferralService::class)->commissionForPayment($payment);

        return $payment;
    }

    /** The subscription + plan + credits side of activation (no payment record), with the activation email. */
    private function applyPlan(User $user, Plan $plan, string $gateway, ?string $reference): void
    {
        $user->subscriptions()->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'canceled', 'ends_at' => now()]);

        $renewsAt = match ($plan->interval) {
            'month' => now()->addMonth(),
            'year' => now()->addYear(),
            default => null,
        };

        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'gateway_subscription_id' => $reference,
            'status' => 'active',
            'renews_at' => $renewsAt,
        ]);

        $user->forceFill([
            'plan_id' => $plan->id,
            'ai_credits' => max((int) $user->ai_credits, (int) ($plan->limit('ai_credits') ?? 0)),
        ])->save();

        if ((float) $plan->price > 0) {
            app(Postman::class)->send('subscription_activated', $user->email, [
                'name' => $user->name,
                'plan_name' => $plan->name,
                'amount' => $plan->currency.' '.number_format((float) $plan->price, 2),
                'action_url' => route('billing.index'),
            ]);
        }
    }
}
