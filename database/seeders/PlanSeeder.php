<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Starting values only — the whole point of the feature is that the admin
 * edits these (or replaces them) from the panel. Slugs match the historical
 * tenants.plan strings so existing tenants link up without a data migration.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Trial',
                'slug' => 'trial',
                'description' => '30-day free trial with full access.',
                'price' => 0,
                'features' => [],
                'is_trial' => true,
                'sort_order' => 0,
                'marketing_description' => ['en' => 'Full platform, free for 30 days.', 'sq' => 'Platforma e plotë, falas për 30 ditë.'],
                'marketing_highlights' => [],
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For small fleets getting started.',
                'price' => 20,
                'marketing_description' => ['en' => 'For small operators getting started.', 'sq' => 'Për operatorë të vegjël në fillim.'],
                'marketing_highlights' => ['branding', 'vehicle_limit', 'staff_seat_limit'],
                'features' => [
                    PlanFeature::VehicleLimit->value => 5,
                    PlanFeature::PhotosPerVehicle->value => 5,
                    PlanFeature::Reports->value => false,
                    PlanFeature::FleetHeatmap->value => false,
                    PlanFeature::Branding->value => true,
                    PlanFeature::StaffSeatLimit->value => 1,
                    PlanFeature::Templates->value => false,
                    PlanFeature::PromoCodes->value => false,
                    PlanFeature::MaintenanceReminders->value => false,
                    PlanFeature::Reviews->value => false,
                    PlanFeature::Waitlist->value => false,
                    PlanFeature::StockAlert->value => false,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'For growing rental businesses.',
                'price' => 40,
                'marketing_description' => ['en' => 'For growing rental businesses.', 'sq' => 'Për biznese në rritje.'],
                'marketing_highlights' => ['reports', 'fleet_heatmap', 'templates', 'promo_codes', 'reviews'],
                'features' => [
                    PlanFeature::VehicleLimit->value => 10,
                    PlanFeature::PhotosPerVehicle->value => 8,
                    PlanFeature::Reports->value => true,
                    PlanFeature::FleetHeatmap->value => true,
                    PlanFeature::Branding->value => true,
                    PlanFeature::StaffSeatLimit->value => 3,
                    PlanFeature::Templates->value => true,
                    PlanFeature::PromoCodes->value => true,
                    PlanFeature::MaintenanceReminders->value => true,
                    PlanFeature::Reviews->value => true,
                    PlanFeature::Waitlist->value => true,
                    PlanFeature::StockAlert->value => true,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Unlimited fleet, every feature.',
                'price' => 80,
                'marketing_description' => ['en' => 'Unlimited fleet, every feature.', 'sq' => 'Flotë pa kufi, çdo veçori.'],
                'marketing_highlights' => ['vehicle_limit', 'ai_listing_writer', 'ai_concierge'],
                'features' => [
                    PlanFeature::AiListingWriter->value => true,
                    PlanFeature::AiBusinessSummary->value => true,
                    PlanFeature::AiPricingSuggestions->value => true,
                    PlanFeature::AiConcierge->value => true,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
