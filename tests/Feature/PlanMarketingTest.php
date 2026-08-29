<?php

declare(strict_types=1);

use App\Enums\PlanFeature;
use App\Models\Plan;

/*
 * Plan::marketingHighlightLines() and Plan::marketingTagline() feed the public
 * pricing card: the admin picks a subset of PlanFeature values and writes a
 * bilingual one-liner, and these turn that into rendered strings.
 */

it('returns only the picked highlight lines, ordered by the marketing order', function () {
    $plan = Plan::factory()
        ->withFeatures([
            PlanFeature::VehicleLimit->value => 5,
            PlanFeature::Reports->value => true,
        ])
        // Deliberately out of marketing order; Reports comes after Branding/limits.
        ->withHighlights([PlanFeature::Reports->value, PlanFeature::VehicleLimit->value])
        ->make();

    expect($plan->marketingHighlightLines())->toBe([
        'Up to 5 vehicles',
        __('marketing.plan_feature_reports'),
    ]);
});

it('drops a picked feature whose line resolves to null', function () {
    $plan = Plan::factory()
        ->withFeatures([PlanFeature::Reports->value => false])
        ->withHighlights([PlanFeature::Reports->value])
        ->make();

    expect($plan->marketingHighlightLines())->toBe([]);
});

it('returns no highlight lines when none are picked', function () {
    expect(Plan::factory()->withHighlights([])->make()->marketingHighlightLines())->toBe([]);
});

it('returns the tagline for the active locale', function () {
    $plan = Plan::factory()->make([
        'marketing_description' => ['en' => 'Growing fleets.', 'sq' => 'Flota në rritje.'],
    ]);

    app()->setLocale('sq');
    expect($plan->marketingTagline())->toBe('Flota në rritje.');

    app()->setLocale('en');
    expect($plan->marketingTagline())->toBe('Growing fleets.');
});

it('falls back to English when the active locale has no tagline', function () {
    $plan = Plan::factory()->make(['marketing_description' => ['en' => 'Only English.']]);

    app()->setLocale('sq');

    expect($plan->marketingTagline())->toBe('Only English.');
});
