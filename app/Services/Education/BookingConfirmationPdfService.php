<?php

namespace App\Services\Education;

use App\Enums\DocumentType;
use App\Models\Booking;
use App\Models\CandidateDocument;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Booking\BookingDayPeriods;
use App\Services\Candidates\Document;
use App\Services\Education\BookingConfirmationChecks as EducationBookingConfirmationChecks;
use App\Services\Healthcare\BookingConfirmationChecks as HealthcareBookingConfirmationChecks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\FpdiException;

class BookingConfirmationPdfService
{
    /**
     * @throws RuntimeException when the booking's candidate is of a type
     *                          that has no vetting checks defined, rather
     *                          than failing later with a TypeError deep in
     *                          the check builders.
     */
    public function generate(Booking $booking): string
    {
        $candidate = $booking->candidate;

        if (! $candidate instanceof EducationCandidate && ! $candidate instanceof HealthcareCandidate) {
            throw new RuntimeException(
                "Cannot build a confirmation PDF for booking {$booking->id}: candidate type ".
                ($booking->candidate_type ?? 'none').' is not supported.'
            );
        }

        $html = view('pdfs.booking-confirmation', [
            'booking' => $booking,
            'candidate' => $candidate,
            'checks' => collect($this->checksFor($candidate)),
            'bookingDates' => BookingDayPeriods::rows($booking, 'charge'),
            'photoDataUri' => $this->photoDataUri($candidate),
            'logoDataUri' => $this->logoDataUri($booking),
        ])->render();

        $summaryPdf = Pdf::loadHTML($html)->output();

        $merged = $this->mergeWithCandidateDocuments($summaryPdf, $candidate);

        $filename = "booking-{$booking->id}-confirmation.pdf";

        return Document::putGenerated($merged, $candidate, $filename, 'bookings');
    }

    protected function logoDataUri(Booking $booking): string
    {
        $company = $booking->company;

        $contents = $company ? $company->logoContents() : file_get_contents(public_path('images/appletech.png'));
        $mimeType = $company ? $company->logoMimeType() : 'image/png';

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    protected function photoDataUri(EducationCandidate|HealthcareCandidate $candidate): ?string
    {
        /** @var CandidateDocument|null $photo */
        $photo = $candidate->documents->firstWhere('document_type', DocumentType::Photo);

        $disk = Storage::disk(config('filesystems.default'));

        if (! $photo || ! $disk->exists($photo->path)) {
            return null;
        }

        $contents = $disk->get($photo->path);
        $mimeType = $disk->mimeType($photo->path) ?: 'image/jpeg';

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    /**
     * Which vetting rows the summary table gets — the two sectors collect
     * different checks, so each owns its own list.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function checksFor(EducationCandidate|HealthcareCandidate $candidate): array
    {
        return match (true) {
            $candidate instanceof EducationCandidate => EducationBookingConfirmationChecks::for($candidate),
            $candidate instanceof HealthcareCandidate => HealthcareBookingConfirmationChecks::for($candidate),
        };
    }

    protected function mergeWithCandidateDocuments(string $summaryPdf, EducationCandidate|HealthcareCandidate $candidate): string
    {
        $pdf = new Fpdi;

        $this->importPdfBytes($pdf, $summaryPdf);

        foreach ([DocumentType::DbsFront, DocumentType::DbsBack, DocumentType::SafeguardingTraining] as $type) {
            /** @var CandidateDocument|null $document */
            $document = $candidate->documents()->where('document_type', $type)->first();

            if (! $document) {
                continue;
            }

            try {
                $this->appendStoredDocument($pdf, $document->path);
            } catch (FpdiException $e) {
                Log::warning(
                    "Could not embed {$type->label()} (document {$document->id}) in the confirmation PDF "
                    ."for candidate {$candidate->id}: {$e->getMessage()}"
                );

                $this->appendUnembeddableDocumentPage($pdf, $type);
            }
        }

        return $pdf->Output('S');
    }

    /**
     * A scan FPDI can't import — most often a DBS certificate saved with
     * PDF encryption/permissions set, which FPDI refuses outright — must
     * not take the whole confirmation PDF down with it: the summary page
     * and the candidate's other documents are still worth sending.
     *
     * It gets a page saying so rather than being dropped silently, because
     * these are compliance documents. A pack that quietly arrives without
     * a DBS certificate looks complete to whoever reads it, which is worse
     * than one that says the scan needs re-uploading.
     */
    protected function appendUnembeddableDocumentPage(Fpdi $pdf, DocumentType $type): void
    {
        $pdf->AddPage('P');
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->MultiCell(0, 8, "{$type->label()} - not included");
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 6,
            'This document is on file but could not be added to this pack, because the uploaded file is '
            .'password-protected or otherwise unreadable. Please request an unprotected copy and re-upload it, '
            .'then regenerate this confirmation.'
        );
    }

    protected function importPdfBytes(Fpdi $pdf, string $bytes): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'booking-pdf-');
        file_put_contents($tempPath, $bytes);

        try {
            $this->importPdfFile($pdf, $tempPath);
        } finally {
            unlink($tempPath);
        }
    }

    protected function importPdfFile(Fpdi $pdf, string $path): void
    {
        $pageCount = $pdf->setSourceFile($path);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
    }

    /**
     * Fpdi/getimagesize need a real local file path — stored documents live
     * on whichever disk is configured (S3 in production), so the file is
     * downloaded to a temp local path for the duration of this call.
     */
    protected function appendStoredDocument(Fpdi $pdf, string $storagePath): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($storagePath)) {
            return;
        }

        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $tempPath = tempnam(sys_get_temp_dir(), 'booking-doc-').'.'.$extension;
        file_put_contents($tempPath, $disk->get($storagePath));

        try {
            if ($extension === 'pdf') {
                $this->importPdfFile($pdf, $tempPath);

                return;
            }

            $dimensions = @getimagesize($tempPath);

            if (! $dimensions) {
                return;
            }

            [$width, $height] = $dimensions;

            $pdf->AddPage($width >= $height ? 'L' : 'P');
            $pdf->Image($tempPath, 0, 0, $pdf->GetPageWidth());
        } finally {
            unlink($tempPath);
        }
    }
}
