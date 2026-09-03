---
paths:
  - app/Http/Middleware/EnsureTenantIsActive.php
---

# Middleware

## EnsureTenantIsActive picks its view from a middleware parameter, not the route name
The audience is declared where the middleware is registered: `EnsureTenantIsActive::class.':panel'` in OperatorPanelProvider (operator copy, resources/views/tenant/inactive.blade.php), bare in routes/tenant.php (customer copy, resources/views/public/unavailable.blade.php). It used to sniff a `filament.` route-name prefix, which also matched the admin panel.

The parameterised form is safe on the shared Livewire update route: Livewire's persistent-middleware allowlist compares on the bare class name (`Str::before($value, ':')` in vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php), so TenancyServiceProvider's allowlist entry stays unparameterised and the guard still replays. Do not "fix" that allowlist by adding the argument.

Which view each audience gets is pinned by tests/Feature/TenantStatusGateTest.php and tests/Feature/Operator/OperatorPanelTest.php — if you change the parameter, those fail.
