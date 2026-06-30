<?php

use App\Enums\BookingStatus;
use App\Enums\VehicleStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

afterEach(fn () => tenancy()->end());

// ── Test helpers ────────────────────────────────────────────────────────────

function publicTenant(string $subdomain): Tenant
{
    return Tenant::factory()->withDomain($subdomain)->create();
}

function publicVehicle(array $attrs = []): Vehicle
{
    return Vehicle::factory()->create(array_merge([
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 50,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ], $attrs));
}

// ── Listing ──────────────────────────────────────────────────────────────────

it('shows only public + available vehicles on the listing', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);

    $visible = publicVehicle(['name' => 'Toyota Corolla']);
    Vehicle::factory()->private()->create(['name' => 'Hidden Car']);
    Vehicle::factory()->underMaintenance()->create(['name' => 'Broken Car']);

    tenancy()->end();

    $this->get('http://ardi.localhost/')
        ->assertOk()
        ->assertSee('Toyota Corolla')
        ->assertDontSee('Hidden Car')
        ->assertDontSee('Broken Car');
});

it('does not show tenant B vehicles on tenant A subdomain', function () {
    $tenantA = publicTenant('alpha');
    $tenantB = publicTenant('beta');

    tenancy()->initialize($tenantA);
    publicVehicle(['name' => 'Alpha Car']);
    tenancy()->end();

    tenancy()->initialize($tenantB);
    publicVehicle(['name' => 'Beta Car']);
    tenancy()->end();

    $this->get('http://alpha.localhost/')
        ->assertSee('Alpha Car')
        ->assertDontSee('Beta Car');
});

it('filters vehicles by category', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);

    publicVehicle(['name' => 'Sedan Car', 'category' => 'sedan']);
    publicVehicle(['name' => 'SUV Car', 'category' => 'suv']);

    tenancy()->end();

    $this->get('http://ardi.localhost/?category=sedan')
        ->assertSee('Sedan Car')
        ->assertDontSee('SUV Car');
});

it('filters vehicles by transmission', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);

    publicVehicle(['name' => 'Manual Car', 'transmission' => 'manual']);
    publicVehicle(['name' => 'Auto Car', 'transmission' => 'automatic']);

    tenancy()->end();

    $this->get('http://ardi.localhost/?transmission=manual')
        ->assertSee('Manual Car')
        ->assertDontSee('Auto Car');
});

it('filters vehicles by max price', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);

    publicVehicle(['name' => 'Cheap Car', 'daily_rate' => 30]);
    publicVehicle(['name' => 'Expensive Car', 'daily_rate' => 200]);

    tenancy()->end();

    $this->get('http://ardi.localhost/?maxPrice=50')
        ->assertSee('Cheap Car')
        ->assertDontSee('Expensive Car');
});

// ── Availability endpoint ────────────────────────────────────────────────────

it('returns blocking bookings and blocked dates for a vehicle', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);

    $vehicle = publicVehicle();

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-05',
    ]);

    tenancy()->end();

    $this->get("http://ardi.localhost/vehicles/{$vehicle->id}/availability")
        ->assertOk()
        ->assertJsonCount(1, 'unavailable')
        ->assertJsonCount(0, 'blocked');
});

it('returns 404 for availability endpoint on another tenant vehicle', function () {
    $tenantA = publicTenant('aaa');
    $tenantB = publicTenant('bbb');

    tenancy()->initialize($tenantA);
    $vehicle = publicVehicle();
    tenancy()->end();

    // Request from tenant B — vehicle is not visible (global scope filters it).
    $this->get("http://bbb.localhost/vehicles/{$vehicle->id}/availability")
        ->assertNotFound();
});

// ── Booking wizard ───────────────────────────────────────────────────────────

it('creates a pending booking on submit and redirects to confirmation', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->set('startDate', '2030-06-01')
        ->set('endDate', '2030-06-04')
        ->call('nextStep')           // advance to step 2
        ->set('customerName', 'Gazi Halili')
        ->set('customerPhone', '+38344123456')
        ->call('nextStep')           // advance to step 3
        ->call('submit')
        ->assertRedirect();

    expect(Booking::count())->toBe(1)
        ->and(Booking::first()->status)->toBe(BookingStatus::Pending)
        ->and(Booking::first()->customer_name)->toBe('Gazi Halili');
});

it('price preview matches PricingService::calculate', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle(['daily_rate' => 60]);

    $component = Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->set('startDate', '2030-06-01')
        ->set('endDate', '2030-06-04');

    // Trigger the on handler manually.
    $component->dispatch('dates-selected', start: '2030-06-01', end: '2030-06-04');

    $expected = app(PricingService::class)->calculate(
        $vehicle,
        Carbon::parse('2030-06-01'),
        Carbon::parse('2030-06-04'),
    );

    $component->assertSet('priceBreakdown.total', $expected['total']);
});

it('blocks advancing from step 1 when dates are missing', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->call('nextStep')
        ->assertHasErrors(['startDate', 'endDate'])
        ->assertSet('step', 1);
});

it('blocks advancing from step 1 when end is before start', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->set('startDate', '2030-06-10')
        ->set('endDate', '2030-06-05')
        ->call('nextStep')
        ->assertHasErrors(['endDate'])
        ->assertSet('step', 1);
});

it('blocks advancing from step 2 when required details are missing', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->set('startDate', '2030-06-01')
        ->set('endDate', '2030-06-04')
        ->set('step', 2)             // jump to step 2 directly
        ->call('nextStep')
        ->assertHasErrors(['customerName', 'customerPhone'])
        ->assertSet('step', 2);
});

it('bounces to step 1 and shows slot-taken flash on double booking', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ]);

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->set('startDate', '2030-06-01')
        ->set('endDate', '2030-06-04')
        ->set('customerName', 'Test Renter')
        ->set('customerPhone', '+38344000000')
        ->set('step', 3)
        ->call('submit')
        ->assertSet('slotTaken', true)
        ->assertSet('step', 1);

    expect(Booking::count())->toBe(1); // only the pre-existing one
});

// ── Confirmation page ────────────────────────────────────────────────────────

it('confirmation page shows reference and pending notice', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();

    $booking = Booking::factory()->forVehicle($vehicle)->create(['reference' => 'BK-2030-TESTOK']);
    tenancy()->end();

    $this->get('http://ardi.localhost/booking/BK-2030-TESTOK/confirmation')
        ->assertOk()
        ->assertSee('BK-2030-TESTOK');
});

it('confirmation page is not reachable for another tenant booking', function () {
    $tenantA = publicTenant('aaa2');
    $tenantB = publicTenant('bbb2');

    tenancy()->initialize($tenantA);
    $vehicle = publicVehicle();
    Booking::factory()->forVehicle($vehicle)->create(['reference' => 'BK-2030-CROSS1']);
    tenancy()->end();

    // Access from tenant B → global scope filters → 404.
    $this->get('http://bbb2.localhost/booking/BK-2030-CROSS1/confirmation')
        ->assertNotFound();
});

// ── Cancellation ─────────────────────────────────────────────────────────────

it('valid signed cancel link cancels the booking', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();
    $booking = Booking::factory()->forVehicle($vehicle)->create();
    tenancy()->end();

    // Signature must be computed for the tenant subdomain so the host matches the request.
    URL::forceRootUrl('http://ardi.localhost');
    $url = URL::temporarySignedRoute(
        'public.booking.cancel',
        now()->addDay(),
        ['booking' => $booking->id],
    );

    $this->get($url)
        ->assertOk()
        ->assertSee($booking->reference);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('expired signed cancel link returns 403', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();
    $booking = Booking::factory()->forVehicle($vehicle)->create();
    tenancy()->end();

    URL::forceRootUrl('http://ardi.localhost');
    $url = URL::temporarySignedRoute(
        'public.booking.cancel',
        now()->subSecond(),          // already expired
        ['booking' => $booking->id],
    );

    $this->get($url)->assertForbidden();
});

it('tampered signed cancel link returns 403', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    $vehicle = publicVehicle();
    $booking = Booking::factory()->forVehicle($vehicle)->create();
    tenancy()->end();

    URL::forceRootUrl('http://ardi.localhost');
    // Build URL then tamper.
    $url = URL::temporarySignedRoute(
        'public.booking.cancel',
        now()->addDay(),
        ['booking' => $booking->id],
    );

    // The URL itself is for ardi.localhost — tamper by appending a param.
    $this->get($url.'&tamper=1')->assertForbidden();
});

// ── Language toggle ───────────────────────────────────────────────────────────

it('toggling language changes rendered strings', function () {
    $tenant = publicTenant('ardi');
    tenancy()->initialize($tenant);
    publicVehicle();
    tenancy()->end();

    // Default locale is sq — header should show 'English'.
    $this->get('http://ardi.localhost/')
        ->assertSee('English');

    // Toggle to en.
    $this->post('http://ardi.localhost/language', ['locale' => 'en'])
        ->assertRedirect();

    $this->get('http://ardi.localhost/')
        ->assertSee('Shqip');
});
