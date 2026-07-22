<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
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

/**
 * Next-day invitation asking the customer to review a completed rental. The button is a tokenless ~30-day signed link to the public
 * review page; the signature must validate on the tenant subdomain, so the URL
 * is generated against the tenant's public root (queue workers have no request).
 */
class BookingReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $reviewUrl = null,
    ) {}

    public static function forTenantDomain(Booking $booking): self
    {
        $reviewUrl = null;
        $tenant = Tenant::query()->find($booking->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl && $booking->customer_email) {
            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(parse_url((string) $rootUrl, PHP_URL_SCHEME) ?: 'http');
            URL::forceRootUrl($rootUrl);

            $reviewUrl = URL::temporarySignedRoute(
                'public.booking.review',
                now()->addDays(30),
                ['booking' => $booking->id],
            );

            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        return new self($booking, $reviewUrl);
    }

    public function envelope(): Envelope
    {
        $tenant = Tenant::query()->find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: __('emails.review_request.subject', ['operator' => $tenant->name]),
        );
    }

    public function content(): Content
    {
        $operator = Tenant::query()->find($this->booking->tenant_id)->name;

        return new Content(
            markdown: 'emails.booking-review-request',
            with: [
                'booking' => $this->booking,
                'reviewUrl' => $this->reviewUrl,
                'operator' => $operator,
            ],
        );
    }
}
