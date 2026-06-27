<?php

use Illuminate\Support\Facades\Route;

// Central (marketing) host only. Pinned so these don't collide with the operator
// Filament panel, which serves "/dashboard" on every tenant subdomain (no fixed domain).
// The public booking site owns "/" on tenant subdomains (routes/tenant.php).
Route::domain(config('tenancy.central_domain'))->group(function () {
    Route::view('/', 'welcome')->name('home');
    // Operator self-registration: creates a pending tenant awaiting admin approval.
    Route::livewire('signup', 'pages::auth.operator-register')->name('operator.register');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
