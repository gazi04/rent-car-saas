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
        Schema::create('ai_business_summaries', function (Blueprint $table) {
            $table->id();
            // Operator-facing + BelongsToTenant-scoped: string FK matching tenants.id.
            $table->string('tenant_id');
            $table->text('content');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_business_summaries');
    }
};
