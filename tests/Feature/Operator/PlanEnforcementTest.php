<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\BrandingSettings;
use App\Filament\Operator\Pages\Reports;
use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(function () {
    tenancy()->end();
});

/**
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function planOperatorFor(string $domain, array $planFeatures = [], ?string $planSlug = null): array
{
    if ($planSlug !== null) {
        Plan::factory()->create(['slug' => $planSlug, 'features' => $planFeatures]);
    }

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => $planSlug ?? 'ghost-plan']);

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    return [$tenant, $operator];
}

/** @return array<string, mixed> */
function planVehicleData(): array
{
    return [
        'name' => 'Test Car',
        'category' => 'sedan',
        'year' => 2022,
        'fuel_type' => 'petrol',
        'transmission' => 'manual',
        'seats' => 5,
        'daily_rate' => 40,
    ];
}

it('blocks creating a vehicle at the plan limit and keeps existing vehicles', function () {
    planOperatorFor('planlimit', [PlanFeature::VehicleLimit->value => 2], 'capped');
    Vehicle::factory()->count(2)->create();

    Livewire::test(CreateVehicle::class)
        ->fillForm(planVehicleData())
        ->call('create');

    expect(Vehicle::query()->count())->toBe(2);
});

it('allows creating a vehicle under the plan limit', function () {
    planOperatorFor('planunder', [PlanFeature::VehicleLimit->value => 2], 'roomy');
    Vehicle::factory()->create();

    Livewire::test(CreateVehicle::class)
        ->fillForm(planVehicleData())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::query()->count())->toBe(2);
});

it('blocks new creates for an over-limit tenant without touching existing vehicles', function () {
    // Downgrade scenario: 4 vehicles, new plan allows 2.
    planOperatorFor('plandown', [PlanFeature::VehicleLimit->value => 2], 'downgraded');
    Vehicle::factory()->count(4)->create();

    Livewire::test(CreateVehicle::class)
        ->fillForm(planVehicleData())
        ->call('create');

    expect(Vehicle::query()->count())->toBe(4)
        ->and(Vehicle::query()->where('is_public', true)->count())->toBe(4);
});

it('hides the reports page when the plan disables it', function () {
    [$tenant] = planOperatorFor('planreports', [PlanFeature::Reports->value => false], 'noreports');

    expect(Reports::canAccess())->toBeFalse();

    $this->get(tenant_url('planreports', '/dashboard/reports'))->assertNotFound();
});

it('serves the reports page when the plan enables it', function () {
    planOperatorFor('planreporton', [PlanFeature::Reports->value => true], 'withreports');

    expect(Reports::canAccess())->toBeTrue();

    $this->get(tenant_url('planreporton', '/dashboard/reports'))->assertOk();
});

it('hides the branding page when the plan disables it', function () {
    planOperatorFor('planbrand', [PlanFeature::Branding->value => false], 'nobrand');

    expect(BrandingSettings::canAccess())->toBeFalse();

    $this->get(tenant_url('planbrand', '/dashboard/branding-settings'))->assertNotFound();
});

it('applies no restrictions when the tenant\'s plan slug has no plans row', function () {
    planOperatorFor('planghost'); // plan = 'ghost-plan', no row seeded

    expect(Reports::canAccess())->toBeTrue()
        ->and(BrandingSettings::canAccess())->toBeTrue()
        ->and(tenant()->featureLimit(PlanFeature::VehicleLimit))->toBeNull();

    Vehicle::factory()->count(3)->create();

    Livewire::test(CreateVehicle::class)
        ->fillForm(planVehicleData())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::query()->count())->toBe(4);
});
