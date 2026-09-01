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
        Schema::table('users', function (Blueprint $table): void {
            // NULL for Super Admin (central user); set for operator users.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('operator')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // No dropIndex here: foreignId()->constrained() creates only the FK
            // constraint on Postgres, not a separate btree index.
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role']);
        });
    }
};
