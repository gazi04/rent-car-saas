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
        'description' => ['en' => 'Great highway cruiser.', 'sq' => 'Kryqëzues i shkëlqyer autostrade.'],
        'custom_fields' => [['label' => 'Color', 'value' => 'Black']],
    ]);
    tenancy()->end();

    // Storefront defaults to Albanian (branding.defaults.default_locale), so the
    // sq description is shown without a language switch.
    $this->get(tenant_url('showok', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('BMW 320d')
        ->assertSee('Kryqëzues i shkëlqyer autostrade.')
        ->assertSee('Color')
        ->assertSee('Black')
        ->assertSee('€50.00')
        ->assertSee('€300.00')
        ->assertSee('€100.00')
        ->assertSee($vehicle->category->getLabel())
        ->assertSee($vehicle->transmission->getLabel());
});

it('shows the vehicle description in the visitor language', function () {
    $tenant = showTenant('showlocale');

    tenancy()->initialize($tenant);
    $vehicle = showVehicle([
        'description' => ['en' => 'Great highway cruiser.', 'sq' => 'Kryqëzues i shkëlqyer autostrade.'],
    ]);
    tenancy()->end();

    $url = tenant_url('showlocale', "/vehicles/{$vehicle->id}");

    // English visitor sees the en text; Albanian visitor sees the sq text.
    $this->withSession(['locale' => 'en'])->get($url)
        ->assertSee('Great highway cruiser.')
        ->assertDontSee('Kryqëzues i shkëlqyer autostrade.');

    $this->withSession(['locale' => 'sq'])->get($url)
        ->assertSee('Kryqëzues i shkëlqyer autostrade.')
        ->assertDontSee('Great highway cruiser.');
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

it('renders a vehicle under maintenance without a booking CTA', function () {
    $tenant = showTenant('showmaint');

    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->underMaintenance()->create();
    tenancy()->end();

    // This 404'd until the stock alert (#3): the page has to exist for the
    // "notify me when it is back" panel to have somewhere to live. Booking is
    // still refused — see the booking-page guard below.
    $this->get(tenant_url('showmaint', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee(__('booking.vehicle_unavailable_notice'));
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

/*
 * The three vehicle-detail and three listing layouts became one shell each, so
 * the per-variant datasets are gone. These assert the surviving shells render.
 */
it('renders the vehicle details shell with its rates and specs', function () {
    $tenant = showTenant('showshell');

    tenancy()->initialize($tenant);
    $vehicle = showVehicle(['name' => 'Layout Show Car']);
    tenancy()->end();

    $this->get(tenant_url('showshell', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('Layout Show Car')
        ->assertSee(__('booking.rates_heading'))
        ->assertSee(__('booking.book_now'));
});

it('renders the vehicle listing shell with its filters and cards', function () {
    $tenant = showTenant('listshell');

    tenancy()->initialize($tenant);
    showVehicle(['name' => 'Layout List Car']);
    tenancy()->end();

    $this->get(tenant_url('listshell', '/vehicles'))
        ->assertOk()
        ->assertSee('Layout List Car')
        ->assertSee(__('booking.browse_fleet'))
        ->assertSee(__('booking.filters_search'));
});

it('renders the photo gallery for a vehicle with several photos', function () {
    $tenant = showTenant('showgallery');

    tenancy()->initialize($tenant);
    fakeTenantDisks();
    $vehicle = showVehicle(['name' => 'Gallery Car']);

    // Plurality lives *within* one vehicle here: getMedia() returns three rows in
    // one hydration, which arms all three, and the gallery then asks each for two
    // conversion URLs. The Vehicle itself is route-model-bound — a single row that
    // is never armed — so this pins the media side, not the vehicle side.
    $photos = attachVehiclePhotos($vehicle, 3);

    tenancy()->end();

    $response = $this->get(tenant_url('showgallery', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('Gallery Car')
        ->assertSee('/conversions/', escape: false);

    foreach ($photos as $photo) {
        $response->assertSee("/vehicle_photos/{$photo->id}/", escape: false);
    }
});

it('renders the details page for a vehicle saved with no description at all', function () {
    $tenant = showTenant('shownodesc');

    tenancy()->initialize($tenant);
    // The shape the operator panel actually writes when both description boxes
    // are left empty — keys present, values null. Not the same as a null column,
    // and not a shape any factory produces, which is why this went unnoticed.
    $vehicle = showVehicle(['name' => 'Undescribed Car', 'description' => ['en' => null, 'sq' => null]]);
    tenancy()->end();

    $this->get(tenant_url('shownodesc', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('Undescribed Car');

    $this->get(tenant_url('shownodesc', "/vehicles/{$vehicle->id}/book"))
        ->assertOk();
});
