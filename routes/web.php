<?php

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

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
