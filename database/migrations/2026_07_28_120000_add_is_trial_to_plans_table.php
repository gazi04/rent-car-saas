<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            // Marks which plan row is "the" trial tier, replacing the hardcoded
            // Plan::TRIAL_SLUG constant as the source of truth. Plan::trialSlug()
            // reads this column; the constant remains only as a graceful-degradation
            // fallback for fresh installs / tests with no seeded plans table.
            $table->boolean('is_trial')->default(false)->after('is_active');
        });

        // Backfill: flag the existing seeded Trial row, if one exists yet.
        // No-op on fresh installs where PlanSeeder hasn't run.
        DB::table('plans')->where('slug', 'trial')->update(['is_trial' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('is_trial');
        });
    }
};
