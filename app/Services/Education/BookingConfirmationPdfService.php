<?php

namespace App\Services\Education;

use App\Enums\DocumentType;
use App\Enums\ReferenceStatus;
use App\Models\CandidateDocument;
use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class BookingConfirmationPdfService
{
    public function generate(EducationBooking $booking): string
    {
        /** @var EducationCandidate $candidate */
        $candidate = $booking->education_candidate;

        $html = view('pdfs.booking-confirmation', [
            'booking' => $booking,
            'candidate' => $candidate,
            'checks' => collect($this->checks($candidate)),
            'bookingDates' => BookingDayPeriods::rows($booking, 'charge'),
            'photoDataUri' => $this->photoDataUri($candidate),
        ])->render();

        $summaryPdf = Pdf::loadHTML($html)->output();

        $merged = $this->mergeWithCandidateDocuments($summaryPdf, $candidate);

        $filename = "booking-{$booking->id}-confirmation.pdf";

        return Document::putGenerated($merged, $candidate, $filename, 'bookings');
    }

    protected function photoDataUri(EducationCandidate $candidate): ?string
    {
        /** @var CandidateDocument|null $photo */
        $photo = $candidate->documents->firstWhere('document_type', DocumentType::Photo);

        if (! $photo || ! Storage::disk('local')->exists($photo->path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($photo->path);
        $mimeType = Storage::disk('local')->mimeType($photo->path) ?: 'image/jpeg';

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    /** @return array<int, array{label: string, value: string}> */
    protected function checks(EducationCandidate $candidate): array
    {
        return [
            ['label' => 'Date of Birth', 'value' => $candidate->date_of_birth?->format('jS M Y') ?? 'N/A'],
            ['label' => 'NI Number', 'value' => $candidate->ni_number ?? 'N/A'],
            ['label' => 'Address Checked', 'value' => $candidate->proof_of_address_checked_at?->format('jS M Y') ?? 'N/A'],
            ['label' => 'Right to Work Type', 'value' => $this->rightToWorkLabel($candidate)],
            ['label' => 'Reference(s) Checked', 'value' => $this->referencesCheckedLabel($candidate)],
            ['label' => 'Qualification', 'value' => $candidate->qualification?->name ?? 'N/A'],
            ['label' => 'Safeguarding Training', 'value' => $candidate->safeguarding_certified_date?->format('jS M Y') ?? 'N/A'],
            ['label' => 'TRN', 'value' => $candidate->trn_number ?? 'N/A'],
            ['label' => 'TRA/NCTL Sanctions', 'value' => $candidate->sanctions === 'yes' ? 'Sanctions' : 'No Sanctions'],
            ['label' => 'DBS No', 'value' => $candidate->dbs_certificate_number ?? 'N/A'],
            ['label' => 'DBS Checked Date', 'value' => $candidate->dbs_checked_date?->format('jS M Y') ?? 'N/A'],
            ['label' => 'DBS Update Service', 'value' => $candidate->update_service_checked_at?->format('jS M Y') ?? 'N/A'],
            ['label' => 'Any Medical Issue', 'value' => $this->medicalIssueLabel($candidate)],
            ['label' => 'Overseas Police Clearance', 'value' => $this->overseasClearanceLabel($candidate)],
        ];
    }

    protected function rightToWorkLabel(EducationCandidate $candidate): string
    {
        return match ($candidate->right_to_work_type) {
            'passport' => 'UK Passport',
            'visa' => 'Visa',
            'birth_certificate' => 'UK Birth Certificate',
            default => 'N/A',
        };
    }

    protected function referencesCheckedLabel(EducationCandidate $candidate): string
    {
        if (! $candidate->references()->exists()) {
            return 'N/A';
        }

        $allConfirmed = ! $candidate->references()->where('status', '!=', ReferenceStatus::Confirmed)->exists();

        return $allConfirmed ? 'Yes' : 'No';
    }

    protected function medicalIssueLabel(EducationCandidate $candidate): string
    {
        if ($candidate->has_health_condition_or_disability !== 'yes') {
            return 'N/A';
        }

        return $candidate->health_condition_details ?: 'Yes';
    }

    protected function overseasClearanceLabel(EducationCandidate $candidate): string
    {
        if ($candidate->lived_overseas_six_months !== 'yes') {
            return 'Not Required';
        }

        return $candidate->overseas_police_clearance_check === 'yes' ? 'Cleared' : 'Outstanding';
    }

    protected function mergeWithCandidateDocuments(string $summaryPdf, EducationCandidate $candidate): string
    {
        $pdf = new Fpdi;

        $this->importPdfBytes($pdf, $summaryPdf);

        foreach ([DocumentType::DbsFront, DocumentType::DbsBack, DocumentType::SafeguardingTraining] as $type) {
            /** @var CandidateDocument|null $document */
            $document = $candidate->documents()->where('document_type', $type)->first();

            if ($document) {
                $this->appendStoredDocument($pdf, $document->path);
            }
        }

        return $pdf->Output('S');
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

    protected function appendStoredDocument(Fpdi $pdf, string $storagePath): void
    {
        if (! Storage::disk('local')->exists($storagePath)) {
            return;
        }

        $absolutePath = Storage::disk('local')->path($storagePath);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $this->importPdfFile($pdf, $absolutePath);

            return;
        }

        $dimensions = @getimagesize($absolutePath);

        if (! $dimensions) {
            return;
        }

        [$width, $height] = $dimensions;

        $pdf->AddPage($width >= $height ? 'L' : 'P');
        $pdf->Image($absolutePath, 0, 0, $pdf->GetPageWidth());
    }
}
