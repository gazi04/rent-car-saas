---
paths:
  - app/Rules/AvailableSubdomain.php
---

# Rules

## Subdomain claims: one shared rule, two arbiters, always in a transaction
There are TWO places a tenant subdomain can be claimed — operator self-signup (resources/views/pages/auth/operator-register.blade.php) and the admin TenantForm. They had drifted: the admin form had the regex and the "already taken" check but no reserved-name check at all, so an admin could hand an operator "admin.<domain>". Both now go through App\Rules\AvailableSubdomain, which reads config('tenancy.reserved_subdomains'). Add a name there, never inline.

The rule's availability check is advisory — a read before an insert. Under concurrency TWO different guards can reject the write, and both must be caught: stancl's Domain model re-checks occupancy on `saving` (EnsuresDomainIsNotOccupied) and throws DomainOccupiedByOtherTenantException *first*; that check is itself racy, so the `domains.domain` unique index (UniqueConstraintViolationException) is the final arbiter.

Always create tenant + domain (+ user) inside DB::transaction, or a rejected domain write leaves an orphan tenant squatting a plan. Catch each insert at its own call site, not once around the transaction: after a rollback you can no longer tell which constraint fired, and re-querying answers about a row that no longer exists. Tests: tests/Feature/Security/OperatorSignupHardeningTest.php.
