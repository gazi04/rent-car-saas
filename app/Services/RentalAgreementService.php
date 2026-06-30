<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RentalAgreementService
{
    public function generate(Booking $booking, bool $force = false): Contract
    {
        $contract = $booking->contract;

        if ($contract && ! $force && Storage::exists($contract->path)) {
            return $contract;
        }

        $path = "tenants/{$booking->tenant_id}/contracts/{$booking->reference}.pdf";

        App::setLocale($booking->locale ?? 'sq');

        $pdf = Pdf::loadView('pdf.rental-agreement', ['booking' => $booking->load('vehicle')])
            ->setPaper('a4');

        Storage::put($path, $pdf->output());

        return $booking->contract()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'tenant_id' => $booking->tenant_id,
                'path' => $path,
                'generated_at' => now(),
                'token' => Str::random(40),
            ],
        );
    }
}
