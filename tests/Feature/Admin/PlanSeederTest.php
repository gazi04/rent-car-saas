<?php

use App\Enums\PlanFeature;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

/**
 * The seeder is the shipped starting product config — it decides who gets what
 * on a fresh install. It had no coverage at all before the heatmap landed.
 */
it('seeds the heatmap to match the documented tiers', function () {
    $this->seed(PlanSeeder::class);

    $plan = fn (string $slug): Plan => Plan::query()->where('slug', $slug)->firstOrFail();

    // Basic is explicit-false (moot while Reports is off there too, but it keeps
    // Basic's premium keys uniform). Standard is explicit-true. Trial and Pro
    // omit the key and inherit the permissive default — the whole reason the
    // feature needed no migration or backfill.
    expect($plan('basic')->allows(PlanFeature::FleetHeatmap))->toBeFalse()
        ->and($plan('standard')->allows(PlanFeature::FleetHeatmap))->toBeTrue()
        ->and($plan('trial')->allows(PlanFeature::FleetHeatmap))->toBeTrue()
        ->and($plan('pro')->allows(PlanFeature::FleetHeatmap))->toBeTrue();

    // Trial/Pro must inherit rather than carry the key, or "preserve today's
    // behaviour" silently becomes "whatever the seeder happened to write".
    expect($plan('trial')->features)->not->toHaveKey(PlanFeature::FleetHeatmap->value)
        ->and($plan('pro')->features)->not->toHaveKey(PlanFeature::FleetHeatmap->value);
});

it('keeps the heatmap reachable only where the reports page is', function () {
    $this->seed(PlanSeeder::class);

    // The heatmap lives on the Reports page, so a tier with the heatmap on but
    // Reports off would be an inert combination shipped by default.
    foreach (Plan::query()->get() as $plan) {
        if ($plan->allows(PlanFeature::FleetHeatmap)) {
            expect($plan->allows(PlanFeature::Reports))
                ->toBeTrue("plan [{$plan->slug}] enables the heatmap but not the Reports page it lives on");
        }
    }
});

it('is idempotent, so re-seeding does not duplicate plans', function () {
    $this->seed(PlanSeeder::class);
    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('slug', 'standard')->count())->toBe(1)
        ->and(Plan::query()->count())->toBe(4);
});
