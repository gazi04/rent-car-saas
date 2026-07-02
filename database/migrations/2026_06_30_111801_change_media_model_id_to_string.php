<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spatie Media Library creates model_id as bigint. Tenant uses a UUID string
     * primary key, which a bigint column rejects. Widen to varchar on every driver
     * so tests (SQLite) run against the same shape production (Postgres) does.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Postgres has no implicit bigint→varchar cast in ALTER; needs USING.
            DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE varchar(255) USING model_id::varchar');

            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            $table->string('model_id', 255)->change();
        });
    }

    public function down(): void
    {
        // Reverting to bigint is only possible while every row is numeric —
        // fail loudly instead of corrupting/aborting mid-ALTER on UUID rows.
        $nonNumeric = match (DB::getDriverName()) {
            'pgsql' => DB::table('media')->whereRaw("model_id !~ '^[0-9]+$'")->count(),
            'mysql', 'mariadb' => DB::table('media')->whereRaw("model_id NOT REGEXP '^[0-9]+$'")->count(),
            default => DB::table('media')->whereRaw("model_id GLOB '*[^0-9]*'")->count(),
        };

        if ($nonNumeric > 0) {
            throw new RuntimeException(
                "Cannot revert media.model_id to bigint: {$nonNumeric} row(s) hold non-numeric IDs (e.g. tenant UUIDs)."
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE bigint USING model_id::bigint');

            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_id')->change();
        });
    }
};
