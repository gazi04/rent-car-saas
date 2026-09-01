<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Contract;
use App\Models\Tenant;
use App\Services\Media\MediaFileResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class RentalAgreementService
{
    public function generate(Booking $booking, bool $force = false): Contract
    {
        $contract = $booking->contract;

        if ($contract !== null && ! $force && Storage::exists($contract->path)) {
            return $contract;
        }

        $path = sprintf('tenants/%s/contracts/%s.pdf', $booking->tenant_id, $booking->reference);

        $previousLocale = App::getLocale();
        App::setLocale($booking->locale ?? 'sq');

        try {
            $pdf = Pdf::loadView('pdf.rental-agreement', [
                'booking' => $booking->load('vehicle'),
                // Operator's custom terms wording (feature #7), or the built-in default.
                'terms' => resolve(TemplateRenderer::class)->resolve($booking, 'tmpl_agreement_terms', 'contract.terms_body'),
                // dompdf has enable_remote => false, so the logo must be inlined as
                // a data: URI rather than an <img src> the renderer would fetch —
                // and read through the disk layer so it works when media is on S3.
                'logoDataUri' => $this->logoDataUri($booking),
            ])->setPaper('a4');

            Storage::put($path, $pdf->output());
        } finally {
            App::setLocale($previousLocale);
        }

        return $booking->contract()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'tenant_id' => $booking->tenant_id,
                'path' => $path,
                'generated_at' => now(),
            ],
        );
    }

    private function logoDataUri(Booking $booking): ?string
    {
        $media = Tenant::find($booking->tenant_id)?->getFirstMedia('logo');

        if ($media === null) {
            return null;
        }

        return resolve(MediaFileResolver::class)->dataUri($media, 'thumb');
    }
}
