<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `ad_free` feature flag to existing plans so the monetization model
 * works on installs created before the flag existed: paid plans (price > 0)
 * become ad-free, the free plan does not. Idempotent and non-destructive — it
 * only fills the key when absent, so an operator's manual choice is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '{}', true);
            if (! is_array($features)) {
                $features = [];
            }
            if (! array_key_exists('ad_free', $features)) {
                $features['ad_free'] = ((float) $plan->price > 0);
                DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive; nothing to roll back.
    }
};
