<?php

use App\Enums\RateType;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\PricingService;
use Carbon\Carbon;

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
    $vehicle = Vehicle::factory()->create([
        'daily_rate' => 40,
        'weekly_rate' => 200,
        'monthly_rate' => 900,
    ]);

    $result = $this->service->calculate(
        $vehicle,
        Carbon::parse('2030-01-01'),
        Carbon::parse('2030-02-05'), // 35 days → 2 months (ceil)
    );

    expect($result['rate_type'])->toBe(RateType::Monthly)
        ->and($result['subtotal'])->toBe(1800.0); // 2 * 900
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
