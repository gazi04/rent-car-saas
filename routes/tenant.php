<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Http\Controllers\CancelBookingController;
use App\Http\Controllers\DownloadAgreementController;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Routes that only resolve on a tenant (operator) subdomain. The middleware
| stack resolves the tenant from the host and aborts on central domains.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantIsActive::class,
    'set-locale',
])->group(function () {
    // ── Public booking site ──────────────────────────────────────────────
    Route::livewire('/', 'pages::public.home')->name('public.home');

    Route::livewire('/vehicles', 'pages::public.vehicle-listing')
        ->name('public.vehicles');

    Route::livewire('/vehicles/{vehicle}', 'pages::public.vehicle-show')
        ->whereNumber('vehicle')
        ->name('public.vehicle');

    Route::livewire('/vehicles/{vehicle}/book', 'pages::public.vehicle-booking')
        ->whereNumber('vehicle')
        ->name('public.vehicle.book');

    Route::livewire('/booking/{booking:reference}/confirmation', 'pages::public.booking-confirmation')
        ->name('public.booking.confirmation');

    // Signed 24-hour cancellation link — no auth required.
    Route::get('/booking/{booking}/cancel', CancelBookingController::class)
        ->name('public.booking.cancel')
        ->middleware('signed');

    // Signed 7-day rental-agreement download link — no auth required.
    Route::get('/booking/{booking:reference}/agreement', DownloadAgreementController::class)
        ->name('agreement.download')
        ->middleware('signed');

    // Session locale toggle — POST, redirect back.
    Route::post('/language', function (Request $request) {
        $locale = $request->input('locale');
        if (in_array($locale, ['sq', 'en'], strict: true)) {
            session(['locale' => $locale]);
        }

        return back();
    })->name('public.language');

    // Availability JSON endpoint — feeds Flatpickr disabled ranges.
    // Route-model-bound and tenant-scoped (global scope); no cross-tenant leakage.
    Route::get('/vehicles/{vehicle}/availability', function (Vehicle $vehicle) {
        return response()->json([
            'unavailable' => $vehicle->bookings()
                ->whereIn('status', BookingStatus::blocking())
                ->get(['start_date', 'end_date']),
            'blocked' => $vehicle->blockedDates()->get(['start_date', 'end_date']),
        ]);
    })->name('vehicle.availability');
});
