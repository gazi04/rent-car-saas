<?php

declare(strict_types=1);

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| tenant_id cannot be mass-assigned onto another tenant
|--------------------------------------------------------------------------
|
| BelongsToTenant fills tenant_id from the active tenant only when it is not
| already set, so for as long as tenant_id was listed in a model's #[Fillable],
| one create($input)/fill($input) carrying a tenant_id key wrote a row into
| another tenant — silently, and with the global scope none the wiser, because
| the scope filters reads, not writes.
|
| These tests exercise the guard the way an attacker would reach it: through
| mass assignment. They deliberately do NOT use factories —
| Factory::makeInstance() wraps instantiation in Model::unguarded()
| (vendor/laravel/framework/.../Factories/Factory.php:525), which bypasses
| $fillable entirely. That is also why the suite's existing
| Model::factory()->create(['tenant_id' => $other->id]) cross-tenant fixtures
| (e.g. tests/Feature/Admin/TenantOverviewTest.php) keep working: they are not
| what this rule governs.
|
| The remaining bypasses — forceFill, forceCreate, setAttribute,
| Model::unguarded — are unchanged and intentional. They are explicit at the
| call site and greppable; mass assignment is neither.
|
| tests/Arch/TenantIdNotFillableTest.php pins the declaration; this pins the
| behaviour.
|
*/

/** @return array<string, mixed> */
function vehicleAttributes(): array
{
    return [
        'name' => 'Golf',
        'category' => VehicleCategory::cases()[0],
        'year' => 2020,
        'fuel_type' => FuelType::cases()[0],
        'transmission' => Transmission::cases()[0],
        'seats' => 5,
        'daily_rate' => 50,
        'status' => VehicleStatus::Available,
        'is_public' => true,
    ];
}

it('ignores a foreign tenant_id when creating a vehicle', function () {
    $victim = Tenant::factory()->withDomain('victim')->create();
    $attacker = Tenant::factory()->withDomain('attacker')->create();

    tenancy()->initialize($attacker);

    $vehicle = Vehicle::create([...vehicleAttributes(), 'tenant_id' => $victim->id]);

    expect($vehicle->tenant_id)->toBe($attacker->id);

    // And it is really in the attacker's tenant, not merely reported as such.
    tenancy()->end();
    tenancy()->initialize($victim);

    expect(Vehicle::query()->count())->toBe(0);
})->group('security');

it('ignores a foreign tenant_id when creating a customer', function () {
    $victim = Tenant::factory()->withDomain('victim')->create();
    $attacker = Tenant::factory()->withDomain('attacker')->create();

    tenancy()->initialize($attacker);

    $customer = Customer::create([
        'name' => 'Arben',
        'phone' => '+38344123456',
        'tenant_id' => $victim->id,
    ]);

    expect($customer->tenant_id)->toBe($attacker->id);
})->group('security');

it('does not let a foreign tenant_id move an existing row on update', function () {
    $victim = Tenant::factory()->withDomain('victim')->create();
    $attacker = Tenant::factory()->withDomain('attacker')->create();

    tenancy()->initialize($attacker);

    $vehicle = Vehicle::create(vehicleAttributes());

    $vehicle->fill(['name' => 'Passat', 'tenant_id' => $victim->id])->save();

    expect($vehicle->fresh()->tenant_id)->toBe($attacker->id)
        ->and($vehicle->fresh()->name)->toBe('Passat');
})->group('security');
