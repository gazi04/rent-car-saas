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
*/
return [
    /** Hours a booking may sit Pending before the sweep cancels it and frees the vehicle. */
    'pending_expiry_hours' => 48,
];
