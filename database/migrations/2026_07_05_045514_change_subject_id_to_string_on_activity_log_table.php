<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // subject_id was created as bigint by an earlier migration run, before
        // that migration was edited to use string (tenant subjects use UUID
        // primary keys). Raw SQL because doctrine/dbal isn't installed for ->change().
        // SQLite (test suite) has no rigid column typing, so there's nothing to fix there.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(255) USING subject_id::varchar(255)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');
        }
    }
};
