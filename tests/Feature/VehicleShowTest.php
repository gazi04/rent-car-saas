<?php

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\Vehicle;

afterEach(fn () => tenancy()->end());

// ── Test helpers ────────────────────────────────────────────────────────────

function showTenant(string $subdomain): Tenant
{
    return Tenant::factory()->withDomain($subdomain)->create();
}

function showVehicle(array $attrs = []): Vehicle
{
    return Vehicle::factory()->create(array_merge([
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 50,
        'weekly_rate' => 300,
        'deposit' => 100,
    ], $attrs));
}

// ── Details page ─────────────────────────────────────────────────────────────

it('shows vehicle details with specs, rates and description', function () {
    $tenant = showTenant('showok');

    tenancy()->initialize($tenant);
    $vehicle = showVehicle([
        'name' => 'BMW 320d',
        'description' => 'Great highway cruiser.',
        'custom_fields' => [['label' => 'Color', 'value' => 'Black']],
    ]);
    tenancy()->end();

    $this->get(tenant_url('showok', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('BMW 320d')
        ->assertSee('Great highway cruiser.')
        ->assertSee('Color')
        ->assertSee('Black')
        ->assertSee('€50.00')
        ->assertSee('€300.00')
        ->assertSee('€100.00')
        ->assertSee($vehicle->category->getLabel())
        ->assertSee($vehicle->transmission->getLabel());
});

it('links to the booking wizard at /vehicles/{id}/book', function () {
    $tenant = showTenant('showlink');

    tenancy()->initialize($tenant);
    $vehicle = showVehicle();
    tenancy()->end();

    $this->get(tenant_url('showlink', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee("/vehicles/{$vehicle->id}/book", false);
});

it('still serves the booking wizard at the /book URL', function () {
    $tenant = showTenant('showbook');

    tenancy()->initialize($tenant);
    $vehicle = showVehicle();
    tenancy()->end();

    $this->get(tenant_url('showbook', "/vehicles/{$vehicle->id}/book"))
        ->assertOk()
        ->assertSee(__('booking.pick_dates'));
});

// ── Guards ────────────────────────────────────────────────────────────────────

it('returns 404 for a private vehicle', function () {
    $tenant = showTenant('showpriv');

    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->private()->create();
    tenancy()->end();

    $this->get(tenant_url('showpriv', "/vehicles/{$vehicle->id}"))
        ->assertNotFound();
});

it('returns 404 for a vehicle under maintenance', function () {
    $tenant = showTenant('showmaint');

    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->underMaintenance()->create();
    tenancy()->end();

    $this->get(tenant_url('showmaint', "/vehicles/{$vehicle->id}"))
        ->assertNotFound();
});

it('returns 404 when requesting another tenant vehicle', function () {
    $tenantA = showTenant('showcra');
    $tenantB = showTenant('showcrb');

    tenancy()->initialize($tenantA);
    $vehicle = showVehicle();
    tenancy()->end();

    $this->get(tenant_url('showcrb', "/vehicles/{$vehicle->id}"))
        ->assertNotFound();
});

// ── Layout rendering ──────────────────────────────────────────────────────────

it('renders the vehicle details page with every curated layout', function (string $layout) {
    $sub = 'showlay'.str_replace('-', '', $layout);
    $tenant = showTenant($sub);

    tenancy()->initialize($tenant);
    $tenant->setSetting('layout_vehicle_show', $layout);
    $vehicle = showVehicle(['name' => 'Layout Show Car']);
    tenancy()->end();

    $this->get(tenant_url($sub, "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('Layout Show Car');
})->with(['split-screen', 'two-column', 'z-shape']);

it('renders the vehicle listing with every curated layout', function (string $layout) {
    $sub = 'listlay'.str_replace('-', '', $layout);
    $tenant = showTenant($sub);

    tenancy()->initialize($tenant);
    $tenant->setSetting('layout_vehicles', $layout);
    showVehicle(['name' => 'Layout List Car']);
    tenancy()->end();

    $this->get(tenant_url($sub, '/vehicles'))
        ->assertOk()
        ->assertSee('Layout List Car');
})->with(['card-block', 'two-column', 'f-shape']);
