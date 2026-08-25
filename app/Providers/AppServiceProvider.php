<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogImpersonationStart;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use STS\FilamentImpersonate\Events\EnterImpersonation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->composeMarketingPricing();
    }

    /**
     * Feed live plan prices to the public marketing page.
     *
     * The pricing table used to hardcode €15/€29/€49 in the Blade markup while
     * the real prices live in the `plans` table and are editable from the admin
     * panel. An admin changing a price there would have left the landing page
     * quietly advertising the old one. A view composer keeps the route as a
     * plain Route::view (so `route:cache` still works) and runs the query only
     * when that page is actually rendered.
     */
    private function composeMarketingPricing(): void
    {
        View::composer('marketing.home', function (ViewContract $view): void {
            $view->with('plans', Plan::activeInDisplayOrder());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Turn every lazy-loaded relationship into a failure in dev and CI, where
        // an N+1 is a test failure — not in production, where the same strictness
        // would turn a missed eager-load into a 500 for a customer mid-booking.
        // Same production/non-production split as prohibitDestructiveCommands above.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        // Queued mail (booking notifications, subscription reminders) never
        // sends faster than this, regardless of how many mailables a single
        // event queues at once — avoids tripping the SMTP provider's
        // per-second cap (see ThrottlesMailQueue).
        RateLimiter::for('mail', fn () => Limit::perSecond(1));

        // Pulse dashboard access. Platform diagnostics span every tenant, so this is
        // Super-Admin-only — reusing User::isAdmin() rather than restating the
        // predicate, so "is a platform admin" has exactly one definition.
        // The dashboard is additionally pinned to the admin host via PULSE_DOMAIN;
        // see the note in config/pulse.php for why that pinning is not optional.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        // Audit trail for admin "log in as operator" — one activity_log row per
        // start (causer = admin, subject = tenant), surfaced in the admin panel's
        // read-only Audit log resource (§15.5).
        Event::listen(EnterImpersonation::class, LogImpersonationStart::class);
    }
}
