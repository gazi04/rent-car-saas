<?php

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Pest\Browser\Playwright\Playwright;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test storage cleanup
|--------------------------------------------------------------------------
|
| stancl/tenancy's FilesystemTenancyBootstrapper calls useStoragePath() on every
| tenancy()->initialize() — this swaps storage_path() itself for that test's
| duration, to storage/tenant{id}/. That's not limited to real disk writes:
| Storage::fake()'s own fake-disk location is *also* computed from
| storage_path(), so even fully-faked tests still create a fresh
| storage/tenant{id}/framework/testing/disks/... tree, one per tenant created.
| With ~170+ Feature tests each spinning up their own tenant, that's a folder
| per test, every run, forever, if nothing sweeps them.
|
| Two sweeps: once before any test runs (clears leftovers from a previous run
| that was interrupted before its own shutdown sweep could fire), and once via
| register_shutdown_function (fires after the whole suite finishes, pass or
| fail, clearing what *this* run creates). Runs before the app container
| exists, so this uses a plain path, not storage_path().
|
*/

function pest_recursive_rmdir(string $dir): void
{
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;

        is_dir($path) ? pest_recursive_rmdir($path) : unlink($path);
    }

    rmdir($dir);
}

function pest_sweep_tenant_storage_dirs(): void
{
    foreach (glob(__DIR__.'/../storage/tenant*', GLOB_ONLYDIR) ?: [] as $leftoverTenantDir) {
        pest_recursive_rmdir($leftoverTenantDir);
    }
}

pest_sweep_tenant_storage_dirs();
register_shutdown_function('pest_sweep_tenant_storage_dirs');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Postgres suite deliberately has no RefreshDatabase: these tests open a second,
// independent connection that must see (or be blocked by) rows the first connection
// committed/locked — RefreshDatabase's wrapping transaction would make that impossible.
pest()->extend(TestCase::class)
    ->in('Postgres');

// Browser suite: a real Chromium process driving real HTTP requests against the
// app, same RefreshDatabase binding as Feature — Pest's browser runtime makes
// SQLite :memory: visible to that browser process, so no DB config differs here.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

// Journeys are plain in-process Feature tests — they stay in the `composer test`
// gate, unlike the Postgres and Browser carve-outs, which are excluded for
// infrastructure reasons (a live Postgres connection, a Playwright binary) that
// don't apply here. The group exists so that if the time budget ever moves,
// carving them out is `--exclude-group=journey` rather than a refactor.
pest()->group('journey')->in('Feature/Journeys');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The full domain a tenant subdomain resolves at, built from the same config
 * key the app itself uses (tenancy.tenant_base_domain) — never hardcode a base
 * domain literal in a test, it drifts the moment that config changes.
 */
function tenant_domain(string $subdomain): string
{
    return $subdomain.'.'.config('tenancy.tenant_base_domain');
}

/**
 * A bookable, publicly-listed vehicle belonging to $tenant, created with tenancy
 * initialized and left with tenancy ended — the state a real request starts in.
 *
 * Lives here rather than in a test file because a helper declared inside one only
 * resolves cross-file by Pest's load order, which breaks under --filter. Shared by
 * TenantContextEnforcementTest and ForwardedHostTenancyTest.
 */
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

/** A full http:// URL for a tenant subdomain, optionally with a path/query. */
function tenant_url(string $subdomain, string $path = ''): string
{
    return 'http://'.tenant_domain($subdomain).$path;
}

/*
|--------------------------------------------------------------------------
| Livewire update-endpoint replay
|--------------------------------------------------------------------------
|
| Livewire's update endpoint is ONE global route with no domain constraint, and
| the browser drives it with a snapshot it holds itself. Anything that has to be
| proven about that round trip — tenant context, snapshot integrity, what an
| `updates` payload is allowed to change — has to be sent as a REAL HTTP request:
| Livewire::test() skips the persistent-middleware replay entirely
| (PersistentMiddleware.php:43) and never serialises the snapshot, so it
| structurally cannot reproduce either.
|
| These live here rather than in one of the suites that uses them because a
| helper declared inside a test file only resolves cross-file by Pest's load
| order, which breaks under --filter. Used by tests/Feature/Security/*.
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
 *
 * @param  array<string, mixed>  $snapshot
 * @param  array<string, mixed>  $updates
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

/**
 * Browser suite only: visit a path on a tenant subdomain and keep that Host for
 * the WHOLE test, not just the first navigation.
 *
 * PendingAwaitablePage::withHost() applies the host through withTemporaryHost(),
 * which restores the previous value as soon as the initial visit resolves. But
 * LaravelHttpServer rewrites the Host header of EVERY request it serves from
 * Playwright::host() — including the browser's later XHRs, i.e. every Livewire
 * wire:click round trip. Left unpinned those updates arrive on the bare server
 * origin (127.0.0.1), which is a central domain: tenancy never initializes, and
 * the tenant guards replayed as Livewire persistent middleware reject them.
 *
 * Pinning happens after the visit so the Playwright server itself still binds to
 * the default host. tenantHostReset() restores it; call it in afterEach.
 */
function visitAsTenant(string $subdomain, string $path = '/'): mixed
{
    $page = visit($path)->withHost(tenant_domain($subdomain));

    Playwright::setHost(tenant_domain($subdomain));

    return $page;
}

/** Undo visitAsTenant()'s Host pin so it cannot leak into the next test. */
function tenantHostReset(): void
{
    Playwright::setHost(null);
}

/**
 * A tenant + signed-in operator + one vehicle, with tenancy initialized and the
 * operator panel current. Shared by the Reports page suites (ReportsTest,
 * ReportsHeatmapTest) — lives here rather than in one of them because a helper
 * declared inside a test file only resolves cross-file by Pest's load order,
 * which breaks under --filter.
 *
 * @return array{0: Tenant, 1: User, 2: Vehicle}
 */
function reportsOperatorFor(string $domain): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    Pest\Laravel\actingAs($operator);

    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);

    return [$tenant, $operator, $vehicle];
}

/**
 * The default Reports date range used across the reports suites: all of June 2030.
 *
 * @return array{start_date: string, end_date: string}
 */
function reportRange(): array
{
    return ['start_date' => '2030-06-01', 'end_date' => '2030-06-30'];
}

/**
 * A tenant on $subdomain plus its verified operator owner.
 *
 * Deliberately does NOT initialize tenancy, unlike reportsOperatorFor() above: a
 * journey test crosses the operator/visitor boundary several times, and which
 * side it is standing on at any moment is the thing under test. Call
 * actAsOperator()/actAsVisitor() to move.
 *
 * @return array{0: Tenant, 1: User}
 */
function journeyTenant(string $subdomain): array
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    return [$tenant, $operator];
}

/**
 * Step into the operator panel as $user.
 *
 * The order is not interchangeable: User::canAccessPanel() reads tenant('id'),
 * so tenancy has to be live before the panel resolves, and the actor has to be
 * set after both. Same sequence as reportsOperatorFor() and every panel test in
 * the suite.
 */
function actAsOperator(Tenant $tenant, User $user): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    Pest\Laravel\actingAs($user);
}

/**
 * Step back out to an anonymous storefront visitor. Ending tenancy matters as
 * much as the logout: a subsequent tenant_url() request must re-resolve the
 * tenant from its subdomain, which is half of what a journey is proving.
 */
function actAsVisitor(): void
{
    Auth::logout();
    tenancy()->end();
}

/**
 * Fake both disks a tenant writes to — the configured media-library disk
 * (default 'public') and 'local' (rental-agreement PDFs).
 *
 * MUST be called after tenancy()->initialize(): FilesystemTenancyBootstrapper
 * rewrites disk roots on every initialize and silently undoes an earlier fake,
 * which is how real files end up in storage/tenant{id}/ (see the note at
 * tests/Feature/RentalAgreementTest.php:33-36). Re-call it after every context
 * switch that re-initializes tenancy.
 */
function fakeTenantDisks(): void
{
    Storage::fake('public');
    Storage::fake(config('media-library.disk_name'));
    Storage::fake('local');
}

/**
 * Attach $count fake photos to $vehicle's vehicle_photos collection.
 *
 * Two is the meaningful default. Builder::hydrate() only arms
 * Model::preventLazyLoading() on models from a multi-row result, so a
 * one-photo vehicle can never catch an implicit lazy load on a Media row —
 * which is exactly how the TenantAwarePathGenerator bug reached production.
 *
 * @return EloquentCollection<int, Media>
 */
function attachVehiclePhotos(Vehicle $vehicle, int $count = 2, int $width = 400, int $height = 300): EloquentCollection
{
    assertDiskIsFaked(config('media-library.disk_name'));

    /** @var EloquentCollection<int, Media> $media */
    $media = new EloquentCollection;

    foreach (range(1, $count) as $index) {
        $media->push(
            $vehicle->addMedia(UploadedFile::fake()->image("photo-{$index}.jpg", $width, $height))
                ->toMediaCollection('vehicle_photos')
        );
    }

    return $media;
}

/**
 * Attach a fake logo to $tenant. The collection is singleFile(), so a tenant
 * can only ever hold one — plurality for the tenant-logo path is achieved with
 * two tenants, not two logos.
 */
function attachTenantLogo(Tenant $tenant, int $width = 200, int $height = 200): Media
{
    assertDiskIsFaked(config('media-library.disk_name'));

    return $tenant->addMedia(UploadedFile::fake()->image('logo.png', $width, $height))
        ->toMediaCollection('logo');
}

/**
 * Refuse to write media unless $disk is currently a Storage::fake().
 *
 * The shutdown sweep at the top of this file only clears storage/tenant*. Media
 * goes to the central 'public' disk at storage/app/public/tenants/{id}/, which
 * nothing sweeps — so one missing Storage::fake() leaks files into the repo
 * permanently and silently. Fail loudly at the call site instead.
 */
function assertDiskIsFaked(string $disk): void
{
    if (! str_contains(Storage::disk($disk)->path(''), 'framework/testing/disks')) {
        throw new RuntimeException(
            "Disk [{$disk}] is not faked — call fakeTenantDisks() after tenancy()->initialize() before writing media, ".
            'or this test will leave real files in storage/.'
        );
    }
}
