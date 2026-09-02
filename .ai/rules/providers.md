---
paths:
  - app/Providers/TenancyServiceProvider.php
---

# Providers

## Tenant guards must be Livewire persistent middleware, not just route middleware
TenantScope applies NO filter when `! tenancy()->initialized`, and Livewire's update endpoint is one global route with no domain constraint that skips tenancy on central domains (UniversalRoutes). On update, Livewire only re-runs route middleware that is in its persistent allowlist — so a storefront snapshot POSTed to the central host once re-rendered with every tenant's rows, unauthenticated.

`makeLivewireUpdatesRespectTenantBoundaries()` registers PreventAccessFromCentralDomains + EnsureTenantIsActive via `Livewire::addPersistentMiddleware()`. Never "fix" this by adding PreventAccessFromCentralDomains to the update route itself: it is a blind host denylist that abort(404)s on any central domain, and the admin panel's Livewire uses that same route — it would 404 the whole admin panel. Do not add InitializeTenancyByDomain to the allowlist either; it already runs as route middleware there.

Any new tenant-facing route must carry PreventAccessFromCentralDomains, or its components are drivable with the tenant scope disengaged. Regression tests: tests/Feature/Security/TenantContextEnforcementTest.php.
