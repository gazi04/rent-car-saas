<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\BrandingSettings;
use App\Filament\Operator\Pages\Reports;
use App\Filament\Operator\Pages\TemplateSettings;
use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

/**
 * PhotosPerVehicle is enforced in exactly one place — VehicleForm's
 * ->maxFiles(fn () => min(featureLimit(...) ?? 8, 8)) — which Filament turns into
 * a real `max:N` validation rule (BaseFileUpload::getValidationRules), so the
 * Livewire submit path is genuinely gated, not just FilePond client-side.
 *
 * @return array<int, UploadedFile>
 */
function planFakePhotos(int $count): array
{
    return array_map(
        fn (int $i): UploadedFile => UploadedFile::fake()->image("car-{$i}.jpg", 400, 300),
        range(1, $count),
    );
}

it('blocks uploading more photos than the plan allows', function () {
    planOperatorFor('planphotos', [PlanFeature::PhotosPerVehicle->value => 2], 'twophotos');
    Storage::fake('public');

    Livewire::test(CreateVehicle::class)
        ->fillForm([...planVehicleData(), 'photos' => planFakePhotos(3)])
        ->call('create')
        ->assertHasFormErrors(['photos']);

    expect(Vehicle::query()->count())->toBe(0);
});

it('allows photos up to the plan limit', function () {
    // Pairs with the test above — without this, a form that rejected every
    // upload would look identical.
    planOperatorFor('planphotosok', [PlanFeature::PhotosPerVehicle->value => 2], 'twophotosok');
    Storage::fake('public');

    Livewire::test(CreateVehicle::class)
        ->fillForm([...planVehicleData(), 'photos' => planFakePhotos(2)])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::query()->first()->getMedia('vehicle_photos'))->toHaveCount(2);
});

it('caps photos at the app-wide ceiling even on a generous plan', function () {
    // min(20, 8) — a plan cannot raise the cap above the app-wide ceiling of 8.
    planOperatorFor('planphotosmax', [PlanFeature::PhotosPerVehicle->value => 20], 'twentyphotos');
    Storage::fake('public');

    Livewire::test(CreateVehicle::class)
        ->fillForm([...planVehicleData(), 'photos' => planFakePhotos(9)])
        ->call('create')
        ->assertHasFormErrors(['photos']);

    expect(Vehicle::query()->count())->toBe(0);
});

it('falls back to the app-wide photo ceiling when the tenant has no plan row', function () {
    // ?? 8 — note this makes PhotosPerVehicle's "unlimited" mean 8, unlike
    // VehicleLimit/StaffSeatLimit where no limit means genuinely uncapped.
    planOperatorFor('planphotosghost'); // ghost-plan, no row
    Storage::fake('public');

    Livewire::test(CreateVehicle::class)
        ->fillForm([...planVehicleData(), 'photos' => planFakePhotos(9)])
        ->call('create')
        ->assertHasFormErrors(['photos']);

    expect(Vehicle::query()->count())->toBe(0);
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

it('serves the reports page without the heatmap when the plan disables only the heatmap', function () {
    // Two independent gates on one page: Reports controls the page, FleetHeatmap
    // controls just the grid. The assertOk() is the point — the rest of Reports
    // must survive losing the heatmap.
    planOperatorFor('planheatoff', [
        PlanFeature::Reports->value => true,
        PlanFeature::FleetHeatmap->value => false,
    ], 'noheatmap');

    expect(Reports::canAccess())->toBeTrue();

    $this->get(tenant_url('planheatoff', '/dashboard/reports'))
        ->assertOk()
        ->assertSee(__('reports.utilisation'))
        ->assertDontSee(__('reports.heatmap'));
});

it('hides the branding page when the plan disables it', function () {
    planOperatorFor('planbrand', [PlanFeature::Branding->value => false], 'nobrand');

    expect(BrandingSettings::canAccess())->toBeFalse();

    $this->get(tenant_url('planbrand', '/dashboard/branding-settings'))->assertNotFound();
});

it('serves the branding page when the plan enables it', function () {
    // Pairs with the 404 above: without an ON case, that test would still pass
    // if the route were deleted entirely.
    planOperatorFor('planbrandon', [PlanFeature::Branding->value => true], 'withbrand');

    expect(BrandingSettings::canAccess())->toBeTrue();

    $this->get(tenant_url('planbrandon', '/dashboard/branding-settings'))->assertOk();
});

it('hides the template settings page when the plan disables it', function () {
    // Templates had no route-level coverage at all — its gate was only ever
    // asserted through canAccess() in CustomTemplatesTest.
    planOperatorFor('plantmpl', [PlanFeature::Templates->value => false], 'notmpl');

    expect(TemplateSettings::canAccess())->toBeFalse();

    $this->get(tenant_url('plantmpl', '/dashboard/template-settings'))->assertNotFound();
});

it('serves the template settings page when the plan enables it', function () {
    planOperatorFor('plantmplon', [PlanFeature::Templates->value => true], 'withtmpl');

    expect(TemplateSettings::canAccess())->toBeTrue();

    $this->get(tenant_url('plantmplon', '/dashboard/template-settings'))->assertOk();
});

it('applies no restrictions when the tenant\'s plan slug has no plans row', function () {
    planOperatorFor('planghost'); // plan = 'ghost-plan', no row seeded

    expect(Reports::canAccess())->toBeTrue()
        ->and(BrandingSettings::canAccess())->toBeTrue()
        ->and(tenant()->allowsFeature(PlanFeature::FleetHeatmap))->toBeTrue()
        ->and(tenant()->featureLimit(PlanFeature::VehicleLimit))->toBeNull();

    Vehicle::factory()->count(3)->create();

    Livewire::test(CreateVehicle::class)
        ->fillForm(planVehicleData())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::query()->count())->toBe(4);
});
