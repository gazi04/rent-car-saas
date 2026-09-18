<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Tells someone on a stock alert that their vehicle is bookable again (#3).
 *
 * A sibling of WaitlistSlotOpenMail rather than a branch inside it: that copy is
 * built around the dates the recipient asked for, and a stock alert has none —
 * the whole point is that they want this vehicle whenever it returns. It links to
 * the vehicle page, not the booking page, because they still have to pick dates.
 *
 * This is a head start, NOT a reservation: nothing holds the vehicle, so the copy
 * says first-come-first-served. The URL is built against the tenant's public root
 * because queue workers have no request context.
 */
class VehicleBackInStockMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly ?string $vehicleUrl = null,
    ) {}

    public static function forTenantDomain(WaitlistEntry $entry): self
    {
        $vehicleUrl = null;
        $tenant = Tenant::query()->find($entry->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl !== null) {
            // parse_url() returns false (not null) on a malformed URL, so ?? would
            // leak false into forceScheme()'s ?string parameter.
            $scheme = parse_url($rootUrl, PHP_URL_SCHEME);

            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(is_string($scheme) ? $scheme : 'http');
            URL::forceRootUrl($rootUrl);

            $vehicleUrl = URL::route('public.vehicle', ['vehicle' => $entry->vehicle_id]);

            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        return new self($entry, $vehicleUrl);
    }

    public function envelope(): Envelope
    {
        $tenant = Tenant::query()->findOrFail($this->entry->tenant_id);

        return new Envelope(
            from: $tenant->senderAddress(),
            replyTo: array_filter([$tenant->replyToAddress()]),
            subject: __('emails.vehicle_back_in_stock.subject', [
                'vehicle' => $this->entry->vehicle->name,
                'operator' => $tenant->name,
            ]),
        );
    }

    public function content(): Content
    {
        $tenant = Tenant::query()->findOrFail($this->entry->tenant_id);

        return new Content(
            markdown: 'emails.vehicle-back-in-stock',
            with: [
                'entry' => $this->entry,
                'vehicle' => $this->entry->vehicle,
                'vehicleUrl' => $this->vehicleUrl,
                'operator' => $tenant->name,
            ],
        );
    }
}
