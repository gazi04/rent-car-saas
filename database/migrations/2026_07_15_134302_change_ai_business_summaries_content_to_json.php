<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen ai_business_summaries.content from a single-language string to a
     * bilingual {en, sq} JSON payload. Existing summaries are duplicated into
     * both languages so no operator loses their current summary text.
     */
    public function up(): void
    {
        foreach (DB::table('ai_business_summaries')->select('id', 'content')->get() as $row) {
            DB::table('ai_business_summaries')
                ->where('id', $row->id)
                ->update(['content' => json_encode(['en' => $row->content, 'sq' => $row->content])]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ai_business_summaries ALTER COLUMN content TYPE json USING content::json');
        } else {
            Schema::table('ai_business_summaries', function (Blueprint $table): void {
                $table->json('content')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_business_summaries ALTER COLUMN content TYPE text USING content->>'en'");
        } else {
            Schema::table('ai_business_summaries', function (Blueprint $table): void {
                $table->text('content')->change();
            });

            foreach (DB::table('ai_business_summaries')->select('id', 'content')->get() as $row) {
                $decoded = json_decode((string) $row->content, true);

                DB::table('ai_business_summaries')
                    ->where('id', $row->id)
                    ->update(['content' => is_array($decoded) ? ($decoded['en'] ?? '') : $row->content]);
            }
        }
    }
};
