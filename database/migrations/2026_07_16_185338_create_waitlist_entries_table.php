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
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email'); // the notify channel
            $table->string('phone')->nullable(); // optional; deliberately not linked to customers
            // Null dates mean "tell me whenever this vehicle frees up" — the shape
            // the stock-alert idea needs. Only the date-range trigger is built here.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('locale')->nullable(); // captured at join; picks the mail language
            $table->timestamp('notified_at')->nullable(); // idempotency — mail once per entry
            $table->timestamps(); // created_at is the FIFO key

            $table->index(['tenant_id', 'vehicle_id', 'notified_at']);
            $table->unique(['tenant_id', 'vehicle_id', 'email', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
