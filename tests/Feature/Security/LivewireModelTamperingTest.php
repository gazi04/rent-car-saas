<?php

declare(strict_types=1);

use App\Enums\VehicleStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Model-property tampering on the Livewire update route
|--------------------------------------------------------------------------
|
| Four storefront components bind an Eloquent model as a public property
| (vehicle-show, vehicle-booking, booking-confirmation, booking-review). Their
| mount() eligibility guards — abort_unless($vehicle->is_public, 404), the
| review's Completed check — run on mount only. Every later /livewire/update
| carries that model as a key the browser holds, so what stops a visitor
| re-pointing it at another record is the single most load-bearing assumption
| on the public site.
|
| Two independent framework mechanisms hold it shut, and these tests pin both
| so a Livewire upgrade cannot retire either one silently:
|
|   1. The snapshot is authenticated. Checksum::verify() HMACs the whole
|      snapshot minus the checksum, and the model's {class, key} meta is inside
|      it, so an edited key is a corrupt payload.
|   2. The updates payload cannot choose a model. HandleSynths::hydrateForUpdate()
|      is an explicit trust boundary (its own comment says so): the synth and its
|      meta come from the verified snapshot, never from the update, and
|      ModelSynth::hydrate() resolves the record from $meta['key'], ignoring the
|      client value entirely.
|
| Worth knowing what does NOT protect this: ModelSynth restores through
| Model::newQueryForRestoration(), which is newQueryWithoutScopes(). The
| BelongsToTenant global scope is absent from that query. If the checksum ever
| stopped covering the key, the reach would be every tenant, not just this one.
|
*/

function tamperTenant(string $subdomain): Tenant
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

    tenancy()->initialize($tenant);

    return $tenant;
}

it('refuses a storefront snapshot whose model key has been re-pointed', function () {
    tamperTenant('tamperkey');

    $shown = Vehicle::factory()->create([
        'name' => 'Public Runabout',
        'is_public' => true,
        'status' => VehicleStatus::Available,
    ]);

    $hidden = Vehicle::factory()->private()->create(['name' => 'Hidden Limousine']);

    tenancy()->end();

    $snapshot = snapshotFrom(
        test()->get(tenant_url('tamperkey', '/vehicles/'.$shown->id))->assertOk()->getContent()
    );

    // The off-storefront vehicle has no page of its own — mount() 404s on it —
    // so re-keying a snapshot obtained legitimately is the only way in.
    expect($snapshot['data']['vehicle'][1]['key'])->toBe($shown->id);

    $snapshot['data']['vehicle'][1]['key'] = $hidden->id;

    // Assert on the exception, not the response body: with the debug handler on,
    // the rendered error page echoes this test's own source, so a body scanned
    // for 'Hidden Limousine' matches the string in the line above and passes for
    // entirely the wrong reason.
    $this->withoutExceptionHandling();

    expect(fn () => replaySnapshot(tenant_domain('tamperkey'), $snapshot))
        ->toThrow(CorruptComponentPayloadException::class);
})->group('security');

it('refuses an updates payload that tries to re-point a bound model over the wire', function () {
    tamperTenant('tamperupd');

    $shown = Vehicle::factory()->create([
        'name' => 'Public Runabout',
        'is_public' => true,
        'status' => VehicleStatus::Available,
    ]);

    $hidden = Vehicle::factory()->private()->create(['name' => 'Hidden Limousine']);

    tenancy()->end();

    $snapshot = snapshotFrom(
        test()->get(tenant_url('tamperupd', '/vehicles/'.$shown->id))->assertOk()->getContent()
    );

    // Untouched snapshot — the checksum still verifies. The swap is attempted
    // through the one channel the browser is allowed to write to, over real HTTP
    // rather than Livewire::test(), so the whole middleware stack is in play.
    $this->withoutExceptionHandling();

    expect(fn () => replaySnapshot(tenant_domain('tamperupd'), $snapshot, ['vehicle' => $hidden->id]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->group('security');

it('cannot re-point a model property even when a component forgets to lock it', function () {
    tamperTenant('tamperbare');

    $shown = Vehicle::factory()->create(['name' => 'Public Runabout', 'is_public' => true]);
    $hidden = Vehicle::factory()->private()->create(['name' => 'Hidden Limousine']);

    // #[Locked] is deliberately absent here. This pins the guarantee underneath
    // it — HandleSynths::hydrateForUpdate() takes the model's meta only from the
    // checksum-verified snapshot, so ModelSynth resolves $meta['key'] and throws
    // the client's value away. That is what protects the next storefront
    // component written by someone who does not know about the attribute, and it
    // is invisible in our own code, so it is asserted rather than assumed.
    $component = Livewire::test(UnlockedModelPropProbe::class, ['vehicle' => $shown])
        ->set('vehicle', $hidden->id);

    expect($component->get('vehicle')->id)->toBe($shown->id);

    $component->assertSee('Public Runabout')->assertDontSee('Hidden Limousine');
})->group('security');

it('does not let a review be written against a booking the visitor swapped in', function () {
    tamperTenant('tamperrev');

    $vehicle = Vehicle::factory()->create(['is_public' => true, 'status' => VehicleStatus::Available]);

    $mine = Booking::factory()->completed()->forVehicle($vehicle)->create(['customer_name' => 'Attacker']);
    $theirs = Booking::factory()->completed()->forVehicle($vehicle)->create(['customer_name' => 'Real Customer']);

    // Before #[Locked] this was a silent no-op: the swap was discarded, submit()
    // ran, and the review landed on $mine — safe, but indistinguishable from a
    // component that had simply ignored the attempt for some other reason. The
    // lock is what makes the refusal an assertable event.
    expect(fn () => Livewire::test('pages::public.booking-review', ['booking' => $mine])
        ->set('booking', $theirs->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(Booking::query()->find($theirs->id)->review()->exists())->toBeFalse()
        ->and(Booking::query()->find($mine->id)->review()->exists())->toBeFalse();
})->group('security');

it('refuses to re-point every model-bound storefront component', function (string $component) {
    tamperTenant('tamperall');

    $vehicle = Vehicle::factory()->create([
        'name' => 'Public Runabout',
        'is_public' => true,
        'status' => VehicleStatus::Available,
    ]);

    $booking = Booking::factory()->completed()->forVehicle($vehicle)->create();

    [$property, $mounted, $swapTo] = str_contains($component, 'vehicle')
        ? ['vehicle', $vehicle, Vehicle::factory()->private()->create()->id]
        : ['booking', $booking, Booking::factory()->completed()->forVehicle($vehicle)->create()->id];

    expect(fn () => Livewire::test($component, [$property => $mounted])->set($property, $swapTo))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'pages::public.vehicle-show',
    'pages::public.vehicle-booking',
    'pages::public.booking-confirmation',
    'pages::public.booking-review',
])->group('security');

/**
 * A component with an unlocked Eloquent property, existing only to assert what
 * Livewire guarantees without #[Locked]. It lives here rather than under
 * resources/views so the arch rule in tests/Arch/LivewireModelPropertiesTest.php
 * — which requires that attribute on every real component — does not flag it.
 */
class UnlockedModelPropProbe extends Component
{
    public Vehicle $vehicle;

    public function render(): string
    {
        return '<div>{{ $vehicle->name }}</div>';
    }
}
