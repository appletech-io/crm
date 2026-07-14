<?php

namespace App\Jobs;

use App\Models\EducationBooking;
use App\Services\Education\BookingConfirmationPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateBookingConfirmationPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly EducationBooking $booking,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(BookingConfirmationPdfService $pdfService): void
    {
        try {
            $path = $pdfService->generate($this->booking);
        } catch (Throwable $e) {
            Log::error("Failed to generate booking confirmation PDF for booking {$this->booking->id}: {$e->getMessage()}");

            throw $e;
        }

        $this->booking->update(['confirmation_pdf_path' => $path]);
    }
}
