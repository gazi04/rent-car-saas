<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Central (marketing) host only. Pinned so these don't collide with the operator
// Filament panel, which serves "/dashboard" on every tenant subdomain (no fixed domain).
Route::domain(config('tenancy.central_domain'))->group(function () {
    // Operator self-registration: creates a pending tenant awaiting admin approval.
    Route::livewire('signup', 'pages::auth.operator-register')->name('operator.register');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
