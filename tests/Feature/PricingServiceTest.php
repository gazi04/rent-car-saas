<?php

use App\Enums\RateType;
use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\PricingService;
use Carbon\Carbon;

covers(PricingService::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->service = app(PricingService::class);
});

afterEach(fn () => tenancy()->end());

it('selects hourly rate for rentals under 24 hours', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 8,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-10 15:00'), // 6 hours
    );

    expect($result['rate_type'])->toBe(RateType::Hourly)
        ->and($result['subtotal'])->toBe(48.0); // 6 * 8
});

it('selects daily rate for multi-day rentals without weekly rate', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10'),
        Carbon::parse('2030-01-13'), // 3 days
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(120.0); // 3 * 40
});

it('selects weekly rate and rounds up partial weeks', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'weekly_rate' => 200,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-11'), // 10 days → 2 weeks (ceil)
    );

    expect($result['rate_type'])->toBe(RateType::Weekly)
        ->and($result['subtotal'])->toBe(400.0); // 2 * 200
});

it('selects monthly rate and rounds up partial months', function () {
    // monthly_rate is deliberately cheap enough to beat weekly (5*200=1000) and
    // daily (35*40=1400) — this test is about the ceil() rounding, not about
    // whether monthly is the cheapest tier (PricingServiceTest's "cheapest
    // wins" cases cover that separately).
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'weekly_rate' => 200,
        'monthly_rate' => 250,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-02-05'), // 35 days → 2 months (ceil)
    );

    expect($result['rate_type'])->toBe(RateType::Monthly)
        ->and($result['subtotal'])->toBe(500.0); // 2 * 250
});

it('falls back to daily when optional rate is null', function () {
    // 10 days but no weekly_rate → daily fallback.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-11'), // 10 days
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(500.0); // 10 * 50
});

it('bills at least one hour for a sub-hour rental when an hourly rate is set', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 8,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-10 09:20'), // 20 minutes
    );

    expect($result['rate_type'])->toBe(RateType::Hourly)
        ->and($result['subtotal'])->toBe(8.0) // 1 hour minimum, not 0
        ->and($result['total'])->toBeGreaterThan(0.0);
});

it('bills at least one day for a sub-day rental when no hourly rate is set', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 60,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-10 09:20'), // 20 minutes
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(60.0) // 1 day minimum, not 0
        ->and($result['total'])->toBeGreaterThan(0.0);
});

it('rounds a partial hour up to a full hour when billing hourly', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 10,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-10 10:15'), // 1h 15m → bills 2 hours
    );

    expect($result['subtotal'])->toBe(20.0); // 2 * 10, not 1 * 10
});

it('switches to daily at exactly 24 hours', function () {
    // 24h is the first duration that is no longer hourly — the tier boundary.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 8,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-11 09:00'), // exactly 24 hours
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(50.0); // 1 * 50, not 24 * 8
});

it('still bills hourly at 23 hours', function () {
    // One hour below the daily threshold — the hourly tier still applies, and
    // hourly_rate is cheap enough to genuinely beat the 1-day minimum (23*1=23
    // vs 1*50=50) so this pins the eligibility boundary, not just a number.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 1,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-11 08:00'), // 23 hours
    );

    expect($result['rate_type'])->toBe(RateType::Hourly)
        ->and($result['subtotal'])->toBe(23.0); // 23 * 1
});

it('counts sixty minutes to the hour', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 50,
        'hourly_rate' => 10,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-10 10:01'), // 61 minutes → 2 hours
    );

    expect($result['subtotal'])->toBe(20.0); // 2 * 10, not 1 * 10
});

it('still bills daily at 6 days', function () {
    // One day below the weekly threshold.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-07'), // 6 days
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(240.0); // 6 * 40
});

it('still bills weekly at 29 days', function () {
    // One day below the monthly threshold.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => 900,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-30'), // 29 days
    );

    expect($result['rate_type'])->toBe(RateType::Weekly)
        ->and($result['subtotal'])->toBe(1000.0); // ceil(29 / 7) = 5 weeks * 200
});

it('rounds a partial month up to a full month', function () {
    // monthly_rate is cheap enough to beat daily (31*40=1240) so this test pins
    // the ceil(31/30)=2 rounding, not just whether monthly gets picked at all.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => 300,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-02-01'), // 31 days → 2 months
    );

    expect($result['subtotal'])->toBe(600.0); // 2 * 300, not 1 * 300
});

it('switches to weekly at exactly 7 days', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-08'), // exactly 7 days
    );

    expect($result['rate_type'])->toBe(RateType::Weekly)
        ->and($result['subtotal'])->toBe(200.0); // 1 week, not 7 * 40
});

it('switches to monthly at exactly 30 days', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => 900,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-31'), // exactly 30 days
    );

    expect($result['rate_type'])->toBe(RateType::Monthly)
        ->and($result['subtotal'])->toBe(900.0); // 1 month
});

it('falls through to weekly for a month-long rental when no monthly rate is set', function () {
    // Both the day count and the rate presence must hold to pick a tier.
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-31'), // 30 days, no monthly rate
    );

    expect($result['rate_type'])->toBe(RateType::Weekly)
        ->and($result['subtotal'])->toBe(1000.0); // ceil(30 / 7) = 5 weeks * 200
});

it('divides whole weeks by exactly seven', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => 200,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-15'), // 14 days → exactly 2 weeks
    );

    expect($result['subtotal'])->toBe(400.0); // 2 * 200, not 3 weeks
});

it('divides whole months by exactly thirty', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => 900,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-03-02'), // 60 days → exactly 2 months
    );

    expect($result['subtotal'])->toBe(1800.0); // 2 * 900, not 3 months
});

it('rounds a partial day up to a full day', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 60,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-12 12:00'), // 2 days 3 hours → bills 3 days
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(180.0); // 3 * 60, not 2 * 60
});

// ─── Cheapest applicable tier (deep-audit finding 03) ────────────────────────
// Reproduces the audit's own probe numbers (daily €40, hourly €5, weekly €200,
// monthly €700) — each case previously charged a more expensive tier for a
// window a cheaper tier would have covered.

it('charges daily, not hourly, for a 23-hour rental when daily is cheaper', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => 5,
        'weekly_rate' => 200,
        'monthly_rate' => 700,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-10 09:00'),
        Carbon::parse('2030-01-11 08:00'), // 23 hours
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(40.0); // not 23 * 5 = 115
});

it('charges daily, not weekly, for an 8-day rental when daily is cheaper', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => 5,
        'weekly_rate' => 200,
        'monthly_rate' => 700,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-09'), // 8 days
    );

    expect($result['rate_type'])->toBe(RateType::Daily)
        ->and($result['subtotal'])->toBe(320.0); // not ceil(8/7)*200 = 400
});

it('charges weekly, not monthly, for a 31-day rental when weekly is cheaper', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'hourly_rate' => 5,
        'weekly_rate' => 200,
        'monthly_rate' => 700,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-02-01'), // 31 days
    );

    expect($result['rate_type'])->toBe(RateType::Weekly)
        ->and($result['subtotal'])->toBe(1000.0); // not ceil(31/30)*700 = 1400
});

it('reports no discount and no deposit when the vehicle has neither', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
        'discount_type' => null,
        'discount_value' => null,
        'deposit' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
    );

    expect($result['discount'])->toBe(0.0)
        ->and($result['total'])->toBe(200.0)
        ->and($result['deposit'])->toBe(0.0);
});

it('stacks a promo code on top of the vehicle discount', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
        'discount_type' => 'fixed',
        'discount_value' => 20,
    ]);

    $promo = PromoCode::factory()->create(['value' => 10]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
        $promo,
    );

    // Promo applies to the post-vehicle-discount amount: 10% of (200 - 20) = 18.
    expect($result['subtotal'])->toBe(200.0)
        ->and($result['discount'])->toBe(38.0) // 20 + 18
        ->and($result['total'])->toBe(162.0);
});

it('caps the combined vehicle and promo discount at the subtotal', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
        'discount_type' => 'fixed',
        'discount_value' => 180,
    ]);

    $promo = PromoCode::factory()->fixed(100)->create();

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
        $promo,
    );

    expect($result['discount'])->toBe(200.0) // 180 + 20 available, capped at subtotal
        ->and($result['total'])->toBe(0.0);
});

it('applies percentage discount and passes deposit through', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'deposit' => 150,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
    );

    expect($result['subtotal'])->toBe(200.0)
        ->and($result['discount'])->toBe(40.0)   // 20% of 200
        ->and($result['total'])->toBe(160.0)
        ->and($result['deposit'])->toBe(150.0);
});

it('applies fixed discount correctly', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'discount_type' => 'fixed',
        'discount_value' => 30,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
    );

    expect($result['discount'])->toBe(30.0)
        ->and($result['total'])->toBe(170.0);
});

it('caps percentage discount at the subtotal', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'discount_type' => 'percentage',
        'discount_value' => 150,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
    );

    expect($result['discount'])->toBe(200.0)
        ->and($result['total'])->toBe(0.0);
});

it('caps fixed discount at the subtotal', function () {
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 100,
        'discount_type' => 'fixed',
        'discount_value' => 300,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-01-03'), // 2 days → 200 subtotal
    );

    expect($result['discount'])->toBe(200.0)
        ->and($result['total'])->toBe(0.0);
});

// ─── rentalDays — the shared length definition ───────────────────────────────

it('counts whole days for a date-only range', function () {
    expect($this->service->rentalDays(Carbon::parse('2030-01-01'), Carbon::parse('2030-01-04')))->toBe(3);
});

it('rounds a part-day up, matching how the rental is charged', function () {
    expect($this->service->rentalDays(Carbon::parse('2030-01-01 10:00'), Carbon::parse('2030-01-04 12:00')))->toBe(4);
});

it('floors a sub-24h rental at one day', function () {
    expect($this->service->rentalDays(Carbon::parse('2030-01-01 10:00'), Carbon::parse('2030-01-01 13:00')))->toBe(1);
});
