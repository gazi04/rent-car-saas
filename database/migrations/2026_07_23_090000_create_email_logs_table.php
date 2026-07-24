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
        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            // Null tenant_id = platform/central mail (e.g. subscription reminders
            // queued outside tenant context); nullOnDelete keeps the log if the
            // tenant is later removed.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            // Resend's message id (stamped as the X-Resend-Email-ID header on send);
            // the webhook matches delivery events back to this row on it.
            $table->string('message_id')->nullable();
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->string('mailable')->nullable();
            $table->string('status')->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('status');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
