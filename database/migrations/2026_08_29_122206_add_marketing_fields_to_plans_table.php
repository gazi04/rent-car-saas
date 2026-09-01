<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-curated marketing copy for the public pricing cards, plus a
     * visibility flag. Before this, every active plan showed on the homepage and
     * the card bullets/tagline were auto-derived from feature gates + lang files.
     *
     * - is_public: off = a private/custom plan — still assignable to a tenant and
     *   billable (Plan::options() ignores this flag), just hidden from the
     *   marketing homepage (Plan::publiclyListed() filters on it).
     * - marketing_description: {en, sq} one-liner shown under the price.
     * - marketing_highlights: list of PlanFeature values the admin picked to show
     *   as the card's bullet list.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_public')->default(true)->after('is_active');
            $table->json('marketing_description')->nullable()->after('description');
            $table->json('marketing_highlights')->nullable()->after('features');
        });

        // Backfill the shipped tiers so the live page keeps its copy on deploy.
        // Mirrored in PlanSeeder for fresh installs.
        $seed = [
            'trial' => [
                'description' => ['en' => 'Full platform, free for 30 days.', 'sq' => 'Platforma e plotë, falas për 30 ditë.'],
                'highlights' => [],
            ],
            'basic' => [
                'description' => ['en' => 'For small operators getting started.', 'sq' => 'Për operatorë të vegjël në fillim.'],
                'highlights' => ['branding', 'vehicle_limit', 'staff_seat_limit'],
            ],
            'standard' => [
                'description' => ['en' => 'For growing rental businesses.', 'sq' => 'Për biznese në rritje.'],
                'highlights' => ['reports', 'fleet_heatmap', 'templates', 'promo_codes', 'reviews'],
            ],
            'pro' => [
                'description' => ['en' => 'Unlimited fleet, every feature.', 'sq' => 'Flotë pa kufi, çdo veçori.'],
                'highlights' => ['vehicle_limit', 'ai_listing_writer', 'ai_concierge'],
            ],
        ];

        foreach ($seed as $slug => $copy) {
            DB::table('plans')->where('slug', $slug)->update([
                'marketing_description' => json_encode($copy['description']),
                'marketing_highlights' => json_encode($copy['highlights']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['is_public', 'marketing_description', 'marketing_highlights']);
        });
    }
};
