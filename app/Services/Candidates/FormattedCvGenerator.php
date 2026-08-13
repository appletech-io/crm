<?php

namespace App\Services\Candidates;

use App\Enums\DocumentType;
use App\Models\CandidateDocument;
use App\Models\FormattedCv;
use App\Services\Ai\CvParserService;
use App\Services\Education\BookingConfirmationPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a company-branded, contact-detail-free version of a candidate's CV.
 * Uses the existing CV-extraction AI pipeline ({@see CvParserService},
 * otherwise only used for bulk import) to reproduce the CV's full content
 * faithfully as HTML — not a summary rebuilt from a few fields — with only
 * the contact-details section omitted, then adds a logo header and converts
 * that to a PDF — mirroring
 * {@see BookingConfirmationPdfService}'s render-then-dompdf shape.
 */
class FormattedCvGenerator
{
    public function __construct(private readonly CvParserService $cvParser) {}

    public function generate(Model $candidate, CandidateDocument $cvDocument): FormattedCv
    {
        $tempPath = $this->downloadToTempFile($cvDocument->path);

        try {
            $extraction = $this->cvParser->parse($tempPath);
        } finally {
            unlink($tempPath);
        }

        $html = view('pdfs.formatted-cv', [
            'name' => trim(collect([$extraction->firstName, $extraction->middleName, $extraction->lastName])->filter()->implode(' ')) ?: 'Candidate',
            'bodyHtml' => $extraction->bodyHtml,
            'logoDataUri' => $this->logoDataUri(),
        ])->render();

        $formattedCv = FormattedCv::updateOrCreate([
            'candidate_type' => $candidate->getMorphClass(),
            'candidate_id' => $candidate->getKey(),
        ], [
            'content' => $html,
        ]);

        $formattedCv->update([
            'pdf_path' => $this->generatePdf($html, $candidate, $cvDocument),
        ]);

        return $formattedCv;
    }

    /**
     * Re-renders just the PDF from whatever is currently saved in
     * $formattedCv->content — used after someone edits the formatted CV by
     * hand, so the PDF preview never falls out of sync with their edit
     * without re-running the (AI, slower, and edit-destroying) parse step.
     * A no-op if the source CV has since been removed, since the filename
     * convention is derived from it.
     */
    public function regeneratePdf(Model $candidate, FormattedCv $formattedCv): void
    {
        $cvDocument = $candidate->documents->firstWhere('document_type', DocumentType::Cv);

        if (! $cvDocument) {
            return;
        }

        $formattedCv->update([
            'pdf_path' => $this->generatePdf($formattedCv->content ?? '', $candidate, $cvDocument),
        ]);
    }

    /**
     * The AI parser (and getimagesize()-style file inspection) needs a real
     * local file — the source CV may live on S3 in production, so its bytes
     * are downloaded to a temp file for the duration of this call.
     */
    private function downloadToTempFile(string $storagePath): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'formatted-cv-').($extension ? ".{$extension}" : '');

        file_put_contents($tempPath, $disk->get($storagePath));

        return $tempPath;
    }

    private function logoDataUri(): string
    {
        $contents = file_get_contents(public_path('images/applebough.png'));

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private function generatePdf(string $html, Model $candidate, CandidateDocument $cvDocument): string
    {
        $pdf = Pdf::loadHTML($html)->output();

        $companySlug = Str::slug($candidate->company?->name) ?: 'formatted';
        $baseName = pathinfo($cvDocument->path, PATHINFO_FILENAME);
        $filename = "{$baseName}-{$companySlug}.pdf";

        return Document::putGeneratedViewable($pdf, $candidate, $filename, 'formatted-cv');
    }
}
