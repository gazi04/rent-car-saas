# AGENTS.md

Guidance for AI coding agents working in this repository. Kept in sync with `CLAUDE.md`.

## Project Overview

`rent-car-saas` (product name **RentACar SaaS**) is a **multi-tenant, white-label car-rental SaaS** for the Kosovo / Western Balkans market. Car-rental businesses ("operators") subscribe to get their own branded booking website on a subdomain (`operatorname.yourdomain.com`); their customers book cars there with no account required. The platform owner ("Super Admin") onboards operators, charges a monthly subscription, and never touches the money that flows between operators and their customers.

The full spec lives in **`docs/RentACar_Application_Plan.pdf`** (17 pages) — read it before any domain work. Key facts and stack guidance below.

**Status:** Currently the **Laravel Livewire starter kit** scaffolding only (auth, settings, profile, passkeys, 2FA). The rent-car domain (tenants, vehicles, bookings, billing) is **not yet implemented** — build it per the plan.

> **Ignore the PDF's stack versions — use the latest.** The plan PDF names Laravel 11 / Livewire 3 / Filament 3, but those are stale — use **Filament 4**, not 3. Always build on the **latest stable version** of each technology; the installed versions in this repo (Laravel 13 / Livewire 4 / Flux UI — see Boost foundational context below) are the source of truth. Use the PDF only for *domain and feature* intent, never for stack/version decisions. Filament, Cashier, a tenancy package, dompdf, Intervention Image, Flatpickr, and Resend are **planned but not yet installed** — when adding them, pull the latest stable release, and get approval before adding any dependency.

## Commands

```bash
composer dev          # Run full dev stack: serve + queue:listen + pail logs + vite (concurrently)
composer setup        # First-time setup: install, .env, key:gen, migrate, npm install + build
npm run dev           # Vite dev server only
npm run build         # Build frontend assets (run if UI changes don't appear)

composer test         # Full CI gate: config:clear + pint --test + phpstan + artisan test
php artisan test --compact                          # Run tests
php artisan test --compact --filter=testName        # Run a single test by name
php artisan test --compact tests/Feature/Auth/AuthenticationTest.php  # Single file

composer lint         # Fix code style (pint --parallel)
vendor/bin/pint --dirty --format agent              # Format only changed files (run before finalizing)
composer types:check  # Static analysis (phpstan/larastan, level in phpstan.neon)
```

Tests use SQLite `:memory:` (see `phpunit.xml`); the dev DB is SQLite (`database/database.sqlite`).

## Product / Domain Model

**Three roles:** Super Admin (platform owner, `admin.yourdomain.com`, separate auth guard, `users.tenant_id = NULL`, `role = admin`) · Operator (subscribing rental business, `operatorname.yourdomain.com/dashboard`) · Customer (renter, public subdomain, no login).

**Five modules:**
1. **Public Booking Website** — white-label per operator (logo/colors/font/footer via `tenant_settings` key-value table → CSS variables injected in Blade layout). Vehicle listing + filters, 3-step booking flow (dates → details → review), no account. Bookings created as `pending`. Bilingual (Albanian/English).
2. **Operator Dashboard** — fleet management (vehicle CRUD, photos→WebP, custom JSONB fields, soft deletes), availability calendar, booking management (confirm/reject/active/complete/cancel), pricing (hourly/daily/weekly/monthly + promo codes + discounts), rental-agreement PDF per booking, dashboard notifications, reports + CSV export.
3. **Admin Dashboard** (planned Filament 4) — tenant management, approve/reject/suspend/impersonate operators, MRR & revenue overview, platform health.
4. **Billing & Subscriptions** (planned Laravel Cashier + Stripe) — operator subscriptions (Trial/Basic €15/Standard €29/Pro €49), webhook-driven status, 30-day trial → grace → suspension via scheduled command. **Two strictly separate payment flows:** B2B (operator→platform, automated via Stripe) and B2C (customer→operator, cash/bank transfer, platform never touches it).
5. **Notifications & Emails** (planned Resend) — queued Blade-template Mailables, bilingual, per-operator branding on customer-facing emails.

**Multi-tenancy:** Single shared database, single codebase. `tenant_id` on every tenant-scoped table; middleware resolves tenant from subdomain and an Eloquent **global scope** auto-filters all queries. Availability conflict checks **must** run server-side inside a DB transaction with a row-level lock on the vehicle (double-booking destroys operator trust). Planned core tables: `tenants`, `tenant_settings`, `vehicles`, `vehicle_photos`, `bookings`, `blocked_dates`, `contracts`, `promo_codes` (+ Laravel `notifications`/`jobs`/`failed_jobs`).

## Architecture

- **Routing is Livewire-first.** Pages are registered with `Route::livewire('path', 'pages::dir.name')` in `routes/web.php` and `routes/settings.php`, not via controllers. There is effectively one real controller (`app/Http/Controllers/Controller.php`, base only).
- **Livewire 4 single-file components (SFC).** Page components live in `resources/views/pages/**`. Files prefixed with `⚡` (e.g. `⚡profile.blade.php`, `⚡security.blade.php`) are Livewire SFCs — PHP class + Blade markup in one `.blade.php` file. The `pages::` route namespace maps to this directory. Auth pages (`pages/auth/*`) and settings pages (`pages/settings/*`) follow this convention.
- **Authentication via Fortify**, customized through action classes in `app/Actions/Fortify/` (`CreateNewUser`, `ResetUserPassword`) and `app/Providers/FortifyServiceProvider.php`. Validation rules are shared via traits in `app/Concerns/` (`PasswordValidationRules`, `ProfileValidationRules`). Logout is a Livewire action: `app/Livewire/Actions/Logout.php`.
- **2FA + Passkeys (WebAuthn).** Two-factor columns added to `users` table; passkeys stored in `passkeys` table. Frontend passkey logic in `resources/js/passkeys.js` (uses `@laravel/passkeys`). `.well-known/passkey-endpoints` route exposes enroll/manage URLs.
- **UI is Flux UI (free tier)** + Tailwind v4. Custom Flux overrides and icons live in `resources/views/flux/`. Layouts in `resources/views/layouts/` (app shell with sidebar/header, plus auth card/simple/split variants).

## Skills

Domain skills live under `**/skills/**` and the project mandates activating the relevant one before working in that domain (Livewire, Flux UI, Fortify, Pest, Tailwind, Laravel best practices).

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- filament/filament (FILAMENT) - v5
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
