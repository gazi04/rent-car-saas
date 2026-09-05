---
paths:
  - app/Providers/TenancyServiceProvider.php
  - 'app/Providers/**'
---

# Providers

## Tenant guards must be Livewire persistent middleware, not just route middleware
TenantScope applies NO filter when `! tenancy()->initialized`, and Livewire's update endpoint is one global route with no domain constraint that skips tenancy on central domains (UniversalRoutes). On update, Livewire only re-runs route middleware that is in its persistent allowlist — so a storefront snapshot POSTed to the central host once re-rendered with every tenant's rows, unauthenticated.

`makeLivewireUpdatesRespectTenantBoundaries()` registers PreventAccessFromCentralDomains + EnsureTenantIsActive via `Livewire::addPersistentMiddleware()`. Never "fix" this by adding PreventAccessFromCentralDomains to the update route itself: it is a blind host denylist that abort(404)s on any central domain, and the admin panel's Livewire uses that same route — it would 404 the whole admin panel. Do not add InitializeTenancyByDomain to the allowlist either; it already runs as route middleware there.

Any new tenant-facing route must carry PreventAccessFromCentralDomains, or its components are drivable with the tenant scope disengaged. Regression tests: tests/Feature/Security/TenantContextEnforcementTest.php.

## Fortify's routes cannot be mutated from a booted() callback
Attaching middleware to an already-registered route from `$this->app->booted(...)` in an app service provider **fails silently**. `RouteServiceProvider::register()` queues route loading in a booted callback, and the name-lookup refresh in a *nested* one, so a callback queued from any app provider's `boot()` runs before `Route::getRoutes()->getByName()` can resolve anything — and under `route:cache` before the routes exist at all. `getByName()` just returns null and nothing is added. `php artisan route:list` will show the middleware missing; a route-shaped test would have passed against the broken version.

This matters because Fortify exposes limiter config for `login`, `two-factor`, `passkeys` and `verification` ONLY — `password.email` and `password.update` ship with `['guest:web']` and no lever.

The working pattern is `App\Http\Middleware\ThrottlePasswordResetRequests`: a `web`-group middleware that matches on `$request->route()?->getName()` inside the request lifecycle, where the route is already resolved and no ordering assumption is needed. Match on route NAME, not URI — the paths come from `config/fortify.php` via `RoutePath` and would drift from a hardcoded string.

`TenancyServiceProvider::mapRoutes()` does use `booted()`, but it *registers* routes rather than mutating already-registered ones, which is why it works.
