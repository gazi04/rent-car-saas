<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // The hourly bookings:expire-pending sweep asks for
            // [tenant_id, status = pending, created_at < cutoff]. The existing
            // indexes both lead with vehicle_id after tenant_id, so neither
            // serves a fleet-wide status scan.
            $table->index(['tenant_id', 'status', 'created_at'], 'bookings_pending_sweep_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_pending_sweep_index');
        });
    }
};
