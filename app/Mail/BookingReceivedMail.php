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
        $domain = $tenant?->domains()->first()?->domain;

        if ($domain && $booking->customer_email) {
            $appUrl = config('app.url');
            $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
            $port = parse_url($appUrl, PHP_URL_PORT);
            URL::forceRootUrl($scheme.'://'.$domain.($port ? ':'.$port : ''));

            $cancelUrl = URL::temporarySignedRoute(
                'public.booking.cancel',
                now()->addDay(),
                ['booking' => $booking->id],
            );

            URL::forceRootUrl(null);
        }

        return new self($booking, $cancelUrl);
    }

    public function envelope(): Envelope
    {
        $tenant = Tenant::find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: __('emails.booking_received.subject'),
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
