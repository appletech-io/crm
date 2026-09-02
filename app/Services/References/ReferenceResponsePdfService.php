<?php

namespace App\Services\References;

use App\Models\CandidateReference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ReferenceResponsePdfService
{
    /** Raw PDF bytes — the caller decides whether to stream it as a download or persist it. */
    public function generate(CandidateReference $reference): string
    {
        $companyName = $reference->candidate?->company?->trading_name ?: config('app.name');

        $html = view('pdfs.reference-response', [
            'reference' => $reference,
            'sections' => ReferenceFormSchema::sectionsFor($reference->type, $companyName),
            'needsPositionAndOrganisation' => ReferenceFormSchema::needsPositionAndOrganisation($reference->type),
            'candidateName' => trim("{$reference->candidate?->first_name} {$reference->candidate?->last_name}"),
            'refereeName' => trim("{$reference->first_name} {$reference->last_name}"),
            'logoDataUri' => $this->logoDataUri($reference),
        ])->render();

        return Pdf::loadHTML($html)->output();
    }

    public function filename(CandidateReference $reference): string
    {
        $candidateName = Str::slug(trim("{$reference->candidate?->first_name} {$reference->candidate?->last_name}")) ?: 'candidate';

        return "reference-{$candidateName}-{$reference->id}.pdf";
    }

    private function logoDataUri(CandidateReference $reference): string
    {
        $company = $reference->candidate?->company;

        $contents = $company ? $company->logoContents() : file_get_contents(public_path('images/appletech.png'));
        $mimeType = $company ? $company->logoMimeType() : 'image/png';

        return "data:{$mimeType};base64,".base64_encode($contents);
    }
}
