<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Routes that only resolve on a tenant (operator) subdomain. The middleware
| stack resolves the tenant from the host and aborts on central domains.
|
| NOTE: These routes must not share a URI with a central route in web.php.
| Laravel keys routes by method+URI (the domain is ignored when not set), so
| the later-registered route silently overrides the earlier one. The real
| public booking page (tenant "/") is added in the Public-Site step, where
| central vs tenant "/" is separated by subdomain routing.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Temporary smoke-test endpoint proving tenant resolution works.
    // Replaced by the public booking website in a later step.
    Route::get('/_tenancy-check', function () {
        return 'tenant:'.tenant('id');
    })->name('tenancy.check');
});
