<?php

use App\Enums\VehicleStatus;
use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
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
 * Create an active tenant on the given subdomain plus an operator user for it.
 *
 * @return array{0: Tenant, 1: User}
 */
function fleetOperator(string $domain): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $user = new User;
    $user->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
    ])->save();

    return [$tenant, $user];
}

/**
 * Minimum required fields to submit the vehicle form.
 *
 * @return array<string, mixed>
 */
function vehicleFormData(array $overrides = []): array
{
    return array_merge([
        'name' => 'VW Golf',
        'category' => 'sedan',
        'year' => 2022,
        'fuel_type' => 'petrol',
        'transmission' => 'manual',
        'seats' => 5,
        'daily_rate' => 45,
        'status' => 'available',
        'is_public' => true,
    ], $overrides);
}

it('auto-fills tenant_id when a vehicle is created in a tenant context', function () {
    [$tenant] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);

    $vehicle = Vehicle::factory()->create();

    expect($vehicle->tenant_id)->toBe($tenant->id);
});

it('scopes vehicles to the current tenant', function () {
    [$tenantA] = fleetOperator('a.localhost');
    [$tenantB] = fleetOperator('b.localhost');

    tenancy()->initialize($tenantA);
    Vehicle::factory()->count(2)->create();
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Vehicle::factory()->count(3)->create();
    expect(Vehicle::count())->toBe(3);
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect(Vehicle::count())->toBe(2);
});

it('defaults a new vehicle to public and available', function () {
    [$tenant] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);

    $vehicle = Vehicle::create([
        'name' => 'Toyota Corolla',
        'category' => 'sedan',
        'year' => 2023,
        'fuel_type' => 'petrol',
        'transmission' => 'automatic',
        'seats' => 5,
        'daily_rate' => 40,
    ]);

    expect($vehicle->is_public)->toBeTrue()
        ->and($vehicle->status)->toBe(VehicleStatus::Available);
});

it('soft deletes a vehicle but keeps the row', function () {
    [$tenant] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create();

    $vehicle->delete();

    expect(Vehicle::count())->toBe(0)
        ->and(Vehicle::withTrashed()->count())->toBe(1);
});

it('creates a vehicle with custom fields through the operator panel form', function () {
    [$tenant, $operator] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    Livewire::test(CreateVehicle::class)
        ->fillForm(vehicleFormData([
            'name' => 'BMW 320i',
            'custom_fields' => [['label' => 'GPS', 'value' => 'Included']],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $vehicle = Vehicle::firstWhere('name', 'BMW 320i');

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->tenant_id)->toBe($tenant->id)
        ->and($vehicle->custom_fields)->toBe([['label' => 'GPS', 'value' => 'Included']]);
});

it('attaches an uploaded photo on a subdomain and generates a webp conversion', function () {
    [$tenant, $operator] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    Livewire::test(CreateVehicle::class)
        ->fillForm(vehicleFormData([
            'name' => 'Audi A4',
            'photos' => [UploadedFile::fake()->image('car.jpg', 800, 600)],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $vehicle = Vehicle::firstWhere('name', 'Audi A4');
    $media = $vehicle->getFirstMedia('vehicle_photos');

    expect($vehicle->getMedia('vehicle_photos'))->toHaveCount(1)
        ->and($media->hasGeneratedConversion('web'))->toBeTrue();
});

it('stores vehicle photos under a tenants/{tenant_id}/vehicle_photos/{media_id}/ path, not a bare media-ID folder', function () {
    [$tenant] = fleetOperator('ardi.localhost');
    tenancy()->initialize($tenant);
    Storage::fake('public');

    $vehicle = Vehicle::factory()->create();
    $media = $vehicle->addMedia(UploadedFile::fake()->image('car.jpg', 800, 600))
        ->toMediaCollection('vehicle_photos');

    $expectedPrefix = "tenants/{$tenant->id}/vehicle_photos/{$media->id}/";

    expect($media->getPath())->toContain($expectedPrefix)
        ->and($media->getPathRelativeToRoot())->toStartWith($expectedPrefix);
});

it('keeps vehicle photos from different tenants under separate storage roots', function () {
    [$tenantA] = fleetOperator('a.localhost');
    [$tenantB] = fleetOperator('b.localhost');

    tenancy()->initialize($tenantA);
    Storage::fake('public');
    $vehicleA = Vehicle::factory()->create();
    $mediaA = $vehicleA->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('vehicle_photos');
    $rootA = $mediaA->getPath();
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Storage::fake('public');
    $vehicleB = Vehicle::factory()->create();
    $mediaB = $vehicleB->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('vehicle_photos');
    $rootB = $mediaB->getPath();

    expect($rootA)->not->toBe($rootB)
        ->and($rootA)->toContain((string) $tenantA->id)
        ->and($rootB)->toContain((string) $tenantB->id);
});
