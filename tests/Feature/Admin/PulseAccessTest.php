<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Livewire\Cache;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\Queues;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;

afterEach(fn () => tenancy()->end());

/**
 * Pulse exposes platform-wide diagnostics — every tenant's slow queries, jobs and
 * exceptions in one dashboard. Two things keep it out of operators' hands: the
 * viewPulse gate (Super Admin only) and the PULSE_DOMAIN pinning that keeps the
 * route off tenant subdomains. Both are covered here.
 */
function pulseUser(string $role, ?Tenant $tenant = null): User
{
    $user = new User;
    $user->forceFill([
        'tenant_id' => $tenant?->id,
        'role' => $role,
        'name' => ucfirst($role),
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    return $user;
}

it('allows a super admin through the viewPulse gate', function () {
    expect(Gate::forUser(pulseUser('admin'))->allows('viewPulse'))->toBeTrue();
});

it('denies operators and staff the viewPulse gate', function (string $role) {
    $tenant = Tenant::factory()->withDomain('ardi')->create();

    expect(Gate::forUser(pulseUser($role, $tenant))->allows('viewPulse'))->toBeFalse();
})->with(['operator', 'staff']);

it('renders the pulse dashboard for a super admin', function () {
    $this->actingAs(pulseUser('admin'))
        ->get('http://'.config('tenancy.admin_domain').'/pulse')
        ->assertOk();
});

it('renders each pulse card twice, so cached card data survives a round trip', function (string $card) {
    // Rendering twice is the whole point. Pulse's cards are #[Lazy], so the page
    // GET above only returns placeholders — the cards load over a follow-up
    // Livewire request, and the first render populates the cache while the second
    // reads it back. cache.serializable_classes must permit Collection and
    // stdClass or that second render hands the card a __PHP_Incomplete_Class and
    // 500s. A GET-only assertion passes straight through this bug.
    // phpunit.xml pins CACHE_STORE=array, whose driver never serializes — under it
    // this test passes no matter what serializable_classes says. Point at the
    // database store so the value makes a real serialize/unserialize round trip,
    // the same as production.
    config(['cache.default' => 'database']);

    $this->actingAs(pulseUser('admin'));

    Livewire::test($card)->call('$refresh')->assertOk();
    Livewire::test($card)->call('$refresh')->assertOk();
})->with([
    Cache::class,
    Exceptions::class,
    Queues::class,
    Servers::class,
    SlowQueries::class,
    Usage::class,
]);

it('refuses the pulse dashboard to an operator', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create();

    // 404 rather than 403: bootstrap/app.php rewrites every 403 so a resource you
    // may not see is indistinguishable from one that does not exist.
    $this->actingAs(pulseUser('operator', $tenant))
        ->get('http://'.config('tenancy.admin_domain').'/pulse')
        ->assertNotFound();
});

it('pins the pulse dashboard to the admin host so it cannot resolve on tenant subdomains', function () {
    // routes/tenant.php carries no domain constraint, so an unpinned route would be
    // served on every operator subdomain. The failure mode is silent — the dashboard
    // simply starts answering on hosts it should not — so pin it here, and pin it to
    // the same host the admin panel uses rather than merely "something non-empty".
    expect(config('pulse.domain'))
        ->not->toBeEmpty()
        ->toBe(config('tenancy.admin_domain'));
});

it('still pins the dashboard when PULSE_DOMAIN is declared but left blank', function () {
    // The dangerous case is not an unset variable, it is `PULSE_DOMAIN=` in a .env:
    // env() returns '' rather than null, so an env() default argument never fires,
    // and Route::domain('') is falsy — Laravel applies no host constraint at all.
    // Re-evaluate the config file under that env to prove the fallback chain holds.
    putenv('PULSE_DOMAIN=');

    try {
        $config = require config_path('pulse.php');

        expect($config['domain'])
            ->not->toBeEmpty()
            ->toBe(config('tenancy.admin_domain'));
    } finally {
        putenv('PULSE_DOMAIN');
    }
});
