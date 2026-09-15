<?php

use App\Filament\Resources\Tenants\Tables\TenantsTable;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseMissing;

/*
| The daily tenants:purge-abandoned sweep (redteam 2026-09-11, finding 4).
|
| domains.domain is globally unique and held for as long as the tenant row exists,
| so a dead signup squats its subdomain forever. The sweep deletes only the subset
| where no human judgement is owed — Tenant::autoPurgeable() — and the invariant
| section below pins it as a strict subset of the manual purge rule so the two
| cannot drift apart.
*/

afterEach(fn () => tenancy()->end());

/** Comfortably past config('tenancy.abandoned_after_days'). */
function abandonedAge(): DateTimeInterface
{
    return now()->subDays(config()->integer('tenancy.abandoned_after_days') + 30);
}

/** A self-signup shape: a pending tenant whose owner never verified their email. */
function unverifiedSignup(string $subdomain = 'deadco'): Tenant
{
    $tenant = Tenant::factory()->pending()->withDomain($subdomain)->create([
        'created_at' => abandonedAge(),
    ]);

    User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);

    return $tenant;
}

// ── Purges ───────────────────────────────────────────────────────────────────

it('purges a cancelled signup and frees its subdomain for re-registration', function () {
    $tenant = Tenant::factory()->cancelled()->withDomain('ghostco')->create([
        'created_at' => abandonedAge(),
    ]);

    artisan('tenants:purge-abandoned')->assertSuccessful();

    assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    assertDatabaseMissing('domains', ['tenant_id' => $tenant->id]);

    // The whole point of the finding: the subdomain is claimable again.
    expect(Tenant::factory()->withDomain('ghostco')->create())->toBeInstanceOf(Tenant::class);
});

it('purges a pending signup whose owner never verified their email', function () {
    $tenant = unverifiedSignup();

    artisan('tenants:purge-abandoned')->assertSuccessful();

    assertDatabaseMissing('tenants', ['id' => $tenant->id]);
});

// ── Keeps ────────────────────────────────────────────────────────────────────

it('keeps a pending signup whose owner verified their email', function () {
    // Admin backlog, not abandonment: this operator did everything asked of them
    // and is waiting on approval. Only the manual Purge action may remove it.
    $tenant = Tenant::factory()->pending()->create(['created_at' => abandonedAge()]);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);

    artisan('tenants:purge-abandoned')->assertSuccessful();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('keeps a pending tenant that has no users at all', function () {
    // The admin create-tenant form makes a tenant without a user; users are added
    // afterwards through the relation manager. Never sweep a deliberate creation.
    $tenant = Tenant::factory()->pending()->create(['created_at' => abandonedAge()]);

    artisan('tenants:purge-abandoned')->assertSuccessful();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('keeps an active tenant however old it is', function () {
    $tenant = Tenant::factory()->create(['created_at' => abandonedAge()]);

    artisan('tenants:purge-abandoned')->assertSuccessful();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('purges at the window boundary but not one day inside it', function () {
    $window = config()->integer('tenancy.abandoned_after_days');

    $stale = Tenant::factory()->cancelled()->create(['created_at' => now()->subDays($window + 1)]);
    $edge = Tenant::factory()->cancelled()->create(['created_at' => now()->subDays($window)]);
    $fresh = Tenant::factory()->cancelled()->create(['created_at' => now()->subDays(2)]);

    artisan('tenants:purge-abandoned')->assertSuccessful();

    // <= the cutoff, mirroring TenantsTable::isAbandoned()'s ! created_at->isAfter().
    expect(Tenant::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(Tenant::query()->whereKey($edge->id)->exists())->toBeFalse()
        ->and(Tenant::query()->whereKey($fresh->id)->exists())->toBeTrue();
});

it('keeps an abandoned tenant that ever took a booking', function () {
    $tenant = Tenant::factory()->cancelled()->create(['created_at' => abandonedAge()]);

    tenancy()->initialize($tenant);
    Booking::factory()->create(['vehicle_id' => Vehicle::factory()->create()->id]);
    tenancy()->end();

    artisan('tenants:purge-abandoned')->assertSuccessful();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('keeps an abandoned tenant that uploaded vehicles but never took a booking', function () {
    // "Never operated" is the bar, not "quiet". Zero bookings alone would let the
    // sweep destroy an ex-operator's whole fleet and its photos unattended.
    $tenant = Tenant::factory()->cancelled()->create(['created_at' => abandonedAge()]);

    tenancy()->initialize($tenant);
    Vehicle::factory()->create();
    tenancy()->end();

    artisan('tenants:purge-abandoned')->assertSuccessful();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

// ── Orphaned users (users.tenant_id is nullOnDelete, not cascade) ─────────────

it('deletes the operator account along with the purged tenant', function () {
    $tenant = unverifiedSignup();
    $email = $tenant->users()->sole()->email;

    artisan('tenants:purge-abandoned')->assertSuccessful();

    // Without Tenant's deleting hook the FK sets tenant_id NULL and the row
    // survives, holding its unique email against the person ever signing up again.
    assertDatabaseMissing('users', ['email' => $email]);
});

// ── Audit trail ──────────────────────────────────────────────────────────────

it('records what it deleted before the row disappears', function () {
    $tenant = unverifiedSignup('audited');

    artisan('tenants:purge-abandoned')->assertSuccessful();

    $activity = Activity::query()->where('description', 'auto_purged')->sole();

    expect($activity->properties->get('domains'))->toContain(tenant_domain('audited'))
        ->and($activity->properties->get('status'))->toBe('pending')
        // No admin did this; ActivitiesTable renders a null causer as "System".
        ->and($activity->causer_id)->toBeNull();
});

// ── Invariant: the sweep may only ever delete what an admin could purge by hand ─

it('only sweeps tenants the manual purge action would also offer', function () {
    $shapes = [
        'cancelled' => Tenant::factory()->cancelled()->create(['created_at' => abandonedAge()]),
        'unverified pending' => unverifiedSignup(),
    ];

    $sweepable = Tenant::autoPurgeable()->pluck('id');

    expect($sweepable)->toHaveCount(count($shapes));

    foreach ($shapes as $label => $tenant) {
        expect($sweepable)->toContain($tenant->id)
            ->and(TenantsTable::isAbandoned($tenant))->toBeTrue("{$label} is swept but not manually purgeable");
    }
});
