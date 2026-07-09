<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use App\Models\Tenant;
use App\Services\RentalAgreementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendBookingConfirmedEmail implements ShouldQueue
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        if (! $booking->customer_email) {
            return;
        }

        app(RentalAgreementService::class)->generate($booking);

        $agreementUrl = null;
        $tenant = Tenant::find($booking->tenant_id);
        $rootUrl = $tenant?->publicRootUrl();

        if ($rootUrl) {
            // forceRootUrl alone is not enough: the generator swaps in the current
            // request's scheme, so an https root would still emit http links.
            URL::forceScheme(parse_url($rootUrl, PHP_URL_SCHEME) ?: 'http');
            URL::forceRootUrl($rootUrl);

            $agreementUrl = URL::temporarySignedRoute(
                'agreement.download',
                now()->addDays(7),
                ['booking' => $booking->reference],
            );

            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        Mail::to($booking->customer_email)
            ->locale($booking->locale ?? 'sq')
            ->queue(new BookingConfirmedMail($booking, $agreementUrl));
    }
}
