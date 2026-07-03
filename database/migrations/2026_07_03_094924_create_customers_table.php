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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Operator-facing + BelongsToTenant-scoped: string FK matching tenants.id.
            $table->string('tenant_id');
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // One customer record per phone per tenant — backs the find-or-create
            // by phone in BookingService and the manual-add uniqueness rule.
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
