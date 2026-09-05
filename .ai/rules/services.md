---
paths:
  - app/Services/BookingService.php
---

# Services

## Public booking input may create a customer record, never rewrite one
`resolveCustomer()` takes `bool $trustContactDetails`. `create()` (public storefront) passes **false** — `firstOrCreate` only, no `fill()` on an existing row, and no "helpful" backfill of a NULL email either (a blank contact address is precisely where an attacker supplying the first one takes ownership of "contact this customer"). `createManual()` (operator walk-in, reachable only from the Filament booking page) passes **true**: an authenticated operator typing at the front desk is the authority on their own directory.

Nothing is lost by refusing — `bookings.customer_name/_phone/_email` already carry what the visitor typed, so the operator still sees the submitted details on the booking. The directory record stays authoritative.

`is_blacklisted` blocks `create()` (throws `CustomerNotEligibleException`) and deliberately does NOT block `createManual()`. The storefront surfaces it as the generic `booking.submit_failed`, never a blacklist-specific message: naming it would confirm the flag to anyone probing phone numbers, and would be hostile to someone flagged by mistake.

Phone is the identity key (`customers.[tenant_id, phone]` is unique) and is stored normalized via `App\Support\PhoneNumber::normalize()`. Every site that matches a customer by phone must normalize first — `resolveCustomer()`, the storefront's `previewPromo()`, and the operator `CustomerForm` — or one human becomes two records and a promo's `per_customer_limit` is spendable twice. Country codes are deliberately not folded (`044…` vs `00383…` vs `+383…` stay distinct): collapsing them needs an assumption about local dialing, and a wrong one merges two real people irreversibly.

In Filament, normalize the phone in the VISIBLE state (`->live(onBlur: true)->afterStateUpdated(...)`), not via `dehydrateStateUsing()` — dehydration runs after validation, so `->unique()` would still compare the raw string.
