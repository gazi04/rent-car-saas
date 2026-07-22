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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->unsignedSmallInteger('year');
            $table->string('fuel_type');
            $table->string('transmission');
            $table->unsignedTinyInteger('seats');
            $table->decimal('daily_rate', 10, 2);                      // required base rate
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('weekly_rate', 10, 2)->nullable();
            $table->decimal('monthly_rate', 10, 2)->nullable();
            $table->string('discount_type')->nullable();              // percentage | fixed
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedInteger('mileage_limit')->nullable();     // km/day
            $table->decimal('deposit', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('custom_fields')->nullable();               // [{label, value}, …]
            $table->string('status')->default('available');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'is_public']);      // app-plan §7.2 — public listing query
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
