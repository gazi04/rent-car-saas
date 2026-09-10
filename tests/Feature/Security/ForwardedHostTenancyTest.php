<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Load-balancer Host handling
|--------------------------------------------------------------------------
|
| Tenants resolve from $request->getHost(), and routes/tenant.php carries no
| Route::domain() constraint. That leaves one deploy-time unknown with two very
| different failure modes:
|
|   1. Security — the load balancer passes a client-supplied X-Forwarded-Host
|      through untouched. Harmless as configured (the header is not in
|      trustProxies()), and pinned by TenantContextEnforcementTest's
|      "does not let X-Forwarded-Host choose which tenant a request resolves as".
|
|   2. Availability — the load balancer rewrites Host to its own hostname and
|      passes the real one only in X-Forwarded-Host. Then nothing resolves and
|      EVERY tenant subdomain 404s, indistinguishable from an unknown subdomain.
|
| ForwardedHostTenancyGuard detects (2) always, and recovers from it behind a
| default-off flag. These tests simulate the load balancer by requesting a host
| that resolves to nothing while sending the real one in the header — which works
| precisely BECAUSE HEADER_X_FORWARDED_HOST is absent from the trusted set, so
| getHost() returns the request's own host and the guard sees the production
| shape. Adding that header to trustProxies() would break these tests, which is
| the point: it is the reverted 2026-09-03 vulnerability.
|
| tenancy()->end() before each request for the reason replaySnapshot() documents
| in tests/Pest.php — feature requests run in-process, and leftover tenancy would
| scope the queries and mask the behaviour under test.
|
*/

/** The hostname a load balancer would substitute: syntactically valid, resolves to no tenant. */
const LB_HOST = 'lb-internal-7f3a.example.net';

/*
|--------------------------------------------------------------------------
| Recovery flag — default OFF
|--------------------------------------------------------------------------
*/

it('ignores a forwarded host by default when the primary host resolves to nothing', function () {
    $tenant = Tenant::factory()->withDomain('fwdoff')->create();
    publicVehicleFor($tenant, 'Default Off Roadster');

    tenancy()->end();

    // Shipping the guard must change nothing until somebody opts in.
    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('fwdoff')])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertNotFound();

    expect($response->getContent())->not->toContain('Default Off Roadster');
})->group('security');

/*
|--------------------------------------------------------------------------
| Recovery flag — ON, and only under the narrow predicate
|--------------------------------------------------------------------------
*/

it('resolves the tenant from a validated forwarded host when recovery is enabled', function () {
    config()->set('security.forwarded_host_recovery', true);

    $tenant = Tenant::factory()->withDomain('fwdon')->create();
    publicVehicleFor($tenant, 'Recovered Roadster');

    tenancy()->end();

    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('fwdon')])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertOk()
        ->assertSee('Recovered Roadster');
})->group('security');

it('never serves tenant content on the central host even with recovery enabled', function () {
    config()->set('security.forwarded_host_recovery', true);

    $victim = Tenant::factory()->withDomain('centralvictim')->create();
    publicVehicleFor($victim, 'Central Victim Roadster');

    tenancy()->end();

    // The flag-armed twin of TenantContextEnforcementTest's
    // "does not let X-Forwarded-Host choose which tenant a request resolves as".
    // Neither may be weakened without the other being looked at.
    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('centralvictim')])
        ->get('http://'.config('tenancy.central_domain').'/vehicles')
        ->assertNotFound();

    expect($response->getContent())->not->toContain('Central Victim Roadster');
})->group('security');

it('never serves tenant content on the admin host even with recovery enabled', function () {
    config()->set('security.forwarded_host_recovery', true);

    $victim = Tenant::factory()->withDomain('adminvictim')->create();
    publicVehicleFor($victim, 'Admin Victim Roadster');

    tenancy()->end();

    // Separate from the central case on purpose: the admin origin is where the
    // parent-scoped session cookie actually hurts. A Super Admin's cookie is sent
    // to every host under the parent domain, so tenant-controlled content rendered
    // on the admin origin is an admin-takeover path, not merely a spoof.
    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('adminvictim')])
        ->get('http://'.config('tenancy.admin_domain').'/vehicles')
        ->assertNotFound();

    expect($response->getContent())->not->toContain('Admin Victim Roadster');
})->group('security');

it('does not let a forwarded host override a host that already resolves to a tenant', function () {
    config()->set('security.forwarded_host_recovery', true);

    $tenantA = Tenant::factory()->withDomain('fwda')->create();
    $tenantB = Tenant::factory()->withDomain('fwdb')->create();

    publicVehicleFor($tenantA, 'Alpha Roadster');
    publicVehicleFor($tenantB, 'Bravo Limousine');

    tenancy()->end();

    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('fwdb')])
        ->get(tenant_url('fwda', '/vehicles'))
        ->assertOk()
        ->assertSee('Alpha Roadster');

    expect($response->getContent())->not->toContain('Bravo Limousine');
})->group('security');

it('rejects a forwarded host that is not an exact domains row', function (string $shape) {
    config()->set('security.forwarded_host_recovery', true);

    $tenant = Tenant::factory()->withDomain('exactonly')->create();
    publicVehicleFor($tenant, 'Exact Only Roadster');

    tenancy()->end();

    // Built here, not in the dataset: dataset closures are resolved during test
    // collection, before the application container exists, so config() — and
    // therefore tenant_domain() — is unavailable there.
    $forwarded = match ($shape) {
        'unrelated' => 'evil.example.com',
        // The real domain with attacker-controlled labels appended.
        'suffix' => tenant_domain('exactonly').'.attacker.example.com',
        // A deeper label under the real domain.
        'prefix' => 'sub.'.tenant_domain('exactonly'),
    };

    $response = test()->withHeaders(['X-Forwarded-Host' => $forwarded])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertNotFound();

    expect($response->getContent())->not->toContain('Exact Only Roadster');
})->with(['unrelated', 'suffix', 'prefix'])->group('security');

it('takes the first entry of a comma separated forwarded host list', function () {
    config()->set('security.forwarded_host_recovery', true);

    $tenant = Tenant::factory()->withDomain('fwdlist')->create();
    publicVehicleFor($tenant, 'List Roadster');

    tenancy()->end();

    // A chain of proxies appends, and the EARLIEST proxy saw the original Host.
    // Symfony takes element [0] when the header is trusted; the guard matches that
    // so the two can never disagree about which element counts.
    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('fwdlist').', '.LB_HOST])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertOk()
        ->assertSee('List Roadster');
})->group('security');

it('does not take a later entry of a comma separated forwarded host list', function () {
    config()->set('security.forwarded_host_recovery', true);

    $tenant = Tenant::factory()->withDomain('fwdlate')->create();
    publicVehicleFor($tenant, 'Late Roadster');

    tenancy()->end();

    $response = test()->withHeaders(['X-Forwarded-Host' => LB_HOST.', '.tenant_domain('fwdlate')])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertNotFound();

    expect($response->getContent())->not->toContain('Late Roadster');
})->group('security');

it('strips a port from the forwarded host before matching', function () {
    config()->set('security.forwarded_host_recovery', true);

    $tenant = Tenant::factory()->withDomain('fwdport')->create();
    publicVehicleFor($tenant, 'Port Roadster');

    tenancy()->end();

    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('fwdport').':8443'])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertOk()
        ->assertSee('Port Roadster');
})->group('security');

/*
|--------------------------------------------------------------------------
| Detection — always on
|--------------------------------------------------------------------------
*/

it('warns when the primary host resolves to nothing and the forwarded host is a real tenant', function () {
    Log::spy();

    Tenant::factory()->withDomain('alertco')->create();

    tenancy()->end();

    // Flag off: still a 404, but no longer a silent one.
    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('alertco')])
        ->get('http://'.LB_HOST.'/vehicles')
        ->assertNotFound();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'rewriting Host')
                && $context['host'] === LB_HOST
                && $context['forwarded_host'] === tenant_domain('alertco')
                && $context['recovery_enabled'] === false
                && is_string($context['hint'])
                && $context['hint'] !== '';
        });
})->group('security');

it('does not warn for ordinary unknown subdomain probing', function (?string $forwarded) {
    Log::spy();

    Tenant::factory()->withDomain('quietco')->create();

    tenancy()->end();

    $headers = $forwarded === null ? [] : ['X-Forwarded-Host' => $forwarded];

    test()->withHeaders($headers)
        ->get('http://nosuchtenant.'.config('tenancy.tenant_base_domain').'/vehicles')
        ->assertNotFound();

    // The anti-log-flood guarantee: an attacker who cannot name a real tenant
    // domain cannot make this write a line.
    Log::shouldNotHaveReceived('warning');
})->with([
    'no forwarded header at all' => null,
    'forwarded host that resolves to nothing either' => 'alsonope.example.net',
])->group('security');

it('does not warn when the primary host is a central domain', function () {
    Log::spy();

    Tenant::factory()->withDomain('nocentralalert')->create();

    tenancy()->end();

    test()->withHeaders(['X-Forwarded-Host' => tenant_domain('nocentralalert')])
        ->get('http://'.config('tenancy.central_domain').'/vehicles')
        ->assertNotFound();

    // The central host is the most exposed surface in the app, and the outage this
    // detector exists for can never produce a central primary host — a load
    // balancer's own hostname is by definition not in central_domains. Logging
    // here would hand an anonymous attacker a free log-write primitive for no
    // diagnostic gain.
    Log::shouldNotHaveReceived('warning');
})->group('security');

it('warns at most once per forwarded host inside the throttle window', function () {
    Log::spy();

    Tenant::factory()->withDomain('onceco')->create();

    tenancy()->end();

    foreach (range(1, 3) as $ignored) {
        test()->withHeaders(['X-Forwarded-Host' => tenant_domain('onceco')])
            ->get('http://'.LB_HOST.'/vehicles')
            ->assertNotFound();
    }

    Log::shouldHaveReceived('warning')->once();
})->group('security');

it('warns per tenant domain rather than per primary host', function () {
    Log::spy();

    Tenant::factory()->withDomain('perdomain')->create();

    tenancy()->end();

    // Three DIFFERENT primary hosts, one forwarded host. If the dedup key were
    // derived from the primary host — which is attacker-controlled and unbounded —
    // this would log three times and the key space would be a cache-fill primitive.
    foreach (['lb-a.example.net', 'lb-b.example.net', 'lb-c.example.net'] as $lb) {
        test()->withHeaders(['X-Forwarded-Host' => tenant_domain('perdomain')])
            ->get('http://'.$lb.'/vehicles')
            ->assertNotFound();
    }

    Log::shouldHaveReceived('warning')->once();
})->group('security');

/*
|--------------------------------------------------------------------------
| Deploy probe
|--------------------------------------------------------------------------
*/

it('hides the host diagnostics endpoint by default', function () {
    test()->get('http://'.config('tenancy.central_domain').'/_diagnostics/host')
        ->assertNotFound();
})->group('security');

it('reports what the app sees when host diagnostics are enabled', function () {
    config()->set('security.host_diagnostics_enabled', true);

    Tenant::factory()->withDomain('probeco')->create();

    tenancy()->end();

    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('probeco')])
        ->get('http://'.LB_HOST.'/_diagnostics/host')
        ->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $response->assertJson([
        'host' => LB_HOST,
        'forwarded_host_normalized' => tenant_domain('probeco'),
        // The failure-mode-2 reading the runbook tells the operator to look for.
        'host_resolves_to_tenant' => false,
        'forwarded_host_resolves_to_tenant' => true,
        'host_is_central' => false,
        // The single fact the probe exists to establish.
        'x_forwarded_host_trusted' => false,
        'recovery_enabled' => false,
    ]);
})->group('security');

it('does not disclose tenant identity from the host diagnostics endpoint', function () {
    config()->set('security.host_diagnostics_enabled', true);

    $tenant = Tenant::factory()->withDomain('secretco')->create(['name' => 'Very Secret Rentals']);

    tenancy()->end();

    $response = test()->withHeaders(['X-Forwarded-Host' => tenant_domain('secretco')])
        ->get('http://'.LB_HOST.'/_diagnostics/host')
        ->assertOk();

    // Resolution answers are booleans only. This is what makes the endpoint safe
    // to reach on an unknown host: it cannot enumerate which subdomains exist, and
    // it never names the tenant it just resolved.
    //
    // The forwarded host itself IS echoed, but that is the caller's own input
    // coming back — it discloses nothing they did not already send.
    $response->assertJsonMissingPath('tenant_id')
        ->assertJsonMissingPath('tenant')
        ->assertJsonMissingPath('domain')
        ->assertJsonPath('forwarded_host_resolves_to_tenant', true);

    expect($response->getContent())->not->toContain('Very Secret Rentals')
        ->and($tenant->name)->toBe('Very Secret Rentals');
})->group('security');

it('throttles the host diagnostics endpoint', function () {
    config()->set('security.host_diagnostics_enabled', true);

    RateLimiter::clear('host-diagnostics');

    foreach (range(1, 10) as $ignored) {
        test()->get('http://'.config('tenancy.central_domain').'/_diagnostics/host')->assertOk();
    }

    test()->get('http://'.config('tenancy.central_domain').'/_diagnostics/host')
        ->assertStatus(429);
})->group('security');
