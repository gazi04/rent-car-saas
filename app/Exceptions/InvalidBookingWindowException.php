<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * The requested rental window is not bookable in itself — it starts in the past,
 * or it runs longer than config('bookings.max_rental_days'). Distinct from
 * VehicleNotAvailableException, which means the window is fine but taken: the
 * booking wizard recovers from the two differently.
 */
class InvalidBookingWindowException extends Exception {}
