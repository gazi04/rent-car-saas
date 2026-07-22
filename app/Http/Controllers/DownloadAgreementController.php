<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\RentalAgreementService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAgreementController extends Controller
{
    public function __invoke(Booking $booking, RentalAgreementService $service): StreamedResponse
    {
        abort_unless(in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::Active,
            BookingStatus::Completed,
        ], true), 404);

        $contract = $service->generate($booking);

        return Storage::download($contract->path, sprintf('agreement-%s.pdf', $booking->reference));
    }
}
