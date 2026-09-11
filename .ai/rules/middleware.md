---
paths:
  - app/Http/Middleware/EnsureTenantIsActive.php
  - 'app/Http/Middleware/**'
---

# Middleware

## EnsureTenantIsActive picks its view from a middleware parameter, not the route name
The audience is declared where the middleware is registered: `EnsureTenantIsActive::class.':panel'` in OperatorPanelProvider (operator copy, resources/views/tenant/inactive.blade.php), bare in routes/tenant.php (customer copy, resources/views/public/unavailable.blade.php). It used to sniff a `filament.` route-name prefix, which also matched the admin panel.

The parameterised form is safe on the shared Livewire update route: Livewire's persistent-middleware allowlist compares on the bare class name (`Str::before($value, ':')` in vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php), so TenancyServiceProvider's allowlist entry stays unparameterised and the guard still replays. Do not "fix" that allowlist by adding the argument.

Which view each audience gets is pinned by tests/Feature/TenantStatusGateTest.php and tests/Feature/Operator/OperatorPanelTest.php — if you change the parameter, those fail.

## Route middleware cannot be assumed to run before tenancy or bindings
TenancyServiceProvider::makeTenancyMiddlewareHighestPriority() prepends InitializeTenancyByDomain to the middleware priority list, and SortedMiddleware splices priority-listed middleware UPWARD past anything not in that list. So middleware written above it in routes/tenant.php is not guaranteed to still be above it after sorting.

Two consequences, both verified empirically (2026-09-05), not reasoned:

1. Middleware that must precede tenant resolution belongs in global middleware (bootstrap/app.php), not a route stack. Use append() rather than prepend() so it lands after TrustProxies. See ForwardedHostTenancyGuard.
2. SubstituteBindings also runs BEFORE route middleware here, so route middleware never executes at all when route-model binding fails — it cannot observe or count a 404 from a missing record. Use the route's ->missing() callback for that (SubstituteBindings.php:45-46). ThrottleBookingReferenceMisses does this; its first version counted nothing and a log probe was what revealed it.

If you add middleware whose correctness depends on ordering, prove the ordering with a probe before trusting it.
