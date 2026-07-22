<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CancelBookingController extends Controller
{
    public function __invoke(Request $request, Booking $booking, BookingService $bookingService): View
    {
        if ($booking->status === BookingStatus::Completed || $booking->status === BookingStatus::Cancelled) {
            return view('public.cancel-result', [
                'alreadyDone' => true,
                'notCancellable' => false,
                'booking' => $booking,
            ]);
        }

        if ($booking->status !== BookingStatus::Pending) {
            return view('public.cancel-result', [
                'alreadyDone' => false,
                'notCancellable' => true,
                'booking' => $booking,
            ]);
        }

        $bookingService->cancel($booking, cancelledBy: 'customer');

        return view('public.cancel-result', [
            'alreadyDone' => false,
            'notCancellable' => false,
            'booking' => $booking,
        ]);
    }
}
