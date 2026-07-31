<?php

use App\Enums\VehicleStatus;
use App\Filament\Operator\Resources\Vehicles\Pages\EditVehicle;
use App\Models\Vehicle;
use Livewire\Livewire;

afterEach(fn () => tenancy()->end());

/*
 * Journey: what the operator hides in the panel really disappears from the
 * storefront — and what they merely flag as unavailable really doesn't.
 *
 * PublicBookingTest pins both behaviours, but against vehicles a factory created
 * already private or already under maintenance. That proves the read side reads
 * the column; it does not prove the panel action writes it.
 */

/**
 * Two public vehicles. The second exists so the listing always hydrates more
 * than one row, which is the only shape that arms preventLazyLoading().
 *
 * @return array{0: Vehicle, 1: Vehicle}
 */
function visibilityFleet(): array
{
    return [
        Vehicle::factory()->create(['name' => 'Staying Golf', 'is_public' => true, 'status' => VehicleStatus::Available]),
        Vehicle::factory()->create(['name' => 'Vanishing Passat', 'is_public' => true, 'status' => VehicleStatus::Available]),
    ];
}

it('removes a vehicle from the storefront the moment the operator unpublishes it', function () {
    [$tenant, $operator] = journeyTenant('journeyhide');
    actAsOperator($tenant, $operator);

    [$staying, $vanishing] = visibilityFleet();

    actAsVisitor();

    $this->get(tenant_url('journeyhide', '/vehicles'))
        ->assertOk()
        ->assertSee('Staying Golf')
        ->assertSee('Vanishing Passat');

    // ── Operator flips one switch ────────────────────────────────────────────
    actAsOperator($tenant, $operator);

    Livewire::test(EditVehicle::class, ['record' => $vanishing->getKey()])
        ->fillForm(['is_public' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    // ── Four separate abort sites, all over real HTTP ────────────────────────
    // Each lives behind its own route and its own guard; none is reachable from a
    // component test, and one flip has to close all four.
    actAsVisitor();

    $this->get(tenant_url('journeyhide', '/vehicles'))
        ->assertOk()
        ->assertSee('Staying Golf')
        ->assertDontSee('Vanishing Passat');

    $this->get(tenant_url('journeyhide', "/vehicles/{$vanishing->id}"))->assertNotFound();
    $this->get(tenant_url('journeyhide', "/vehicles/{$vanishing->id}/book"))->assertNotFound();
    $this->get(tenant_url('journeyhide', "/vehicles/{$vanishing->id}/availability"))->assertNotFound();

    // The untouched vehicle stays fully reachable — the flip was surgical.
    $this->get(tenant_url('journeyhide', "/vehicles/{$staying->id}"))->assertOk();
});

it('keeps an under-maintenance vehicle listed but unbookable after the operator flips its status', function () {
    [$tenant, $operator] = journeyTenant('journeymaint');
    actAsOperator($tenant, $operator);

    [, $grounded] = visibilityFleet();

    Livewire::test(EditVehicle::class, ['record' => $grounded->getKey()])
        ->fillForm(['status' => VehicleStatus::UnderMaintenance->value])
        ->call('save')
        ->assertHasNoFormErrors();

    actAsVisitor();

    // The deliberate asymmetry: an unavailable vehicle stays on the listing and
    // keeps its detail page, because that page is where a visitor asks to be told
    // when it returns (stock alert). Only booking is closed off.
    $this->get(tenant_url('journeymaint', '/vehicles'))
        ->assertOk()
        ->assertSee('Vanishing Passat')
        ->assertSee(__('booking.vehicle_unavailable_badge'));

    $this->get(tenant_url('journeymaint', "/vehicles/{$grounded->id}"))->assertOk();

    $this->get(tenant_url('journeymaint', "/vehicles/{$grounded->id}/book"))->assertNotFound();
    $this->get(tenant_url('journeymaint', "/vehicles/{$grounded->id}/availability"))->assertNotFound();
});
