---
paths:
  - 'app/Models/*.php'
---

# Models

## tenant_id is never mass-assignable on a BelongsToTenant model
`BelongsToTenant::bootBelongsToTenant()` fills `tenant_id` only when it is NOT already set (`if (! $model->getAttribute('tenant_id') …)`), so a caller that passes one wins over the trait. Listing `tenant_id` in `#[Fillable]` therefore turns any `create($input)`/`fill($input)` carrying that key into a silent cross-tenant write — the global scope filters reads, not writes. Keep it out of `#[Fillable]` on every model that uses the trait; the trait supplies it.

Central models administered cross-tenant from the admin panel — `TenantPayment`, `AiUsageLog`, `EmailLog` — do NOT use the trait, so nothing supplies the value and `tenant_id` stays fillable there deliberately. That is the only exemption. `tests/Arch/TenantIdNotFillableTest.php` keys on the trait (not a name list) so the exemption cannot rot; `tests/Feature/Security/TenantIdMassAssignmentTest.php` pins the behaviour.

Consequences to know: `preventSilentlyDiscardingAttributes()` is off (`AppServiceProvider.php`), so a leftover `'tenant_id' =>` key in an array is dropped with no error and reads as working code — delete it at the call site. Factories are unaffected (`Factory::makeInstance()` wraps in `Model::unguarded()`), which is why the admin tests' `factory()->create(['tenant_id' => $other->id])` cross-tenant fixtures still work. `forceFill`/`forceCreate`/`setAttribute` still bypass this by design — they are explicit and greppable.

Related: `User::canAccessPanel()` requires `hasVerifiedEmail()` on BOTH the operator and admin branches. Keep them symmetric.
