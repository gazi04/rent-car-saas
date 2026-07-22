<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogImpersonationStart;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        // Audit trail for admin "log in as operator" — one activity_log row per
        // start (causer = admin, subject = tenant), surfaced in the admin panel's
        // read-only Audit log resource (§15.5).
        Event::listen(EnterImpersonation::class, LogImpersonationStart::class);
    }
}
