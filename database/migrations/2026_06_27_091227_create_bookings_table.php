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
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->string('reference');                 // e.g. BK-2026-AB12CD; unique per-tenant (see index below)
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('pickup_location')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('rate_type');                 // hourly|daily|weekly|monthly
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('locale', 5)->default('sq');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);                            // customer-facing ref, unique within a tenant
            $table->index(['tenant_id', 'vehicle_id', 'status']);                  // availability query
            $table->index(['tenant_id', 'vehicle_id', 'start_date', 'end_date']);  // overlap scan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
