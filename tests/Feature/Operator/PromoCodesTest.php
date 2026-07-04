<?php

use App\Enums\PlanFeature;
use App\Exceptions\PromoCodeInvalidException;
use App\Filament\Operator\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Filament\Operator\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Filament\Operator\Resources\PromoCodes\PromoCodeResource;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function promoTenant(string $domain, array $planFeatures = [], ?string $planSlug = null): array
{
    if ($planSlug !== null) {
        Plan::factory()->create(['slug' => $planSlug, 'features' => $planFeatures]);
    }

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => $planSlug ?? 'ghost-plan']);

    $owner = new User;
    $owner->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Owner',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($owner);

    return [$tenant, $owner];
}

/** @return array<string, mixed> */
function promoBookingData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Arben',
        'customer_phone' => '+38344111222',
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ], $overrides);
}

it('computes discountFor and validity', function () {
    promoTenant('promounit');

    $pct = PromoCode::factory()->make(['type' => 'percentage', 'value' => 10]);
    $fixed = PromoCode::factory()->make(['type' => 'fixed', 'value' => 15]);

    expect($pct->discountFor(100))->toBe(10.0)
        ->and($fixed->discountFor(8))->toBe(8.0) // capped at amount
        ->and(PromoCode::factory()->inactive()->make()->isCurrentlyValid())->toBeFalse()
        ->and(PromoCode::factory()->expired()->make()->isCurrentlyValid())->toBeFalse()
        ->and(PromoCode::factory()->make(['max_uses' => 2, 'uses_count' => 2])->isCurrentlyValid())->toBeFalse();
});

it('lowers the total in PricingService', function () {
    promoTenant('promoprice');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $promo = PromoCode::factory()->create(['type' => 'percentage', 'value' => 10]);

    $start = Carbon::parse('2030-06-01');
    $end = Carbon::parse('2030-06-04'); // 3 days * 50 = 150

    $plain = app(PricingService::class)->calculate($vehicle, $start, $end);
    $discounted = app(PricingService::class)->calculate($vehicle, $start, $end, $promo);

    expect($plain['total'])->toBe(150.0)
        ->and($discounted['discount'])->toBe(15.0)
        ->and($discounted['total'])->toBe(135.0);
});

it('applies and records a valid promo on a booking', function () {
    promoTenant('promobook');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $promo = PromoCode::factory()->create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10]);

    $booking = app(BookingService::class)->create(promoBookingData($vehicle, ['promo_code' => 'save10']));

    expect((float) $booking->discount_amount)->toBe(15.0)
        ->and($booking->promo_code_id)->toBe($promo->id)
        ->and($promo->fresh()->uses_count)->toBe(1);
});

it('rejects an invalid or expired code and creates no booking', function () {
    promoTenant('promobad');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    PromoCode::factory()->expired()->create(['code' => 'OLD']);

    expect(fn () => app(BookingService::class)->create(promoBookingData($vehicle, ['promo_code' => 'OLD'])))
        ->toThrow(PromoCodeInvalidException::class);

    expect(Booking::query()->count())->toBe(0);
});

it('ignores the code when the plan disables promo codes', function () {
    promoTenant('promooff', [PlanFeature::PromoCodes->value => false], 'nopromo');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    PromoCode::factory()->create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10]);

    $booking = app(BookingService::class)->create(promoBookingData($vehicle, ['promo_code' => 'SAVE10']));

    expect((float) $booking->discount_amount)->toBe(0.0)
        ->and($booking->promo_code_id)->toBeNull();
});

it('enforces the per-customer limit', function () {
    promoTenant('promopercust');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    PromoCode::factory()->create(['code' => 'ONCE', 'per_customer_limit' => 1]);

    app(BookingService::class)->create(promoBookingData($vehicle, [
        'promo_code' => 'ONCE',
        'start_date' => '2030-06-01', 'end_date' => '2030-06-03',
    ]));

    expect(fn () => app(BookingService::class)->create(promoBookingData($vehicle, [
        'promo_code' => 'ONCE',
        'start_date' => '2030-07-01', 'end_date' => '2030-07-03', // same phone, different dates
    ])))->toThrow(PromoCodeInvalidException::class);
});

it('previews the promo discount on the public booking component', function () {
    [$tenant] = promoTenant('promopublic');
    tenancy()->end();

    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50, 'is_public' => true]);
    PromoCode::factory()->create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10]);

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->dispatch('dates-selected', start: '2030-06-01 10:00', end: '2030-06-04 10:00')
        ->set('promoCode', 'SAVE10')
        ->call('applyPromo')
        ->assertSet('priceBreakdown.total', 135.0)
        ->assertSet('promoNotice', __('booking.promo_applied'));
});

it('flags an invalid code on the public component', function () {
    [$tenant] = promoTenant('promopublicbad');
    tenancy()->end();

    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50, 'is_public' => true]);

    Livewire::test('pages::public.vehicle-booking', ['vehicle' => $vehicle])
        ->dispatch('dates-selected', start: '2030-06-01 10:00', end: '2030-06-04 10:00')
        ->set('promoCode', 'NOPE')
        ->call('applyPromo')
        ->assertSet('priceBreakdown.total', 150.0)
        ->assertSet('promoError', __('booking.promo_invalid'));
});

it('lets the owner create and list promo codes, tenant-scoped', function () {
    [$tenant] = promoTenant('promores', [PlanFeature::PromoCodes->value => true], 'withpromo');

    Livewire::test(CreatePromoCode::class)
        ->fillForm(['code' => 'summer', 'type' => 'percentage', 'value' => 20, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $promo = PromoCode::query()->first();
    expect($promo->code)->toBe('SUMMER') // uppercased
        ->and($promo->tenant_id)->toBe($tenant->id);

    Livewire::test(ListPromoCodes::class)->assertSee('SUMMER');
});

it('gates the promo resource by owner and plan', function () {
    [$tenant] = promoTenant('promogate', [PlanFeature::PromoCodes->value => true], 'gatepromo');
    expect(PromoCodeResource::canAccess())->toBeTrue();

    $staff = User::factory()->staff()->create(['tenant_id' => $tenant->id]);
    actingAs($staff);
    expect(PromoCodeResource::canAccess())->toBeFalse();
});
