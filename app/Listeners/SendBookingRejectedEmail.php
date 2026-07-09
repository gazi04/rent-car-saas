<?php

namespace App\Listeners;

use App\Events\BookingRejected;
use App\Mail\BookingRejectedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingRejectedEmail implements ShouldQueue
{
    public function handle(BookingRejected $event): void
    {
        $booking = $event->booking;

        if (! $booking->customer_email) {
            return;
        }

        Mail::to($booking->customer_email)
            ->locale($booking->locale ?? 'sq')
            ->queue(new BookingRejectedMail($booking));
    }
}
