<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Tenant context enforcement on the shared Livewire update route
|--------------------------------------------------------------------------
|
| Livewire's update endpoint is ONE global route with no domain constraint, and
| stancl's UniversalRoutes feature makes tenant identification skip (rather than
| 404) on central domains. TenantScope::apply() no-ops when tenancy is not
| initialized, so a component re-rendered on a central host queries with NO
| tenant filter at all.
|
| Livewire re-applies "persistent middleware" on update by rebuilding a fake
| request from the snapshot's `path` memo against the CURRENT host and matching
| it to a live route. Only middleware in Livewire's persistent allowlist runs,
| which is why the tenancy guards have to be registered there — see
| TenancyServiceProvider::makeLivewireUpdatesRespectTenantBoundaries().
|
| These tests must issue REAL HTTP requests: Livewire::test() skips the
| persistent-middleware replay entirely (PersistentMiddleware.php:43), so it
| structurally cannot reproduce this. livewireUpdateUrl()/snapshotFrom()/
| replaySnapshot() live in tests/Pest.php so LivewireModelTamperingTest can
| share them.
|
*/

it('does not leak another tenant vehicles when a storefront snapshot is replayed on the central domain', function () {
    $tenantA = Tenant::factory()->withDomain('leaka')->create();
    $tenantB = Tenant::factory()->withDomain('leakb')->create();

    publicVehicleFor($tenantA, 'Alpha Runabout');
    publicVehicleFor($tenantB, 'Bravo Limousine');

    // A visitor legitimately browses tenant A's storefront and keeps the snapshot.
    $snapshot = snapshotFrom(test()->get(tenant_url('leaka', '/vehicles'))->assertOk()->getContent());

    // Replaying it on the central (marketing) host runs the component with tenancy
    // uninitialized — where the BelongsToTenant global scope applies no filter.
    $response = replaySnapshot((string) config('tenancy.central_domain'), $snapshot);

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('Bravo Limousine')
        ->and($response->getContent())->not->toContain('Alpha Runabout');
})->group('security');

it('refuses an operator panel snapshot replayed on the admin domain', function () {
    $tenantA = Tenant::factory()->withDomain('panela')->create();
    $tenantB = Tenant::factory()->withDomain('panelb')->create();

    publicVehicleFor($tenantB, 'Bravo Limousine');

    $operatorA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'operator',
        'email_verified_at' => now(),
    ]);

    $snapshot = snapshotFrom(
        actingAs($operatorA)->get(tenant_url('panela', '/dashboard'))->assertOk()->getContent()
    );

    tenancy()->end();

    $response = actingAs($operatorA)
        ->withHeaders(['X-Livewire' => '1'])
        ->postJson(livewireUpdateUrl((string) config('tenancy.admin_domain')), [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [],
            ]],
        ]);

    expect($response->getContent())->not->toContain('Bravo Limousine');
})->group('security');

it('does not let X-Forwarded-Host choose which tenant a request resolves as', function () {
    $victim = Tenant::factory()->withDomain('victimco')->create();

    publicVehicleFor($victim, 'Victim Roadster');

    tenancy()->end();

    // Reaching the origin directly (off-LB port, SSRF) and naming the victim's
    // subdomain in X-Forwarded-Host must not resolve that tenant. The real Host
    // is a central domain, so this is an unknown-tenant 404, not the victim's site.
    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('victimco')])
        ->get('http://'.config('tenancy.central_domain').'/vehicles')
        ->assertNotFound();
})->group('security');

it('sends a script-blocking CSP on the storefront', function () {
    Tenant::factory()->withDomain('cspco')->create();

    $csp = test()->get(tenant_url('cspco', '/'))
        ->assertOk()
        ->headers->get('Content-Security-Policy');

    // The shared parent-domain session cookie makes storefront XSS an
    // admin-takeover path, so injected inline script must not be executable.
    expect(cspDirective($csp, 'script-src'))->toBe("'self' 'unsafe-eval'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'");
})->group('security');
