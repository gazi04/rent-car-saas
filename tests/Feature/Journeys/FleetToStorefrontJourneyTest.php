<?php

use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Operator\Resources\Vehicles\Pages\EditVehicle;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

afterEach(fn () => tenancy()->end());

/*
 * Journey: what the operator publishes in the panel is what the customer sees on
 * the storefront.
 *
 * Every other fleet test proves one half. FleetManagementTest creates vehicles
 * through the panel and stops; PublicBookingTest renders the storefront against
 * vehicles it seeded itself with a factory. Neither can catch a write path that
 * produces data the read path chokes on — which is precisely the class of bug
 * that reached production through TenantAwarePathGenerator.
 */

it('publishes a vehicle the operator creates and renames in the panel, photos and all, on the storefront', function () {
    [$tenant, $operator] = journeyTenant('journeyfleet');
    actAsOperator($tenant, $operator);
    fakeTenantDisks();

    // ── Operator: create, with two photos ────────────────────────────────────
    // Two matters. Media hydrated from a multi-row result is the only shape that
    // arms preventLazyLoading(), so a one-photo vehicle would walk this whole
    // journey without ever testing the path generator against DB-read media.
    //
    // The form payload is inlined rather than borrowed from FleetManagementTest's
    // vehicleFormData(): in-file helpers only resolve cross-file by Pest's load
    // order and break under --filter.
    Livewire::test(CreateVehicle::class)
        ->fillForm([
            'name' => 'Journey Golf',
            'category' => 'sedan',
            'year' => 2022,
            'fuel_type' => 'petrol',
            'transmission' => 'manual',
            'seats' => 5,
            'daily_rate' => 45,
            'status' => 'available',
            'is_public' => true,
            'photos' => [
                UploadedFile::fake()->image('front.jpg', 400, 300),
                UploadedFile::fake()->image('rear.jpg', 400, 300),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vehicle = Vehicle::firstWhere('name', 'Journey Golf');

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->getMedia('vehicle_photos'))->toHaveCount(2);

    // ── Operator: the record-bound panel page, over a real request ───────────
    // No other test in the suite HTTP-GETs a {record} panel page, and this one is
    // the exact surface the shipped bug lived on: the media field loads both
    // photos in one query and asks each for a URL, through the full routing +
    // tenancy + panel-auth stack that Livewire::test() bypasses entirely.
    $this->get(tenant_url('journeyfleet', "/dashboard/vehicles/{$vehicle->id}/edit"))
        ->assertOk()
        ->assertSee('Journey Golf');

    // ── Operator: rename and reprice ─────────────────────────────────────────
    Livewire::test(EditVehicle::class, ['record' => $vehicle->getKey()])
        ->fillForm([
            'name' => 'Renamed Golf',
            'daily_rate' => 77,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Saving a form that never mentions `photos` must not drop them: Filament's
    // media field runs deleteAbandonedFiles() on every save. Nothing else in the
    // suite pins this.
    expect($vehicle->fresh()->getMedia('vehicle_photos'))->toHaveCount(2);

    // ── Customer: the storefront reflects all of it ──────────────────────────
    actAsVisitor();

    $this->get(tenant_url('journeyfleet', '/vehicles'))
        ->assertOk()
        ->assertSee('Renamed Golf')
        ->assertDontSee('Journey Golf')
        ->assertSee("/storage/tenants/{$tenant->id}/vehicle_photos/", escape: false);

    $response = $this->get(tenant_url('journeyfleet', "/vehicles/{$vehicle->id}"))
        ->assertOk()
        ->assertSee('Renamed Golf')
        ->assertSee('/conversions/', escape: false);

    foreach ($vehicle->fresh()->getMedia('vehicle_photos') as $photo) {
        $response->assertSee("/vehicle_photos/{$photo->id}/", escape: false);
    }
});
