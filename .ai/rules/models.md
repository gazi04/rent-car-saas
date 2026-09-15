---
paths:
  - 'app/Models/*.php'
  - app/Models/Tenant.php
---

# Models

## tenant_id is never mass-assignable on a BelongsToTenant model
`BelongsToTenant::bootBelongsToTenant()` fills `tenant_id` only when it is NOT already set (`if (! $model->getAttribute('tenant_id') …)`), so a caller that passes one wins over the trait. Listing `tenant_id` in `#[Fillable]` therefore turns any `create($input)`/`fill($input)` carrying that key into a silent cross-tenant write — the global scope filters reads, not writes. Keep it out of `#[Fillable]` on every model that uses the trait; the trait supplies it.

Central models administered cross-tenant from the admin panel — `TenantPayment`, `AiUsageLog`, `EmailLog` — do NOT use the trait, so nothing supplies the value and `tenant_id` stays fillable there deliberately. That is the only exemption. `tests/Arch/TenantIdNotFillableTest.php` keys on the trait (not a name list) so the exemption cannot rot; `tests/Feature/Security/TenantIdMassAssignmentTest.php` pins the behaviour.

Consequences to know: `preventSilentlyDiscardingAttributes()` is off (`AppServiceProvider.php`), so a leftover `'tenant_id' =>` key in an array is dropped with no error and reads as working code — delete it at the call site. Factories are unaffected (`Factory::makeInstance()` wraps in `Model::unguarded()`), which is why the admin tests' `factory()->create(['tenant_id' => $other->id])` cross-tenant fixtures still work. `forceFill`/`forceCreate`/`setAttribute` still bypass this by design — they are explicit and greppable.

Related: `User::canAccessPanel()` requires `hasVerifiedEmail()` on BOTH the operator and admin branches. Keep them symmetric.

## Tenant deletes do not remove users — the deleting hook does
`users.tenant_id` is the one tenant_id FK declared `nullOnDelete`; every other one (domains, tenant_settings, vehicles, bookings, …) is `cascadeOnDelete`. So a tenant delete used to leave the operator's row behind with `tenant_id = NULL` and `role = 'operator'` — not an access problem (both `User::canAccessPanel()` branches fail for it), but `users.email` is unique, so the purged operator could never sign up again with that address.

`Tenant::booted()`'s `deleting` hook is what removes them, and it is the only total path: it covers the scheduled sweep, `purgeAction()`, `EditTenant`'s `DeleteAction` and the unguarded `DeleteBulkAction`. Delete tenants with Eloquent (`$tenant->delete()`), never a query-builder delete — a query delete skips this hook and Media Library's logo cleanup both.

Found 2026-09-11 (redteam Finding 7); pinned by tests/Feature/Admin/AbandonedTenantPurgeTest.php and TenantManagementTest.php.

## Tenant attributes not in getCustomColumns() are diverted into the data blob
`Tenant` extends stancl's base model, which uses `VirtualColumn`: any attribute NOT listed in `Tenant::getCustomColumns()` is encoded into the `data` JSON column on save and decoded back on read. The real table column is left to whatever else sets it.

This makes model-level and SQL-level checks disagree. `created_at` was missing from the list, so `Tenant::factory()->create(['created_at' => now()->subDays(60)])` wrote the fake date to `data->created_at` while Eloquent's timestamps wrote `now()` to the actual `created_at` column. Reads decoded the fake value back, so `isAbandoned()` (reads the model attribute) saw a 60-day-old tenant while a `where('created_at', …)` query saw a brand new one. The existing purge tests passed only because they happened to use the model-level path.

Add every real column to `getCustomColumns()` — `created_at`/`updated_at` are now listed. If you add a column to the tenants table in a migration, add it there too, or writes to it will silently land in `data` instead.
