<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dedupe stock-alert entries (backlog #3).
     *
     * The table's existing unique index covers start_date/end_date, so it does
     * nothing for a stock alert: SQL treats NULLs as distinct, meaning one
     * address could join a dateless alert unboundedly and be mailed once per row.
     * A partial index — the one shape Laravel's schema builder can't express —
     * restores the guarantee for exactly those rows. Postgres (production) and
     * SQLite (the test suite) share this syntax.
     */
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX waitlist_entries_stock_alert_unique ON waitlist_entries (tenant_id, vehicle_id, email) WHERE start_date IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS waitlist_entries_stock_alert_unique');
    }
};
