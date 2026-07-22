<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail of manually recorded B2B payments. One row per payment
     * the admin confirms, recording who confirmed it and which period it covers.
     */
    public function up(): void
    {
        Schema::create('tenant_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('method');
            $table->decimal('amount', 8, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
