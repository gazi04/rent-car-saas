---
paths:
  - 'app/Http/Responses/**'
---

# Responses

## Post-email-verification redirect is role-aware (operators → subdomain panel)
Fortify's `verification.verify` route has no domain constraint and its link is built on the central host, so the default VerifyEmailResponse dumps everyone on the central starter `/dashboard`. `app/Http/Responses/VerifyEmailResponse.php` (bound in FortifyServiceProvider::register()) overrides this: operator/staff with a tenant_id → active tenant redirects to `{Tenant::publicRootUrl()}/dashboard`, pending/suspended → central `login` with a flashed `status`; everyone else keeps Fortify's default. Load the tenant with an explicit `Tenant::query()->whereKey(...)` — never `$user->tenant` (lazy-load guard). Production must also set `SESSION_DOMAIN=.renti.lol` or the operator lands on the panel login instead of arriving authenticated. LoginResponse still has the same latent bug (not fixed — operators normally use the subdomain panel login).
