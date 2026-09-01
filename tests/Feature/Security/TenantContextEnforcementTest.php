<?php

declare(strict_types=1);

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

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
| TenancyServiceProvider::makePublicComponentsTenantAware().
|
| These tests must issue REAL HTTP requests: Livewire::test() skips the
| persistent-middleware replay entirely (PersistentMiddleware.php:43), so it
| structurally cannot reproduce this.
|
*/

/** The Livewire update endpoint on a given host, e.g. "http://lvh.me/livewire-abc123/update". */
function livewireUpdateUrl(string $host): string
{
    return 'http://'.$host.'/'.ltrim(app(EndpointResolver::class)::updatePath(), '/');
}

/** Scrape the first component snapshot out of a rendered page. */
function snapshotFrom(string $html): array
{
    expect($html)->toContain('wire:snapshot');

    preg_match('/wire:snapshot="([^"]*)"/', $html, $matches);

    return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Replay a captured snapshot against an arbitrary host's update endpoint.
 *
 * tenancy()->end() first is load-bearing: feature-test requests run in-process,
 * so the tenancy initialized by the preceding GET would still be live and would
 * scope the replay's queries — hiding the very leak under test. A real
 * deployment starts each request with no tenant resolved.
 */
function replaySnapshot(string $host, array $snapshot, array $updates = []): TestResponse
{
    tenancy()->end();

    return test()->withHeaders(['X-Livewire' => '1'])->postJson(livewireUpdateUrl($host), [
        'components' => [[
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updates' => $updates,
            'calls' => [],
        ]],
    ]);
}

function publicVehicleFor(Tenant $tenant, string $name): Vehicle
{
    tenancy()->initialize($tenant);

    $vehicle = Vehicle::factory()->create([
        'name' => $name,
        'is_public' => true,
        'status' => VehicleStatus::Available,
    ]);

    tenancy()->end();

    return $vehicle;
}

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
    expect($csp)->toContain("script-src 'self' 'unsafe-eval'")
        ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'");
})->group('security');
