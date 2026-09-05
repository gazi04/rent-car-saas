<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | CSP violation reporting
    |--------------------------------------------------------------------------
    |
    | The Content-Security-Policy in App\Http\Middleware\SecurityHeaders is
    | load-bearing for tenant isolation, not defence in depth: the session cookie
    | is scoped to the parent domain, so script execution on any operator's
    | storefront would be an admin-takeover path. A regression in `script-src`
    | (a new CDN, an analytics snippet, a stray 'unsafe-inline') therefore has to
    | be visible.
    |
    | `report_uri` is the endpoint browsers POST violations to. Leave it as the
    | app's own route to have violations logged at `warning` (and picked up by
    | Sentry wherever a DSN is configured), or point it at an external collector
    | — Sentry's own security endpoint, say — with no code change. Set it to null
    | to send no reporting directive at all.
    |
    */

    'csp_report_uri' => env('CSP_REPORT_URI', '/csp-report'),

    /*
    |--------------------------------------------------------------------------
    | Forwarded-host recovery
    |--------------------------------------------------------------------------
    |
    | Tenants are resolved from $request->getHost() by InitializeTenancyByDomain,
    | and the tenant route group carries no Route::domain() constraint. Laravel
    | Cloud publishes no documentation of whether its load balancer preserves the
    | original Host header. If it rewrites Host to its own hostname and passes the
    | real one only in X-Forwarded-Host, nothing resolves and EVERY tenant
    | subdomain 404s — a total outage whose 404 is indistinguishable from an
    | ordinary unknown-subdomain 404.
    |
    | This flag does not trust X-Forwarded-Host; the header stays out of
    | trustProxies() in bootstrap/app.php permanently. It *validates* it: the
    | forwarded host is honoured only when it exactly matches a row in the
    | `domains` table AND the real Host resolves to nothing at all — not a central
    | domain, not a tenant domain. An attacker who can reach the origin directly
    | and set X-Forwarded-Host can equally set Host to the same value for the same
    | result, so this grants no capability a direct-to-origin request did not
    | already have. What it buys is that a load balancer which rewrites Host stops
    | being an outage that needs a code deploy to fix.
    |
    | Leave it false. Turn it on only if the deploy probe in docs/deploy-runbook.md
    | shows the load balancer rewriting Host, and turn it back off once the load
    | balancer is fixed.
    |
    */

    'forwarded_host_recovery' => env('FORWARDED_HOST_RECOVERY', false),

    /*
    |--------------------------------------------------------------------------
    | Host diagnostics probe
    |--------------------------------------------------------------------------
    |
    | Exposes GET /_diagnostics/host, which reports what the app actually sees for
    | Host, X-Forwarded-Host, scheme and port. It exists for the first-deploy check
    | in docs/deploy-runbook.md, which cannot be run any other way: an Artisan
    | command has no request, so it has no Host header to report on.
    |
    | The two "does this resolve to a tenant" answers are booleans, never a tenant
    | id, name or subdomain, so the endpoint cannot be used to enumerate which
    | subdomains exist. It is throttled regardless. Turn it on for the probe, then
    | turn it back off.
    |
    */

    'host_diagnostics_enabled' => env('HOST_DIAGNOSTICS_ENABLED', false),

];
