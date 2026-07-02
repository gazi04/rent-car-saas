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
use Illuminate\Support\Facades\URL;

class BookingReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $cancelUrl = null,
    ) {}

    public static function forTenantDomain(Booking $booking): self
    {
        $cancelUrl = null;
        $tenant = Tenant::find($booking->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl && $booking->customer_email) {
            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(parse_url($rootUrl, PHP_URL_SCHEME) ?: 'http');
            URL::forceRootUrl($rootUrl);

            $cancelUrl = URL::temporarySignedRoute(
                'public.booking.cancel',
                now()->addDay(),
                ['booking' => $booking->id],
            );

            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        return new self($booking, $cancelUrl);
    }

    public function envelope(): Envelope
    {
        $tenant = Tenant::find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: __('emails.booking_received.subject', ['reference' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-received',
            with: [
                'booking' => $this->booking,
                'cancelUrl' => $this->cancelUrl,
            ],
        );
    }
}
