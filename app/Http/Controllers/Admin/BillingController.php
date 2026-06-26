<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Mail\Postman;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        // Monthly recurring revenue: normalise each active subscription's plan price to a month.
        $active = Subscription::whereIn('status', ['active', 'trialing'])->with('plan')->get();
        $mrr = $active->sum(fn (Subscription $s) => match ($s->plan?->interval) {
            'month' => (float) $s->plan->price,
            'year' => (float) $s->plan->price / 12,
            default => 0.0,
        });

        $stats = [
            'mrr' => $mrr,
            'revenue_total' => (float) Payment::where('status', 'completed')->sum('amount'),
            'revenue_month' => (float) Payment::where('status', 'completed')->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'active_subs' => $active->count(),
            'paying_users' => $active->pluck('user_id')->unique()->count(),
        ];

        $subStatus = $request->query('sub_status');
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($subStatus, fn ($q) => $q->where('status', $subStatus))
            ->latest()
            ->paginate(15, ['*'], 'subs')
            ->withQueryString();

        $payStatus = $request->query('pay_status');
        $payments = Payment::with(['user', 'plan'])
            ->when($payStatus, fn ($q) => $q->where('status', $payStatus))
            ->latest()
            ->paginate(15, ['*'], 'pay')
            ->withQueryString();

        return view('admin.billing', [
            'stats' => $stats,
            'currency' => config('linkforge.billing.currency', 'USD'),
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'subStatus' => $subStatus,
            'payStatus' => $payStatus,
            'subStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'expired'],
            'payStatuses' => ['pending', 'completed', 'failed', 'refunded'],
        ]);
    }

    /** Cancel a subscription and drop the owner back to the free plan. */
    public function updateSubscription(Request $request, Subscription $subscription)
    {
        if ($request->input('action') === 'cancel' && $subscription->isActive()) {
            $subscription->update(['status' => 'canceled', 'ends_at' => now()]);

            $free = Plan::where('slug', 'free')->value('id');
            $subscription->user?->forceFill(['plan_id' => $free])->save();

            AuditLog::record('billing.cancel', "Canceled subscription for {$subscription->user?->email}", $subscription);

            if ($subscription->user) {
                app(Postman::class)->send('subscription_canceled', $subscription->user->email, [
                    'name' => $subscription->user->name, 'plan_name' => $subscription->plan?->name ?? '',
                    'action_url' => route('billing.index'),
                ]);
            }

            return back()->with('status', 'Subscription canceled and user moved to Free.');
        }

        return back();
    }

    /**
     * Record a refund for a completed payment. This is bookkeeping only; the
     * actual refund is issued by the operator in their gateway (no money moves
     * through the app).
     */
    public function updatePayment(Request $request, Payment $payment)
    {
        if ($request->input('action') === 'refund' && $payment->status === 'completed') {
            $payment->update(['status' => 'refunded']);

            AuditLog::record('payment.refund', "Refunded {$payment->currency} {$payment->amount} for {$payment->user?->email}", $payment);

            if ($payment->user) {
                app(Postman::class)->send('payment_refunded', $payment->user->email, [
                    'name' => $payment->user->name,
                    'amount' => $payment->currency.' '.number_format((float) $payment->amount, 2),
                ]);
            }

            return back()->with('status', 'Payment marked as refunded (record only).');
        }

        return back();
    }
}
