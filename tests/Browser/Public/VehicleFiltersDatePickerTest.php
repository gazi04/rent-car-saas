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
 * The listing page's availability filter runs its own flatpickr instance
 * (vehicle-filters.js) with no wire:model — it reports back purely through
 * Livewire.dispatch('listing-start-selected'/'listing-end-selected'). Feature
 * tests can assert the filtering logic those events trigger, but never load
 * the JS that renders the calendar or fires the events in the first place;
 * this is the one thing only a real browser can prove: the widget actually
 * renders and a real click on a real day cell produces the expected value.
 */
it('renders the listing date-range picker and reflects a real click selection', function () {
    $tenant = Tenant::factory()->withDomain('filterpicker')->create();
    tenancy()->initialize($tenant);

    Vehicle::factory()->create([
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 50,
    ]);

    $start = CarbonImmutable::today()->addDays(2);
    $startLabel = $start->format('F j, Y');

    $page = visitAsTenant('filterpicker', '/vehicles');

    $page->assertNoJavaScriptErrors()
        ->click('#listing-start-picker')
        ->click('.flatpickr-calendar.open [aria-label="'.$startLabel.'"]')
        ->assertValue('#listing-start-picker', $start->format('d/m/Y'))
        ->assertNoJavaScriptErrors();
});
