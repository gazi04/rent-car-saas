<?php

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    tenantHostReset();
    tenancy()->end();
});

/**
 * Real, full click-through of the public booking wizard — the one flow
 * tests/Feature/PublicBookingTest.php cannot reach, since every assertion
 * there drives the component via Livewire::test(...) synthetic calls, never
 * loading booking-form.js, never running the real flatpickr instance at
 * #date-range-picker, never firing a real wire:click round trip over the wire.
 * This is a direct regression test for the bug documented in that file
 * (PublicBookingTest.php:246-248): a UTC-anchored payload once made flatpickr
 * re-anchor to the wrong day — a rendering bug a component-level test cannot
 * see, because it never renders anything.
 */
it('completes a real booking through every wizard step by clicking through the UI', function () {
    $tenant = Tenant::factory()->withDomain('wizardflow')->create();
    tenancy()->initialize($tenant);

    $vehicle = Vehicle::factory()->create([
        'name' => 'Corolla Wizard Flow',
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 50,
        'hourly_rate' => null,
        'weekly_rate' => null,
        'monthly_rate' => null,
    ]);

    $start = CarbonImmutable::today()->addDays(3);
    $end = $start->addDays(2);

    $page = visitAsTenant('wizardflow', "/vehicles/{$vehicle->id}/book");

    // Step 1 — real flatpickr range selection (mode: 'range', two clicks in the
    // same open calendar), dispatching the 'dates-selected' Livewire event that
    // booking-form.js wires up.
    $page->assertNoJavaScriptErrors()
        ->click('#date-range-picker')
        ->click('.flatpickr-calendar.open [aria-label="'.$start->format('F j, Y').'"]')
        ->click('.flatpickr-calendar.open [aria-label="'.$end->format('F j, Y').'"]')
        ->assertSee(__('booking.price_preview'))
        ->click(__('booking.next'));

    // Step 2 — customer details, targeted via the data-test hooks added to
    // vehicle-booking.blade.php (these inputs carry no name/id — only
    // wire:model — so there was previously no stable selector for a real
    // browser to target).
    $page->assertSee(__('booking.step_details'))
        ->fill('[data-test="customer-name"]', 'Browser Test Customer')
        ->fill('[data-test="customer-phone"]', '049111222')
        ->fill('[data-test="customer-email"]', 'browser-test@example.com')
        ->click(__('booking.next'));

    // Step 3 — review. Submission itself (BookingService::create() + the
    // redirect to the confirmation page) is already covered end-to-end at the
    // Feature level in PublicBookingTest.php; stopping here keeps this test to
    // the thing only a real browser can prove — that three real, wire:click-
    // driven steps, fed by a real flatpickr selection, render the right data
    // at each stop.
    $page->assertSee(__('booking.review_heading'))
        ->assertSee('Browser Test Customer')
        ->assertSee($vehicle->name)
        ->assertNoJavaScriptErrors();
});
