<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        $tenant = Tenant::find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: __('emails.booking_cancelled.subject', ['reference' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-cancelled',
            with: ['booking' => $this->booking],
        );
    }
}
