<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Tenant;
use App\Support\BookingReference;
use Illuminate\Support\Facades\RateLimiter;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Booking-reference enumeration
|--------------------------------------------------------------------------
|
| /booking/{reference}/confirmation is an unauthenticated GET returning another
| party's booking on a correct reference: the vehicle, the rental dates, the
| total, and whether an email is on file. The page renders no contact details, so
| this is a rental-history leak rather than a full PII leak — still another
| customer's business, and still reached with nothing but a guess. The 2026-09-03 review
| sized its 30/min throttle against a reference space of 62^6 and wrote that "the
| reference space is what protects them".
|
| It is not. The generator was Str::upper(Str::random(6)) — a 62-symbol draw
| folded onto 36 non-uniformly — which is ~30.7 bits, not ~35.7. New references
| are 40 bits, but every reference issued before that is already in a customer's
| inbox and cannot be reissued, so the control that actually protects them is
| ThrottleBookingReferenceMisses: a budget spent only on FAILED lookups, which a
| real customer never incurs.
|
*/

beforeEach(function () {
    RateLimiter::clear('booking-reference-misses:127.0.0.1');
});

function bookingFor(Tenant $tenant, string $reference): Booking
{
    $vehicle = publicVehicleFor($tenant, 'Enumeration Roadster');

    tenancy()->initialize($tenant);

    $booking = Booking::factory()->forVehicle($vehicle)->create([
        'reference' => $reference,
        'customer_name' => 'Arben Krasniqi',
    ]);

    tenancy()->end();

    return $booking;
}

it('stops an enumerator once the miss budget is spent', function () {
    $tenant = Tenant::factory()->withDomain('enumco')->create();
    bookingFor($tenant, 'BK-2030-REALREF1');

    // Ten wrong guesses are allowed; each one 404s exactly as a wrong guess does.
    foreach (range(1, 10) as $i) {
        test()->get(tenant_url('enumco', '/booking/BK-2030-GUESS'.$i.'/confirmation'))
            ->assertNotFound();
    }

    // The eleventh is refused. Crucially it is refused as a 404, not a 429: an
    // attacker cannot tell a spent budget from another wrong guess, so they
    // cannot pace around it and keep burning effort that can no longer pay out.
    test()->get(tenant_url('enumco', '/booking/BK-2030-GUESS11/confirmation'))
        ->assertNotFound();

    // The budget is spent, so even the CORRECT reference is now refused. That is
    // the property that makes the control worth having: guessing does not become
    // cheap again just because the attacker eventually guesses right.
    test()->get(tenant_url('enumco', '/booking/BK-2030-REALREF1/confirmation'))
        ->assertNotFound();
})->group('security');

it('never charges a customer refreshing their own confirmation page', function () {
    $tenant = Tenant::factory()->withDomain('refreshco')->create();
    bookingFor($tenant, 'BK-2030-MYBOOKIN');

    // Far more than the miss budget. A correct reference never 404s, so it never
    // touches the budget — which is what lets the budget be as tight as 10/hour
    // without ever being felt by legitimate use.
    foreach (range(1, 25) as $ignored) {
        test()->get(tenant_url('refreshco', '/booking/BK-2030-MYBOOKIN/confirmation'))
            ->assertOk()
            ->assertSee('Enumeration Roadster');
    }
})->group('security');

it('does not let a spent budget on one tenant leak into another', function () {
    $alpha = Tenant::factory()->withDomain('budgeta')->create();
    $bravo = Tenant::factory()->withDomain('budgetb')->create();

    bookingFor($bravo, 'BK-2030-BRAVOREF');

    foreach (range(1, 11) as $i) {
        test()->get(tenant_url('budgeta', '/booking/BK-2030-MISS'.$i.'/confirmation'))
            ->assertNotFound();
    }

    // The budget is per IP, deliberately — an enumerator walking every tenant is
    // the same attacker, and letting them reset by switching subdomain would make
    // the control trivially bypassable. Documented here so the shared budget reads
    // as the decision it is rather than an oversight.
    test()->get(tenant_url('budgetb', '/booking/BK-2030-BRAVOREF/confirmation'))
        ->assertNotFound();
})->group('security');

it('issues references wide enough that guessing is not the cheaper attack', function () {
    // Pins the generator's width at the routing boundary, not just in isolation:
    // a reference minted today must still bind on the confirmation route.
    $tenant = Tenant::factory()->withDomain('widthco')->create();

    $reference = BookingReference::generate();
    bookingFor($tenant, $reference);

    expect(strlen($reference))->toBe(16);

    test()->get(tenant_url('widthco', '/booking/'.$reference.'/confirmation'))
        ->assertOk()
        ->assertSee('Enumeration Roadster');
})->group('security');
