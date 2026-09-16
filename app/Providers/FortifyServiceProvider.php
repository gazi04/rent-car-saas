<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\VerifyEmailResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Route operators/staff to their tenant subdomain panel after email
        // verification instead of Fortify's central "/dashboard" default.
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (): Factory|View => view('pages::auth.login'));
        Fortify::verifyEmailView(fn (): Factory|View => view('pages::auth.verify-email'));
        Fortify::confirmPasswordView(fn (): Factory|View => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn (): Factory|View => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn (): Factory|View => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Two limits, because either one alone leaves the other attack open: a
        // per-IP cap does nothing against a distributed flood aimed at one known
        // operator address, and a per-address cap does nothing against a script
        // walking a list of addresses. The password broker's own throttle is
        // 60s per *user* and bounds neither.
        //
        // Each accepted request is a queued Resend send, so the cost of leaving
        // this open is the operator's inbox and the platform's mail spend.
        // Applied by App\Http\Middleware\ThrottlePasswordResetRequests, not
        // here: Fortify exposes no config lever for these two routes, and its
        // routes cannot be reliably mutated from a booted() callback (see that
        // middleware's docblock).
        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perHour(5)->by('pw-reset-email:'.Str::transliterate($request->string('email')->lower()->value())),
            Limit::perHour(15)->by('pw-reset-ip:'.$request->ip()),
        ]);
    }
}
