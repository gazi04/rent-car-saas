<?php

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Operator\Resources\Customers\Pages\ListCustomers;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * Tenant + operator inside tenant context and the operator panel.
 *
 * @return array{0: Tenant, 1: User}
 */
function customerDirectoryOperator(string $domain): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

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
function directoryBookingData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Arben Krasniqi',
        'customer_phone' => '+38344111222',
        'customer_email' => 'arben@example.com',
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ], $overrides);
}

it('auto-creates and links a customer by phone on a public booking', function () {
    [$tenant] = customerDirectoryOperator('dirauto');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);

    $booking = app(BookingService::class)->create(directoryBookingData($vehicle));

    $customer = Customer::query()->first();

    expect($customer)->not->toBeNull()
        ->and($customer->phone)->toBe('+38344111222')
        ->and($customer->name)->toBe('Arben Krasniqi')
        ->and($customer->tenant_id)->toBe($tenant->id)
        ->and($booking->customer_id)->toBe($customer->id);
});

it('reuses the same customer for a repeat phone and updates name/email', function () {
    customerDirectoryOperator('dirrepeat');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);

    app(BookingService::class)->create(directoryBookingData($vehicle));
    app(BookingService::class)->create(directoryBookingData($vehicle, [
        'customer_name' => 'Arben K. Krasniqi',
        'customer_email' => 'new@example.com',
        'start_date' => '2030-07-01',
        'end_date' => '2030-07-03',
    ]));

    expect(Customer::query()->count())->toBe(1);

    $customer = Customer::query()->first();
    expect($customer->name)->toBe('Arben K. Krasniqi')
        ->and($customer->email)->toBe('new@example.com')
        ->and($customer->bookings()->count())->toBe(2);
});

it('also links a customer on a manual/walk-in booking', function () {
    customerDirectoryOperator('dirmanual');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);

    $booking = app(BookingService::class)->createManual(directoryBookingData($vehicle));

    expect($booking->customer_id)->not->toBeNull()
        ->and(Customer::query()->whereKey($booking->customer_id)->exists())->toBeTrue();
});

it('totals spend from active and completed bookings only', function () {
    customerDirectoryOperator('dirspend');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $customer = Customer::factory()->create();

    Booking::factory()->forVehicle($vehicle)->completed()->create(['customer_id' => $customer->id, 'total' => 100]);
    Booking::factory()->forVehicle($vehicle)->active()->create(['customer_id' => $customer->id, 'total' => 40]);
    Booking::factory()->forVehicle($vehicle)->confirmed()->create(['customer_id' => $customer->id, 'total' => 999]);
    Booking::factory()->forVehicle($vehicle)->cancelled()->create(['customer_id' => $customer->id, 'total' => 999]);

    expect($customer->totalSpend())->toBe(140.0);
});

it('lets a blacklisted customer still book (informational only)', function () {
    customerDirectoryOperator('dirblack');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    Customer::factory()->blacklisted()->create(['phone' => '+38344111222']);

    $booking = app(BookingService::class)->create(directoryBookingData($vehicle));

    expect($booking->customer->is_blacklisted)->toBeTrue()
        ->and($booking->status)->toBe(BookingStatus::Pending);
});

it('scopes the directory list to the current tenant', function () {
    customerDirectoryOperator('dirtenantone');
    $mine = Customer::factory()->create(['name' => 'Mine Customer']);
    tenancy()->end();

    customerDirectoryOperator('dirtenanttwo');
    $theirs = Customer::factory()->create(['name' => 'Their Customer']);

    Livewire::test(ListCustomers::class)
        ->assertSee($theirs->name)
        ->assertDontSee($mine->name);
});

it('creates a customer manually and rejects a duplicate phone', function () {
    customerDirectoryOperator('dircreate');

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Walk In', 'phone' => '+38344999000'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::query()->where('phone', '+38344999000')->count())->toBe(1);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Dup', 'phone' => '+38344999000'])
        ->call('create')
        ->assertHasFormErrors(['phone']);
});
