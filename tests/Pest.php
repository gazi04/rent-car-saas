<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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

/** A full http:// URL for a tenant subdomain, optionally with a path/query. */
function tenant_url(string $subdomain, string $path = ''): string
{
    return 'http://'.tenant_domain($subdomain).$path;
}
