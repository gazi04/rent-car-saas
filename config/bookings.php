<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Booking lifecycle
|--------------------------------------------------------------------------
|
| A Pending booking occupies its vehicle (BookingStatus::blocking()), so an
| unanswered one takes the car off the market until a human clears it. The
| hourly bookings:expire-pending sweep cancels those that have gone stale,
| which is the only thing that bounds how long an unconfirmed booking can
| hold inventory. The same window signs the customer's cancellation link,
| since that link is only honoured while the booking is still Pending.
|
| The rental length is bounded separately: the sweep caps how long an
| *unanswered* booking may hold a vehicle, but says nothing about how long a
| booking may legitimately run. Without a ceiling, one request could book a
| car for a decade — and the browser's date picker is not a guard.
|
*/
return [
    /** Hours a booking may sit Pending before the sweep cancels it and frees the vehicle. */
    'pending_expiry_hours' => 48,

    /** Longest rental that may be booked, in days. Applies to customer and operator-entered bookings alike. */
    'max_rental_days' => 90,
];
