<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Spatie Media Library creates model_id as bigint. Tenant uses a UUID string primary key,
        // which Postgres rejects as bigint. SQLite (tests) handles mixed types natively.
        DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE varchar(255) USING model_id::varchar');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE bigint USING model_id::bigint');
    }
};
