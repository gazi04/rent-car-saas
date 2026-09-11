---
paths:
  - 'resources/views/**'
---

# Views

## Laravel starter-kit pages were deleted — do not recreate
The chisel/Livewire starter-kit scaffolding was removed 2026-08: central `/dashboard` route + `resources/views/dashboard.blade.php`, the `layouts/app` sidebar/header shell, the whole `resources/views/pages/settings/**` account-settings area + `routes/settings.php`, Fortify `register` (view + `CreateNewUser` + `Features::registration()`), and 2FA/passkeys (`Features::twoFactorAuthentication`/`passkeys`, `two-factor-challenge` view, `components/passkey-*`, `resources/js/passkeys.js`, `.well-known/passkey-endpoints`). `config/fortify.php` `home` is now `/` (marketing). Real product surfaces: marketing `resources/views/marketing/**`, operator signup `/signup` → `pages::auth.operator-register`, the two Filament panels (own `->login()`), public site `routes/tenant.php`. Kept Fortify auth: `login`/`logout`, password reset, email verification, `password.confirm` — plus their `pages/auth/*` views and `layouts/auth*`. `users` two_factor_* columns + `passkeys` table + the User traits are dormant, not reverted. Don't reintroduce a central dashboard/settings/register UI.

## Don't put @php(...) inline before a @php...@endphp block in the same view
Blade's `@php ... @endphp` block matcher pairs the FIRST `@php` it sees with the next `@endphp`. An inline `@php($x = null)` placed earlier in the same file gets swallowed: everything up to the block's `@endphp` is emitted as raw `<?php ...` with no close, and the page 500s with "ParseError: unexpected token endforeach". Hit in marketing/home.blade.php Aug 2026.

Fixes: initialise the var inside an existing `@php ... @endphp` block instead (e.g. `$previousPlan ??= null;` as the first loop iteration), or keep all inline `@php(...)` statements AFTER the last `@endphp` in the file. Inline `@php(...)` with no `@endphp` anywhere after it compiles fine.

## Storefront XSS is an admin-takeover path — never render untrusted content unescaped
SESSION_DOMAIN is scoped to the parent domain (`.<domain>`) on purpose, so operator impersonation survives the admin -> tenant-subdomain redirect. That means a Super Admin's session cookie is sent to EVERY operator-controlled subdomain. Script execution on any storefront is therefore not a defacement — it is admin session theft plus cross-operator access.

So: operator-supplied settings, customer-supplied text (reviews, waitlist, booking notes) and AI-generated output must never reach `{!! !!}`, an unescaped attribute, or a raw HTML sink. app/Http/Middleware/SecurityHeaders.php enforces a CSP with no 'unsafe-inline' in script-src as the backstop; do not add 'unsafe-inline' to it, and do not introduce inline `<script>` blocks in storefront views (there are currently none — all JS is bundled through Vite).

## Livewire model-typed public props need #[Locked] — and the tenant scope is NOT what protects them
Every public property typed as an Eloquent model on a Livewire SFC must carry `#[Locked]`. Enforced by tests/Arch/LivewireModelPropertiesTest.php (source scan — Pest's class-based arch() cannot see single-file components).

Livewire already refuses to re-point such a property: the snapshot is HMAC-checksummed (Checksum::verify), and HandleSynths::hydrateForUpdate() takes the model's meta only from that verified snapshot, so ModelSynth resolves $meta['key'] and discards any client value. `#[Locked]` is defense-in-depth — it states the constraint at the declaration and turns a silent discard into CannotUpdateLockedPropertyException.

Trap: do NOT reason "the tenant scope would catch a swap anyway". ModelSynth restores via Model::newQueryForRestoration() = newQueryWithoutScopes(), so BelongsToTenant does NOT apply to Livewire model re-hydration. Containment is the checksum alone; a regression there is cross-tenant. Pinned by tests/Feature/Security/LivewireModelTamperingTest.php.

Also: mount()'s abort_unless(...) guards run on mount only, never on a later /livewire/update.
