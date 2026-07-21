<?php

use App\Enums\TenantStatus;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enroll already-Active tenants that never got a paid_until.
 *
 * Only approveAction() ever set paid_until from nothing, so any tenant that
 * reached Active another way (admin form created directly as Active, seeder,
 * factory) was invisible to ProcessTenantSubscriptions — its query is
 * whereNotNull('paid_until') — and kept storefront and panel access with
 * nothing left to lapse. Tenant::booted() now closes that hole for new writes;
 * this backfills the rows that predate it.
 *
 * The period is taken from trial_ends_at when present, since that is the date
 * the operator and the admin were already shown; tenants with no such date get
 * a fresh trial period instead. A backfilled date already in the past starts
 * the grace window immediately — that is deliberate, and the day-after-lapse
 * reminder (config/billing.php reminder_days) means the operator still hears
 * about it before suspension.
 */
return new class extends Migration
{
    public function up(): void
    {
        $trialDays = (int) config('billing.trial_days');

        // Resolved per row in PHP rather than one SQL UPDATE: COALESCE + INTERVAL
        // is Postgres-only syntax and would fail to parse on the SQLite test
        // suite even with no rows to match. The set is tiny by nature — only
        // tenants that slipped through the enrollment hole.
        $unenrolled = DB::table('tenants')
            ->where('status', TenantStatus::Active->value)
            ->whereNull('paid_until')
            ->get(['id', 'trial_ends_at']);

        foreach ($unenrolled as $tenant) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'paid_until' => $tenant->trial_ends_at !== null
                    ? Carbon::parse($tenant->trial_ends_at)
                    : now()->addDays($trialDays)->endOfDay(),
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately empty: clearing paid_until would un-enroll these tenants
        // and restore the unlimited-free-access bug this migration exists to fix.
    }
};
