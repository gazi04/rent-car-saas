# Vehicle Maintenance Tracking (operator feature #10)

**Status:** Planned · **Build phase:** operator-side, independent (after #8 promo codes).
**Stack:** Laravel 13 · Filament 5 · reuses `blocked_dates` + the scheduled-command / per-tenant-job fan-out.

> Operators log a **service history** per vehicle (oil change, tyres, inspection…) — free on every plan, because that history is exactly the lock-in data an operator would lose by leaving. A **premium layer** (gated) then works the data for them: it **reminds** the operator when a service is coming due and **auto-blocks** the vehicle (via the existing blocked-dates mechanism) once it is due, so it can't be rented until serviced. Free shell, gated automation card inside — the same pattern as the dashboard (free) + AI summary (gated).

---

## Decisions (locked, 2026-07-03)

| Decision | Choice | Why |
| --- | --- | --- |
| Gating split | **Logging free** (all plans, owner-only) · **reminders + auto-block gated** by new `PlanFeature::MaintenanceReminders` (Toggle; Basic off, Standard/Pro on) | Lock-in data must stay free or cheap tenants keep no history; automation is the upsell. |
| Auto-block | Reuse **`blocked_dates`** (`reason = 'maintenance'`) | `isAvailable()` already honors blocked dates; the availability calendar already renders them. Zero engine change. |
| Due basis | **Date** (`next_due_on`) is primary; odometer optional. Current mileage derived from the latest booking `end_odometer` | `vehicles` has no odometer column; dates are reliable and simple. Odometer stays informational in v1. |
| Reminder sweep | **Daily central command → per-tenant job** (`tenancy()->initialize/end`) | Exact mirror of `GenerateBusinessSummaries` → `GenerateBusinessSummaryJob`. |
| Notifications | Filament DB bell (`sendToDatabase`) + queued **bilingual** Mailable (`operatorLocale()`) | Matches the booking-notification pattern. |
| Resolution | Logging a **new** service record for a vehicle removes its active maintenance block and clears the superseded due | Operator's natural action closes the loop; no extra "mark done" UI. |
| Access | Maintenance is **fleet data → owner-only** (like Vehicles), not staff | Consistent with the front-desk staff scope. |

---

## Data model

**New `service_records` table** (tenant-scoped, mirrors `Customer`/`PromoCode` conventions):
- `id`, `string tenant_id` (FK cascade), `foreignId vehicle_id` (constrained, cascade), `string service_type`, `date performed_on`, `unsignedInteger odometer` (nullable), `decimal cost 10,2` (nullable), `text notes` (nullable), `date next_due_on` (nullable), `unsignedInteger next_due_odometer` (nullable), `timestamp reminder_sent_at` (nullable), `foreignId blocked_date_id` (nullable, `nullOnDelete`), timestamps. Indexes `['tenant_id','vehicle_id']`, `['tenant_id','next_due_on']`.
- `App\Models\ServiceRecord` — `BelongsToTenant, HasFactory`; `#[Fillable([...])]`; casts (dates, `cost` decimal:2, ints); relations `vehicle(): BelongsTo`, `blockedDate(): BelongsTo`. Factory (+ `due()`/`overdue()` states).
- `App\Models\Vehicle`: add `serviceRecords(): HasMany`.
- `config/maintenance.php`: `service_types` (curated list: oil_change, tyres, inspection, brakes, other), `reminder_days` (e.g. `[7, 1]`), `block_days` (auto-block window length, e.g. `3`).

---

## Free layer — service logging (all plans, owner-only)

`app/Filament/Operator/Resources/ServiceRecords/**` (mirror `PromoCodes/`):
- `ServiceRecordResource`: model `ServiceRecord`, icon `Heroicon::OutlinedWrenchScrewdriver`, nav sort ~7, `recordTitleAttribute = 'service_type'`. `canAccess()` = `auth()->user()?->isOwner() ?? false` (**not** plan-gated — logging is free). `getEloquentQuery()` not needed (BelongsToTenant scopes it).
- `Schemas/ServiceRecordForm.php`: `vehicle_id` Select (`->relationship('vehicle','name')`), `service_type` Select (from config), `performed_on` DatePicker, `odometer` numeric (nullable, helper "km"), `cost` numeric (nullable), `next_due_on` DatePicker (nullable), `next_due_odometer` numeric (nullable), `notes` Textarea.
- `Tables/ServiceRecordsTable.php`: vehicle.name, service_type badge, performed_on, next_due_on (with an "overdue"/"due soon" badge derived from the date), cost; vehicle SelectFilter; row Edit; bulk Delete.
- Pages List/Create/Edit. On **create**, an `afterCreate()` clears the loop: delete any active `maintenance` `BlockedDate` for that vehicle and null the linking `blocked_date_id` on the superseded due record (a fresh service means the vehicle is serviced).
- Alternative UI (optional): a `ServiceRecordsRelationManager` under a new Vehicle **view** page — deferred; the standalone resource ships first.

Everyone can also see `next_due_on` here as a plain informational date even without the premium layer — the value is just not *acted on* (no reminder/block) unless the feature is enabled.

---

## Premium layer — reminders + auto-block (gated `MaintenanceReminders`)

1. **Gating**: `PlanFeature::MaintenanceReminders = 'maintenance_reminders'` (Toggle in `type()`, label, permissive default `true`). `PlanSeeder`: Basic `false`, Standard `true`, Pro omit (→ true).
2. **Config-driven sweep** — `app/Console/Commands/ProcessVehicleMaintenance.php` (`#[Signature('maintenance:process-due')]`): iterate `Tenant::where('status','active')->cursor()`, `continue` unless `allowsFeature(PlanFeature::MaintenanceReminders)`, dispatch `ProcessVehicleMaintenanceJob($tenant)`. Schedule in `routes/console.php`: `->dailyAt('07:00')->withoutOverlapping()`.
3. **Per-tenant job** — `app/Jobs/ProcessVehicleMaintenanceJob.php` (`ShouldQueue`; `tenancy()->initialize($tenant)` in `try/finally`). For service records with a `next_due_on`:
   - **Reminder**: `next_due_on` within `config('maintenance.reminder_days')` of today and `reminder_sent_at` null → send `ServiceDueMail` (queued, `operatorLocale()`) + Filament `->warning()->sendToDatabase()` to the tenant's owners (`User::where('tenant_id',…)`), set `reminder_sent_at`.
   - **Auto-block**: `next_due_on <= today` and `blocked_date_id` null → `BlockedDate::create(['vehicle_id'=>…, 'start_date'=>today, 'end_date'=>today->addDays(config('maintenance.block_days')), 'reason'=>'maintenance'])`, store its id on the record, optionally flip `VehicleStatus::UnderMaintenance`, notify.
4. **`ServiceDueMail`** (`app/Mail/`) + `resources/views/emails/service-due.blade.php` (markdown, bilingual via `lang/{en,sq}/emails.php`).
5. **Optional gated dashboard widget** `ServiceDueWidget` (owner, `canView()` = feature on) listing vehicles due/overdue — deferred if scope tightens.

When the feature is **off**, records still log freely; the sweep simply skips the tenant — no reminders, no blocks.

---

## Localization
- `lang/{en,sq}/panel.php`: `nav_service_records`, `service_*` field labels, `service_due`/`service_overdue` badges. Keep both files equal.
- `lang/{en,sq}/emails.php`: `service_due` subject/greeting/intro/outro.

## Testing (Pest)
1. Owner logs a service record → stored, tenant-scoped; `ServiceRecordResource::canAccess()` true for owner, false for staff; **not** blocked when the plan disables `maintenance_reminders` (logging is free).
2. Overdue record + feature on → job creates a `maintenance` `BlockedDate` (linked), and `AvailabilityService::isAvailable()` now returns false for that window.
3. Due-soon record → reminder notification sent once (`reminder_sent_at` set; second run no-ops). `Mail::fake()` / `Notification::fake()`.
4. Feature off → sweep skips the tenant (no block, no mail) even with an overdue record.
5. Logging a new service record clears the vehicle's active maintenance block (resolution).
6. Command dispatches jobs only for active tenants with the feature (`Queue::fake()`).
Then: `php artisan test --compact`, `vendor/bin/pint`, `composer types:check`; both `panel.php`/`emails.php` locales equal.

## Out of scope
- Odometer-based auto-block (v1 stays date-based; odometer is informational).
- A vehicle-level current-odometer column (derive from bookings if needed later).
- Customer-facing exposure (internal fleet tool only).
- Recurring/interval templates ("every 6 months") — operator sets `next_due_on` per record.

## Done checklist
- [x] `service_records` migration/model/factory; `Vehicle::serviceRecords()`; `config/maintenance.php`.
- [x] `ServiceRecordResource` (owner-only, free) + resolution-on-create.
- [x] `PlanFeature::MaintenanceReminders` + PlanSeeder (Basic off / Standard+Pro on).
- [x] `maintenance:process-due` command + `ProcessVehicleMaintenanceJob` + schedule entry.
- [x] `ServiceDueMail` + view; Filament DB bell.
- [x] EN+SQ lang keys.
- [x] Pest green (logging free, gating, auto-block honored by isAvailable, reminder-once, resolution, command fan-out).
- [x] Pint + PHPStan clean on all new/changed files. (Full suite has 63 pre-existing failures, all tenant-domain-resolution errors on `admin.localhost`/subdomains unrelated to this feature — not touched by this work.)
