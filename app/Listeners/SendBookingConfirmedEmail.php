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
        $domain = $tenant?->domains()->first()?->domain;

        if ($domain) {
            $appUrl = config('app.url');
            $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
            $port = parse_url($appUrl, PHP_URL_PORT);
            URL::forceRootUrl($scheme.'://'.$domain.($port ? ':'.$port : ''));

            $agreementUrl = URL::temporarySignedRoute(
                'agreement.download',
                now()->addDays(7),
                ['booking' => $booking->reference],
            );

            URL::forceRootUrl(null);
        }

        Mail::to($booking->customer_email)
            ->locale($booking->locale)
            ->queue(new BookingConfirmedMail($booking, $agreementUrl));
    }
}
