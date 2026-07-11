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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('type'); // percentage | fixed
            $table->decimal('value', 10, 2);
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();       // null = unlimited
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable(); // null = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
