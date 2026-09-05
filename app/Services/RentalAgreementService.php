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
use RuntimeException;

class RentalAgreementService
{
    /**
     * The unqualified Storage facade below writes to the default disk (`local`),
     * which config/tenancy.php lists under filesystem.disks — so its root is
     * rewritten to storage/tenant{id}/app/ while tenancy is live, and left at
     * storage/app/private/ when it is not. The path string itself already embeds
     * tenants/{tenant_id}/ and does NOT vary, so the same contract resolves to two
     * different files depending on ambient tenancy state, and the mismatch is
     * silent: Storage::exists() simply returns false and the PDF is regenerated.
     *
     * Every caller runs inside the owning tenant's context today (the queued
     * SendBookingConfirmedEmail included, via QueueTenancyBootstrapper). This guard
     * keeps it that way, loudly — the document carries customer PII, so generating
     * one for an arbitrary tenant from central context should never be reachable.
     */
    public function generate(Booking $booking, bool $force = false): Contract
    {
        // Tenant::current() is the house accessor — it returns null when tenancy was
        // never initialized, so this covers both "no tenant" and "the wrong tenant".
        $current = Tenant::current();

        throw_unless(
            $current !== null && $current->id === $booking->tenant_id,
            RuntimeException::class,
            'Rental agreements must be generated inside the owning tenant\'s context.',
        );

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

        // tenant_id is deliberately absent: it is not mass-assignable on a
        // BelongsToTenant model, and the throw_unless above has already proven
        // tenancy is initialized to exactly $booking->tenant_id, so the trait's
        // creating hook writes the same value.
        return $booking->contract()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
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
