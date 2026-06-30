<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\RentalAgreementService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAgreementController extends Controller
{
    public function __invoke(Booking $booking, RentalAgreementService $service): StreamedResponse
    {
        $contract = $service->generate($booking);

        return Storage::download($contract->path, "agreement-{$booking->reference}.pdf");
    }
}
