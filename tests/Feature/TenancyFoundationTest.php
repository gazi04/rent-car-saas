<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\ScopedItem;

beforeEach(function () {
    // Throwaway tenant-scoped table used to prove isolation before real
    // business models (Vehicle, Booking, …) exist. Rolled back by RefreshDatabase.
    Schema::create('scoped_items', function (Blueprint $table) {
        $table->id();
        $table->string('tenant_id');
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    // Ensure no tenant context leaks between tests.
    tenancy()->end();
});

function makeTenant(string $domain, string $name = 'Acme Rentals'): Tenant
{
    $tenant = Tenant::create([
        'name' => $name,
        'email' => str($domain)->before('.').'@example.com',
        'status' => 'active',
        'plan' => 'trial',
    ]);

    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

it('resolves the tenant from the subdomain', function () {
    $tenant = makeTenant('ardi.localhost');

    // /_tenancy-check removed in Step 6 (public booking site owns the tenant routes).
    // The public listing serves as the smoke route for tenant resolution.
    $this->get('http://ardi.localhost/')
        ->assertOk()
        ->assertSee($tenant->name);
});

it('does not initialize tenancy on central domains', function () {
    expect(tenancy()->initialized)->toBeFalse();

    // The tenant-only endpoint must be unreachable from a central domain.
    $this->get('http://localhost/_tenancy-check')->assertNotFound();

    expect(tenancy()->initialized)->toBeFalse();
});

it('returns 404 for an unknown subdomain', function () {
    $this->get('http://does-not-exist.localhost/_tenancy-check')->assertNotFound();
});

it('scopes queries to the current tenant', function () {
    $tenantA = makeTenant('a.localhost', 'Tenant A');
    $tenantB = makeTenant('b.localhost', 'Tenant B');

    tenancy()->initialize($tenantA);
    ScopedItem::create(['name' => 'A car']);

    tenancy()->initialize($tenantB);
    ScopedItem::create(['name' => 'B car']);

    // In tenant B's context, only B's row is visible.
    expect(ScopedItem::count())->toBe(1)
        ->and(ScopedItem::first()->name)->toBe('B car');

    // Switch back to A — only A's row is visible.
    tenancy()->initialize($tenantA);
    expect(ScopedItem::count())->toBe(1)
        ->and(ScopedItem::first()->name)->toBe('A car');
});

it('auto-fills tenant_id when creating a scoped model', function () {
    $tenant = makeTenant('c.localhost', 'Tenant C');

    tenancy()->initialize($tenant);
    $item = ScopedItem::create(['name' => 'No tenant_id passed']);

    expect($item->tenant_id)->toBe($tenant->id);
});
