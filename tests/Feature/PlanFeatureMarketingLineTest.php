<?php

declare(strict_types=1);

use App\Enums\PlanFeature;
use App\Models\Plan;

/*
 * PlanFeature::marketingLine() turns a plan's resolved feature value into a
 * pricing-card bullet (or null to omit it), and marketingOrder() is the curated
 * list the public page iterates. Lives in Feature, not Unit, because it resolves
 * translation strings and tests/Unit has no application container.
 */

covers(PlanFeature::class);

/** @param array<string, mixed> $features */
function planWithFeatures(array $features): Plan
{
    return Plan::factory()->withFeatures($features)->make();
}

it('renders an enabled toggle as its localized label', function () {
    $plan = planWithFeatures([PlanFeature::Reports->value => true]);

    expect(PlanFeature::Reports->marketingLine($plan))
        ->toBe(__('marketing.plan_feature_reports'));
});

it('omits a disabled toggle', function () {
    $plan = planWithFeatures([PlanFeature::Reports->value => false]);

    expect(PlanFeature::Reports->marketingLine($plan))->toBeNull();
});

it('omits an AI feature the plan does not include', function () {
    expect(PlanFeature::AiConcierge->marketingLine(planWithFeatures([])))->toBeNull();
});

it('renders a numeric limit with its count, pluralized', function () {
    $plan = planWithFeatures([
        PlanFeature::VehicleLimit->value => 5,
        PlanFeature::StaffSeatLimit->value => 1,
    ]);

    expect(PlanFeature::VehicleLimit->marketingLine($plan))->toBe('Up to 5 vehicles')
        ->and(PlanFeature::StaffSeatLimit->marketingLine($plan))->toBe('1 staff account');
});

it('renders an absent limit as the unlimited label', function () {
    expect(PlanFeature::VehicleLimit->marketingLine(planWithFeatures([])))
        ->toBe(__('marketing.plan_feature_vehicle_limit_unlimited'));
});

it('includes every feature case in the marketing display order', function () {
    expect(PlanFeature::marketingOrder())
        ->toEqualCanonicalizing(PlanFeature::cases());
});
