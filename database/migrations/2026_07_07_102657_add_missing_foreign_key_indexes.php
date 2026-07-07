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
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('promo_code_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('customer_id');
        });

        Schema::table('service_records', function (Blueprint $table) {
            $table->index('blocked_date_id');
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['promo_code_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
        });

        Schema::table('service_records', function (Blueprint $table) {
            $table->dropIndex(['blocked_date_id']);
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropIndex(['recorded_by']);
        });
    }
};
