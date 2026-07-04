<?php

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
                'sort_order' => 0,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For small fleets getting started.',
                'price' => 15,
                'features' => [
                    PlanFeature::VehicleLimit->value => 5,
                    PlanFeature::PhotosPerVehicle->value => 5,
                    PlanFeature::Reports->value => false,
                    PlanFeature::Branding->value => true,
                    PlanFeature::StaffSeatLimit->value => 1,
                    PlanFeature::PromoCodes->value => false,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'For growing rental businesses.',
                'price' => 29,
                'features' => [
                    PlanFeature::VehicleLimit->value => 10,
                    PlanFeature::PhotosPerVehicle->value => 8,
                    PlanFeature::Reports->value => true,
                    PlanFeature::Branding->value => true,
                    PlanFeature::StaffSeatLimit->value => 3,
                    PlanFeature::PromoCodes->value => true,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Unlimited fleet, every feature.',
                'price' => 49,
                'features' => [
                    PlanFeature::AiListingWriter->value => true,
                    PlanFeature::AiBusinessSummary->value => true,
                    PlanFeature::AiPricingSuggestions->value => true,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
