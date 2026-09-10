<?php

use App\Http\Controllers\HostDiagnosticsController;
use App\Http\Controllers\ResendWebhookController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Central (marketing) host only. Pinned so these don't collide with the operator
// Filament panel, which serves "/dashboard" on every tenant subdomain (no fixed domain).
// The public booking site owns "/" on tenant subdomains (routes/tenant.php).
Route::domain(config('tenancy.central_domain'))->middleware('set-locale')->group(function (): void {
    Route::view('/', 'marketing.home')->name('home');
    // Operator self-registration: creates a pending tenant awaiting admin approval.
    Route::livewire('signup', 'pages::auth.operator-register')->name('operator.register');

    // Session locale toggle — POST, redirect back. Mirrors routes/tenant.php's version.
    Route::post('/language', function (Request $request): RedirectResponse {
        $locale = $request->input('locale');
        if (in_array($locale, ['sq', 'en'], strict: true)) {
            session(['locale' => $locale]);
        }

        return back();
    })->name('marketing.language');
});

// Resend delivery webhook (email log status updates). Central host, no tenant
// middleware; unauthenticated but Svix-signature-verified in the controller,
// and CSRF-exempt (see bootstrap/app.php).
Route::domain(config('tenancy.central_domain'))
    ->post('webhooks/resend', ResendWebhookController::class)
    ->name('webhooks.resend');

// Deploy-time host probe. DELIBERATELY carries no Route::domain() constraint: it
// has to answer while tenant resolution is broken, and a load balancer that has
// rewritten Host is by definition not sending the central domain — pinning it
// would 404 in exactly the case it exists to diagnose. Domain-less is also what
// lets the runbook's "hit a tenant subdomain" instruction work.
//
// Off by default and gated inside the controller (not by conditional
// registration, which route:cache would freeze). See docs/deploy-runbook.md.
Route::get('/_diagnostics/host', HostDiagnosticsController::class)
    ->name('diagnostics.host')
    ->middleware('throttle:host-diagnostics');
