<?php

declare(strict_types=1);

use App\Enums\VehicleStatus;
use App\Exceptions\CustomerNotEligibleException;
use App\Exceptions\PromoCodeInvalidException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Support\PhoneNumber;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| What an anonymous visitor may do to an operator's customer directory
|--------------------------------------------------------------------------
|
| customers is the record an operator uses to recognise a repeat customer, to
| reach them, and to remember that they are blacklisted. Every field the public
| booking wizard submits is unverified — the phone number above all, since it is
| the identity key the directory is matched on.
|
| Three separate guarantees are pinned here, all of which failed before:
|
|  1. A public booking may CREATE a directory record but never rewrite one. It
|     was overwriting name + email on every match, so submitting a booking under
|     a victim's phone number replaced their stored contact details.
|  2. is_blacklisted actually blocks the public path (and deliberately does not
|     block the operator's own manual booking).
|  3. Phone identity is normalized, so "+383 44 123 456" and "+38344123456" are
|     one customer — otherwise a promo code's per_customer_limit is spendable
|     again by re-typing the number with a space in it.
|
| The operator's own createManual() keeps the sync behaviour throughout: an
| authenticated operator typing at the front desk IS the authority on their
| directory. Asserting that half matters as much as the rest — it is what makes
| the split deliberate rather than a blanket freeze.
|
*/

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'weekly_rate' => null,
        'monthly_rate' => null,
        'status' => VehicleStatus::Available,
        'is_public' => true,
    ]);
    $this->service = app(BookingService::class);
});

/** @return array<string, mixed> */
function directoryData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Visitor',
        'customer_phone' => '+38344000000',
        'customer_email' => 'visitor@example.test',
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ], $overrides);
}

it('does not let a public booking rewrite an existing customer name or email', function () {
    $victim = Customer::create([
        'name' => 'Arben Krasniqi',
        'phone' => '+38344000000',
        'email' => 'arben@example.test',
    ]);

    $this->service->create(directoryData($this->vehicle, [
        'customer_name' => 'Attacker',
        'customer_email' => 'attacker@evil.test',
    ]));

    expect($victim->fresh()->name)->toBe('Arben Krasniqi')
        ->and($victim->fresh()->email)->toBe('arben@example.test');
})->group('security');

it('does not let a public booking backfill a blank email on an existing customer', function () {
    // A record with no email is the WEAKER case, not a safe one: supplying the
    // first email address is how an attacker takes ownership of "contact this
    // customer" without overwriting anything.
    $victim = Customer::create(['name' => 'Arben', 'phone' => '+38344000000', 'email' => null]);

    $this->service->create(directoryData($this->vehicle, ['customer_email' => 'attacker@evil.test']));

    expect($victim->fresh()->email)->toBeNull();
})->group('security');

it('still records the submitted contact details on the booking itself', function () {
    Customer::create(['name' => 'Arben', 'phone' => '+38344000000', 'email' => 'arben@example.test']);

    $booking = $this->service->create(directoryData($this->vehicle, [
        'customer_name' => 'Someone Else',
        'customer_email' => 'someone@example.test',
    ]));

    // Refusing to touch the directory must not lose what this visitor typed —
    // the operator still needs to be able to reach whoever made this booking.
    expect($booking->customer_name)->toBe('Someone Else')
        ->and($booking->customer_email)->toBe('someone@example.test');
})->group('security');

it('lets an operator manual booking update the customer record', function () {
    $customer = Customer::create(['name' => 'Old Name', 'phone' => '+38344000000', 'email' => null]);

    $this->service->createManual(directoryData($this->vehicle, [
        'customer_name' => 'Corrected Name',
        'customer_email' => 'corrected@example.test',
    ]));

    expect($customer->fresh()->name)->toBe('Corrected Name')
        ->and($customer->fresh()->email)->toBe('corrected@example.test');
})->group('security');

it('creates the customer record when the phone is new', function () {
    $this->service->create(directoryData($this->vehicle));

    expect(Customer::query()->where('phone', '+38344000000')->first())
        ->not->toBeNull()
        ->name->toBe('Visitor');
})->group('security');

it('blocks a public booking for a blacklisted customer and writes no row', function () {
    Customer::create(['name' => 'Abuser', 'phone' => '+38344000000', 'is_blacklisted' => true]);

    expect(fn () => $this->service->create(directoryData($this->vehicle)))
        ->toThrow(CustomerNotEligibleException::class);

    expect(Booking::query()->count())->toBe(0);
})->group('security');

it('still lets the operator book a blacklisted customer manually', function () {
    Customer::create(['name' => 'Abuser', 'phone' => '+38344000000', 'is_blacklisted' => true]);

    $booking = $this->service->createManual(directoryData($this->vehicle));

    expect($booking->exists)->toBeTrue();
})->group('security');

it('treats punctuation variants of a phone number as one customer', function () {
    $this->service->create(directoryData($this->vehicle, ['customer_phone' => '+38344000000']));

    $second = Vehicle::factory()->create(['daily_rate' => 50, 'status' => VehicleStatus::Available]);
    $this->service->create(directoryData($second, ['customer_phone' => '+383 44 000 000']));

    expect(Customer::query()->count())->toBe(1);
})->group('security');

it('cannot spend a per-customer promo limit twice by re-spacing the phone', function () {
    // The actual L1 exploit, asserted end to end rather than on the helper: the
    // limit is counted through the Customer row, so two rows for one human meant
    // two redemptions.
    PromoCode::create([
        'code' => 'WELCOME',
        'type' => 'fixed',
        'value' => 5,
        'is_active' => true,
        'per_customer_limit' => 1,
    ]);

    $this->service->create(directoryData($this->vehicle, [
        'customer_phone' => '+38344000000',
        'promo_code' => 'WELCOME',
    ]));

    $second = Vehicle::factory()->create(['daily_rate' => 50, 'status' => VehicleStatus::Available]);

    expect(fn () => $this->service->create(directoryData($second, [
        'customer_phone' => '+383 44 000 000',
        'promo_code' => 'WELCOME',
    ])))->toThrow(PromoCodeInvalidException::class);
})->group('security');

it('stores the phone in normalized form', function () {
    $this->service->create(directoryData($this->vehicle, ['customer_phone' => ' (044) 123.456 ']));

    expect(Customer::query()->value('phone'))->toBe('044123456')
        ->and(PhoneNumber::normalize(' (044) 123.456 '))->toBe('044123456');
})->group('security');
