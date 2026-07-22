<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen vehicles.description from a single-language string to a bilingual
     * {en, sq} JSON payload so a vehicle can carry both languages and the public
     * storefront can show the right one per visitor. Existing descriptions are
     * duplicated into both languages so nothing disappears from live listings.
     */
    public function up(): void
    {
        foreach (DB::table('vehicles')->select('id', 'description')->whereNotNull('description')->get() as $row) {
            DB::table('vehicles')
                ->where('id', $row->id)
                ->update(['description' => json_encode(['en' => $row->description, 'sq' => $row->description])]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vehicles ALTER COLUMN description TYPE json USING description::json');
        } else {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->json('description')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE vehicles ALTER COLUMN description TYPE text USING description->>'en'");
        } else {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->text('description')->nullable()->change();
            });

            foreach (DB::table('vehicles')->select('id', 'description')->whereNotNull('description')->get() as $row) {
                $decoded = json_decode((string) $row->description, true);

                DB::table('vehicles')
                    ->where('id', $row->id)
                    ->update(['description' => is_array($decoded) ? ($decoded['en'] ?? null) : $row->description]);
            }
        }
    }
};
