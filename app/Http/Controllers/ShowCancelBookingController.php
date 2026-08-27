<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowCancelBookingController extends Controller
{
    public function __invoke(Request $request, Booking $booking): View
    {
        if ($booking->status === BookingStatus::Completed || $booking->status === BookingStatus::Cancelled) {
            return view('public.cancel-result', [
                'alreadyDone' => true,
                'notCancellable' => false,
                'booking' => $booking,
            ]);
        }

        if (! $booking->isSelfCancellable()) {
            return view('public.cancel-result', [
                'alreadyDone' => false,
                'notCancellable' => true,
                'booking' => $booking,
            ]);
        }

        return view('public.cancel-confirm', [
            'booking' => $booking,
            'cancelUrl' => $request->fullUrl(),
        ]);
    }
}
