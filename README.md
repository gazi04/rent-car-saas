# 🚗 Renti

**A multi-tenant, white-label car-rental SaaS built for the Kosovo / Western Balkans market.**

Here's the idea: a car-rental business (an "operator") signs up and gets its own branded booking website on a subdomain — `operatorname.yourdomain.com`. Their customers land there and book a car in three steps, **no account required**. Meanwhile the platform owner (the "Super Admin") onboards operators, charges them a monthly subscription, and never touches a cent of the money that moves between an operator and its customers.

> **Stack:** PHP 8.5 · Laravel 13 · Livewire 4 · Filament 5 · Flux UI 2 · Tailwind CSS 4 · `stancl/tenancy` 3 (single-database) · PostgreSQL · Pest 4

---

## Table of Contents

- [What it is](#-what-it-is)
- [Architecture](#-architecture)
- [Feature modules](#-feature-modules)
- [Domain model](#-domain-model)
- [Project layout](#-project-layout)
- [Requirements](#-requirements)
- [Getting started](#-getting-started)
- [Running the app](#-running-the-app)
- [Environment variables](#-environment-variables)
- [Testing](#-testing)
- [Code quality](#-code-quality)
- [Localization](#-localization)

---

## 🧭 What it is

Three roles share one codebase and one database:

| Role | Where they live | Auth | Purpose |
|------|-----------------|------|---------|
| **Super Admin** | `admin.yourdomain.com` (Filament panel) | `users.tenant_id = NULL`, `role = admin` | Onboard/approve/suspend operators, record subscription payments, manage plans, audit. |
| **Operator** | `operatorname.yourdomain.com/dashboard` (Filament panel) | `role = operator` (owner) or `role = staff` (front desk) | Manage fleet, bookings, pricing, branding, customers, reviews. |
| **Customer** | `operatorname.yourdomain.com` (public site) | **None** — no login | Browse cars, book in 3 steps, pay the operator directly (cash / bank transfer). |

Two constraints shape everything else in the system, so they're worth knowing up front:

- 💸 **The platform never handles rental money.** There are two strictly separate flows: **B2B** (operator → platform, the monthly subscription, recorded manually by an admin) and **B2C** (customer → operator, settled directly between the two of them).
- 🌍 **Bilingual by default** — Albanian (`sq`, the primary market) and English (`en`).

---

## 🧩 Architecture

- **Single shared database, single codebase, `stancl/tenancy` single-DB mode.** Every tenant-scoped table carries a `tenant_id` column. The tenant is resolved from the request subdomain by stancl middleware (`InitializeTenancyByDomain`), and the `BelongsToTenant` trait installs an Eloquent **global scope** that auto-filters every query for you. `User` and `Tenant` are the two deliberate central exceptions. There is **no** per-tenant schema — `database/migrations/tenant/` is empty on purpose.

- **Two Filament 5 panels**, registered as separate `PanelProvider`s:
  - **Admin panel** — bound to the central `admin_domain`, never tenant-scoped (`AdminPanelProvider`, path `/`).
  - **Operator panel** — **no fixed domain**; one panel serves every tenant subdomain, resolved per request. Path `/dashboard`, brand name pulled from the current tenant (`OperatorPanelProvider`).

  Both gate access through `User::canAccessPanel()` (role + `tenant_id` match).

- **Livewire-first routing.** Pages are registered with `Route::livewire('path', 'pages::dir.name')` rather than controllers — there's effectively one real controller in the whole app (the base class). Public pages are **Livewire 4 single-file components** under `resources/views/pages/**`.

- **Central vs tenant routing collision.** The operator panel serves `/dashboard` on *every* subdomain, so central marketing routes in `routes/web.php` are pinned to `config('tenancy.central_domain')` via `Route::domain(...)` to keep them from clashing. Tenant/public routes live in `routes/tenant.php` behind the tenancy + `EnsureTenantIsActive` middleware stack.

- 🔒 **Race-safe booking.** Availability conflict checks run server-side inside a DB transaction with a **row-level lock** on the vehicle — double-booking destroys operator trust, so this one isn't left to chance. It's proven against a **real PostgreSQL** connection in CI (the `tests/Postgres` suite), not just SQLite.

- **Background work initializes its own tenant context.** Jobs fanned out from a central command (e.g. `ProcessVehicleMaintenanceJob`) call `tenancy()->initialize($tenant)` / `tenancy()->end()` themselves, because the queue bootstrapper won't do it for jobs dispatched this way. Easy trap to fall into — worth remembering.

### Request flow at a glance

```
                       ┌─────────────────────────────────────────────┐
  admin.yourdomain.com │  Filament ADMIN panel  (central, tenant_id=NULL)
                       └─────────────────────────────────────────────┘

     yourdomain.com    ┌─────────────────────────────────────────────┐
   (central_domain)    │  Marketing site + operator self-registration │
                       └─────────────────────────────────────────────┘

  ardi.yourdomain.com  ┌─────────────────────────────────────────────┐
   (tenant subdomain)  │  InitializeTenancyByDomain → EnsureTenantIsActive
                       │    /            → public booking site (no auth)
                       │    /dashboard   → Filament OPERATOR panel (auth)
                       └─────────────────────────────────────────────┘
```

---

## 📦 Feature modules

All five modules are **implemented** and in use.

### 1. Public booking website
White-label per operator (logo / colors / font / footer / per-page layout stored in the `tenant_settings` key-value table → injected as CSS variables in the Blade layout). Vehicle listing with filters, a 3-step booking flow (dates → details → review), no account, promo codes. Bookings are created as `pending`. Signed, tokenless links handle booking confirmation, self-cancellation (24h), agreement download (7d), and review submission (~30d).

### 2. Operator dashboard (Filament)
Fleet management (vehicle CRUD, photos → WebP, custom JSONB fields, soft deletes), availability calendar, booking management (confirm / reject / active / complete / cancel + manual booking), pricing (hourly / daily / weekly / monthly + discounts + promo codes), rental-agreement PDF, dashboard notifications, reports + CSV export, customer directory, staff sub-accounts, custom contract/email templates, customer reviews (collect / moderate / showcase), and three OpenAI-backed features (listing writer, weekly business summary, pricing suggestions).

Some operator features are **plan-gated** (turned on per subscription plan): staff seat limits, promo codes, custom templates, maintenance reminders/auto-block, review request emails + home showcase, and the AI features. Free for everyone, no matter the plan: dashboard home, customer directory, review collection/moderation/display, and service-record logging.

### 3. Admin dashboard (Filament)
Tenant management (approve / reject / suspend / **impersonate** operators), manual subscription controls (extend period, change plan, extend trial), custom plan + feature-gate editor, cross-tenant user management, revenue + at-risk-tenant widgets, and an audit log. Still on the wishlist: AI usage/cost tracking, queue/failed-jobs health, email delivery log, and global search.

### 4. Billing & subscriptions
**Manual B2B billing, no payment gateway.** Laravel Cashier/Stripe was evaluated and dropped for a simple reason: Stripe doesn't support payouts to Kosovo. So operator plans (Trial / Basic €15 / Standard €29 / Pro €49) are paid out-of-band and an admin records each payment. A `TenantPayment` advances `paid_until`, and a daily sweep drives renewal reminders → grace period → auto-suspension.

### 5. Notifications & emails
Queued, bilingual Blade-template Mailables with per-operator branding on customer-facing mail, plus Filament DB-bell notifications. (Resend is the intended production mailer; the default `.env` ships `MAIL_MAILER=log`, so nothing escapes your machine while you're developing.)

---

## 📚 Domain model

**14 Eloquent models.** Tenant-scoped models use the `BelongsToTenant` trait (auto-filtering global scope); central models don't.

**Tenant-scoped:** `Vehicle` · `Booking` · `Customer` · `BlockedDate` · `Contract` · `PromoCode` · `Review` · `ServiceRecord` · `AiBusinessSummary` · `TenantSetting`

**Central (not tenant-scoped):** `Tenant` · `TenantPayment` · `Plan` · `User`

Core tables (see `database/migrations/`):

```
tenants, domains            tenant identity + subdomain (stancl core)
users (+ tenant_id, role,   operator / staff / admin accounts
       locale, 2FA cols)
passkeys                    WebAuthn credentials
plans, tenant_payments      subscription plans + manual B2B payments
tenant_settings             per-tenant branding + template overrides (key/value, per-locale)
vehicles                    fleet (+ Spatie `media` for photos)
bookings                    rentals (+ customer_id, promo_code_id, review_requested_at)
blocked_dates               manual / maintenance date blocks
customers, promo_codes      CRM + discount codes
contracts, service_records  rental agreements + maintenance history
reviews                     tokenless star reviews (moderated)
ai_business_summaries       stored weekly AI summaries
media, activity_log,        Spatie media, audit trail, DB notifications
  notifications
cache, jobs                 framework infra
```

**Enums** (`app/Enums/`): `BookingStatus` · `FuelType` · `PaymentMethod` · `PlanFeature` · `PlanFeatureType` · `RateType` · `TenantStatus` · `Transmission` · `VehicleCategory` · `VehicleStatus`.

---

## 📁 Project layout

```
app/
├── Console/Commands/         Scheduled entrypoints (see "Running the app")
│   ├── ProcessTenantSubscriptions.php   daily B2B billing sweep
│   ├── GenerateBusinessSummaries.php     weekly AI summary fan-out
│   ├── ProcessVehicleMaintenance.php     daily maintenance fan-out
│   └── RequestPendingReviews.php         daily review-invite fan-out
├── Enums/                    10 domain enums
├── Filament/
│   ├── Resources/            ADMIN panel: Tenants, Plans, Users, Activities
│   ├── Widgets/              ADMIN: TenantStats, AtRiskTenants
│   ├── Operator/
│   │   ├── Resources/        Bookings, Customers, Vehicles, PromoCodes,
│   │   │                       Reviews, ServiceRecords, Staff
│   │   ├── Pages/            BrandingSettings, TemplateSettings, Reports
│   │   └── Widgets/          OperatorStatsOverview, AvailabilityCalendar,
│   │                           BusinessSummary, NeedsAttention, TodaysMovements
│   └── Support/HelpAction.php  shared contextual-help action
├── Http/Middleware/
│   ├── EnsureTenantIsActive.php            gate panel + storefront by tenant status
│   ├── ResolveFilamentPanelForSharedRoutes.php  fix panel on Livewire shared route
│   ├── SetLocale.php / SetUserLocale.php   locale resolution
├── Jobs/                     GenerateBusinessSummaryJob, ProcessVehicleMaintenanceJob,
│                               RequestReviewsJob (each initializes its own tenant)
├── Models/                   14 models (see Domain model)
├── Providers/
│   ├── Filament/AdminPanelProvider.php     central admin panel
│   ├── Filament/OperatorPanelProvider.php  per-subdomain operator panel
│   ├── TenancyServiceProvider.php          stancl event/middleware wiring
│   └── FortifyServiceProvider.php          auth backend
└── Services/
    ├── PricingService.php            period pricing; promo stacks on vehicle discount
    ├── AvailabilityService.php       overlap + blocked-date checks
    ├── BookingService.php            create/createManual/confirm/reject/markActive/complete/cancel
    ├── RentalAgreementService.php    generate the Contract PDF
    ├── TemplateRenderer.php          operator template overrides + {variable} substitution
    ├── Media/TenantAwarePathGenerator.php  tenant-grouped media paths
    └── Ai/                           AiChatService, BusinessSummaryGenerator,
                                        PricingSuggestionService, VehicleListingWriter

routes/
├── web.php        central marketing + operator self-registration (pinned to central_domain)
├── tenant.php     public booking site (tenancy + EnsureTenantIsActive)
├── settings.php   authenticated profile / security / appearance
└── console.php    the 4 scheduled commands

resources/views/pages/
├── public/        home, vehicle-listing, vehicle-show, vehicle-booking (3-step),
│                    booking-confirmation, booking-review (+ branding layout partials)
├── auth/          login, register, operator-register, password reset, 2FA, verify-email
└── settings/      profile, security, appearance

lang/{en,sq}/      booking, branding, contract, emails, help, marketing, panel, reports
tests/             Unit, Feature (SQLite :memory:), Postgres (lock/migration fidelity)
```

---

## 🔑 Requirements

- **PHP 8.5** (with the usual Laravel extensions: `pdo_pgsql`, `mbstring`, `gd`/`imagick` for WebP image processing, `intl`, etc.)
- **Composer 2**
- **Node.js** (with npm) for the Vite frontend build
- 🐘 **PostgreSQL** — the app is Postgres-only by design. (SQLite `:memory:` shows up only in the fast test suite.)
- An **OpenAI API key** if you want the AI operator features to work — totally optional, everything else runs without it.

---

## 🚀 Getting started

```bash
git clone <repo-url> rent-car-saas
cd rent-car-saas

composer setup   # install deps, copy .env, generate key, migrate, npm install + build
```

`composer setup` runs: `composer install` → copy `.env.example` to `.env` → `php artisan key:generate` → `php artisan migrate --force` → `npm install` → `npm run build`.

Then edit `.env` for your machine — at minimum:

1. **Database** — set the PostgreSQL connection (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). `DB_CONNECTION=pgsql` is already set for you.
2. **Tenancy hosts** — see the local subdomain note right below. Don't skip it.
3. **Mail** — defaults to `MAIL_MAILER=log` (emails land in the log file). Configure a real mailer for production.
4. **OpenAI** (optional) — set `OPENAI_API_KEY` to light up the AI features.

### 💡 The local subdomain story (important)

Multi-tenancy resolves the tenant from the request host, so plain `localhost` isn't enough — you need wildcard subdomains locally. The easiest path is **`lvh.me`**, a public DNS name that resolves `*.lvh.me` → `127.0.0.1`:

| `.env` var | Local value | Meaning |
|------------|-------------|---------|
| `CENTRAL_DOMAIN` | `lvh.me` | Marketing site host. |
| `ADMIN_PANEL_DOMAIN` | `admin.lvh.me` | Super Admin Filament panel. |
| `TENANT_BASE_DOMAIN` | `lvh.me` | A tenant `ardi` then lives at `ardi.lvh.me`. |
| `SESSION_DOMAIN` | `.lvh.me` | Leading-dot parent domain so the session cookie is shared across `admin.` and every tenant subdomain. **Required for admin "Log in as operator" impersonation** — that action stays hidden while `SESSION_DOMAIN` is unset. (Browsers reject a `.localhost` cookie, which is exactly why `lvh.me` is the recommendation.) |

The shipped `.env.example` defaults to the `localhost` family, which is fine for the central/admin panels but won't get you cross-subdomain tenant testing — switch to `lvh.me` for the full multi-tenant experience locally.

---

## 🧰 Running the app

```bash
composer dev     # full dev stack via concurrently:
                 #   php artisan serve  +  queue:listen  +  pail (logs)  +  vite
```

Or run the pieces individually:

```bash
php artisan serve --host=0.0.0.0
php artisan queue:listen --tries=1     # emails and AI jobs are queued
npm run dev                            # Vite dev server (or `npm run build` for assets)
```

> 💡 If a frontend change stubbornly refuses to show up, you probably just need `npm run dev` / `npm run build`.

### 📅 The scheduler

Four commands are registered in `routes/console.php`. In production, run Laravel's scheduler (`php artisan schedule:run` every minute via cron):

| Command | Cadence | What it does |
|---------|---------|--------------|
| `ProcessTenantSubscriptions` | daily | **B2B billing sweep** — renewal reminders before `paid_until`, then auto-suspend lapsed tenants past the grace window. |
| `GenerateBusinessSummaries` | weekly, Mon 06:00 | Queues one `GenerateBusinessSummaryJob` per eligible tenant. |
| `ProcessVehicleMaintenance` | daily 07:00 | Queues `ProcessVehicleMaintenanceJob` per tenant with maintenance reminders on. |
| `RequestPendingReviews` | daily 09:00 | Queues `RequestReviewsJob` per tenant with reviews on. |

---

## ⚡ Environment variables

The keys worth knowing from `.env.example`:

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_NAME` | `Renti` | |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | UI defaults to English; `sq` (Albanian) is the market locale. |
| `DB_CONNECTION` | `pgsql` | **PostgreSQL only.** Set host/db/user/pass. |
| `CENTRAL_DOMAIN` | `localhost` | Marketing host (central routes are pinned here). |
| `ADMIN_PANEL_DOMAIN` | `admin.localhost` | Super Admin panel host. |
| `TENANT_BASE_DOMAIN` | `localhost` | Tenants resolve at `<sub>.<base>`. |
| `SESSION_DOMAIN` | `null` | Set to a leading-dot parent (`.lvh.me` / `.yourdomain.com`) to share the cookie across subdomains — required for impersonation. |
| `SESSION_DRIVER` | `database` | |
| `QUEUE_CONNECTION` | `database` | Emails + AI run on the queue. |
| `CACHE_STORE` | `database` | |
| `MAIL_MAILER` | `log` | Swap to a real mailer (Resend) in production. |
| `OPENAI_API_KEY` | *(blank)* | Enables the AI operator features. |
| `OPENAI_MODEL` | `gpt-5-mini` | Model used by `Ai\AiChatService`. |

---

## 🧪 Testing

Tests run on **SQLite `:memory:`** so the feedback loop stays fast (see `phpunit.xml`), with a separate **PostgreSQL** suite for the behavior SQLite simply can't exercise — row locks and migration fidelity.

```bash
composer test          # full CI gate: config:clear + pint --test + phpstan + Unit,Feature
php artisan test --compact                       # Unit + Feature (SQLite)
php artisan test --compact --filter=testName     # single test by name
php artisan test --compact tests/Feature/Admin/TenantManagementTest.php   # single file
php artisan test --testsuite=Postgres            # lock/migration fidelity (needs real Postgres; CI-only)
```

Three suites live in `phpunit.xml`:

- **Unit** → `tests/Unit`
- **Feature** → `tests/Feature` (grouped: `Admin/`, `Auth/`, `Operator/`, `Settings/`, plus top-level)
- **Postgres** → `tests/Postgres` (the double-booking row-lock coverage; deliberately kept out of `composer test`)

> ✅ House rule: every change gets a test. Add or update one, run the affected suite, and please don't delete existing tests without a good reason — they're part of the app.

---

## 🧼 Code quality

```bash
composer lint                              # fix code style (Pint, parallel)
vendor/bin/pint --dirty --format agent     # format only changed files (run before finalizing)
composer types:check                       # static analysis (PHPStan / Larastan)
```

`composer test` bundles all three (style check + static analysis + tests) as the CI gate, so if that passes you're good.

---

## 🌍 Localization

Two locales live in `lang/`: **`sq`** (Albanian, the primary market) and **`en`** (English), with mirrored files (`booking`, `branding`, `contract`, `emails`, `help`, `marketing`, `panel`, `reports`).

- Public/marketing locale is toggled **per session** via the `/language` POST routes (whitelisted to `sq` / `en`).
- Panel users persist their choice **per account** via `/panel-language/{locale}`, which writes `users.locale` — it survives sessions and devices, and it also decides what language reminder emails arrive in.
- The `set-locale` middleware wraps both the central and tenant route groups.
