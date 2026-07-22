<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Tells someone on the waitlist that their dates opened up (backlog #2). The
 * button links to the public booking page for the vehicle — no signed route
 * needed, unlike the review mail, because it's just a public page.
 *
 * This is a head start, NOT a reservation: nothing holds the vehicle, so the
 * copy says first-come-first-served. The URL is built against the tenant's
 * public root because queue workers have no request context.
 */
class WaitlistSlotOpenMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly ?string $bookingUrl = null,
    ) {}

    public static function forTenantDomain(WaitlistEntry $entry): self
    {
        $bookingUrl = null;
        $tenant = Tenant::query()->find($entry->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl) {
            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(parse_url((string) $rootUrl, PHP_URL_SCHEME) ?: 'http');
            URL::forceRootUrl($rootUrl);

            $bookingUrl = URL::route('public.vehicle.book', ['vehicle' => $entry->vehicle_id]);

            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        return new self($entry, $bookingUrl);
    }

    public function envelope(): Envelope
    {
        $tenant = Tenant::query()->find($this->entry->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: __('emails.waitlist_slot_open.subject', ['operator' => $tenant->name]),
        );
    }

    public function content(): Content
    {
        $tenant = Tenant::query()->find($this->entry->tenant_id);

        return new Content(
            markdown: 'emails.waitlist-slot-open',
            with: [
                'entry' => $this->entry,
                'vehicle' => $this->entry->vehicle,
                'bookingUrl' => $this->bookingUrl,
                'operator' => $tenant->name,
            ],
        );
    }
}
