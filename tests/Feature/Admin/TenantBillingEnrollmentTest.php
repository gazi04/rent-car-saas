<?php

use App\Enums\TenantStatus;
use App\Models\Tenant;

use function Pest\Laravel\artisan;

/**
 * The billing invariant: an Active tenant always has a paid_until, so it is
 * always visible to the daily subscription sweep. Before Tenant::booted()
 * enforced this, only approveAction() set paid_until, and a tenant reaching
 * Active any other way kept storefront and panel access forever with nothing
 * left to lapse.
 */
it('enrolls a tenant created directly as active', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active, 'paid_until' => null]);

    expect($tenant->paid_until)->not->toBeNull()
        ->and($tenant->paid_until->toDateString())
        ->toBe(now()->addDays((int) config('billing.trial_days'))->toDateString());
});

it('enrolls a pending tenant when it is later activated', function () {
    $tenant = Tenant::factory()->pending()->create(['paid_until' => null]);

    expect($tenant->paid_until)->toBeNull();

    $tenant->update(['status' => TenantStatus::Active]);

    expect($tenant->fresh()->paid_until)->not->toBeNull();
});

it('does not enroll tenants that are not active', function (string $state) {
    $tenant = Tenant::factory()->{$state}()->create(['paid_until' => null]);

    expect($tenant->paid_until)->toBeNull();
})->with(['pending', 'suspended', 'cancelled']);

it('never overwrites an existing paid_until', function () {
    $paidUntil = now()->addDays(120)->startOfDay();

    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active, 'paid_until' => $paidUntil]);

    expect($tenant->fresh()->paid_until->toDateString())->toBe($paidUntil->toDateString());
});

it('makes a directly-created active tenant visible to the subscription sweep', function () {
    // Previously this tenant was skipped forever by whereNotNull('paid_until').
    Tenant::factory()->create(['status' => TenantStatus::Active, 'paid_until' => null]);

    artisan('tenants:process-subscriptions')
        ->expectsOutput('Processed 1 enrolled tenants.')
        ->assertSuccessful();
});

it('keeps a tenant enrolled when its plan changes', function () {
    // change_plan updates `plan` alone, so enrollment must not depend on the tier —
    // the lifecycle is plan-agnostic, a Basic tenant lapses exactly like a trial.
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active, 'paid_until' => null]);
    $enrolledAt = $tenant->paid_until;

    $tenant->update(['plan' => 'basic']);

    expect($tenant->fresh()->paid_until->toDateString())->toBe($enrolledAt->toDateString());
});
