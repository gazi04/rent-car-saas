<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\TemplateRenderer;
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
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $cancelUrl = null,
    ) {}

    public static function forTenantDomain(Booking $booking): self
    {
        $cancelUrl = null;
        $tenant = Tenant::query()->find($booking->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl !== null && filled($booking->customer_email)) {
            // parse_url() returns false (not null) on a malformed URL, so ?? would
            // leak false into forceScheme()'s ?string parameter.
            $scheme = parse_url($rootUrl, PHP_URL_SCHEME);

            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(is_string($scheme) ? $scheme : 'http');
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
        $tenant = Tenant::query()->find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: resolve(TemplateRenderer::class)->resolve(
                $this->booking,
                'tmpl_email_received_subject',
                'emails.booking_received.subject',
                ['reference' => $this->booking->reference],
            ),
        );
    }

    public function content(): Content
    {
        $renderer = resolve(TemplateRenderer::class);
        $operator = Tenant::query()->find($this->booking->tenant_id)->name;

        return new Content(
            markdown: 'emails.booking-received',
            with: [
                'booking' => $this->booking,
                'cancelUrl' => $this->cancelUrl,
                'intro' => $renderer->resolve($this->booking, 'tmpl_email_received_intro', 'emails.booking_received.intro'),
                'outro' => $renderer->resolve($this->booking, 'tmpl_email_received_outro', 'emails.booking_received.outro', ['operator' => $operator]),
            ],
        );
    }
}
