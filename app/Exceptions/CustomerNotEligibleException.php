<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * The resolved customer may not self-serve a booking on the public storefront —
 * today that means the operator has blacklisted their phone number.
 *
 * Deliberately NOT thrown by BookingService::createManual(): the flag exists to
 * stop an abuser booking themselves in, not to stop the operator booking them in
 * knowingly at the front desk.
 */
class CustomerNotEligibleException extends Exception {}
