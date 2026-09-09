<?php

namespace App\Services\Candidates;

use App\Ai\Agents\CandidateProfileWriter;
use App\Models\CandidateEmploymentHistory;
use App\Models\CandidateProfile;
use App\Models\Industry;
use App\Models\SampleProfile;
use App\Services\Ai\DocumentAttachment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes an AI-generated candidate profile, in the style of up to 5 sample
 * profiles an admin has uploaded for the candidate's own company + sector,
 * built entirely from the candidate's own work history/qualifications/
 * skills already in the app — unlike {@see FormattedCvGenerator}, this never
 * reproduces an uploaded CV, it composes new prose from structured data.
 */
class CandidateProfileGenerator
{
    public function generate(Model $candidate): CandidateProfile
    {
        $samples = $this->samplesFor($candidate);

        $response = (new CandidateProfileWriter)->prompt(
            $this->promptFor($candidate),
            attachments: $samples->map(fn (SampleProfile $sample): mixed => $this->attachmentFor($sample))->all(),
        );

        $contentHtml = view('pdfs.candidate-profile-content', [
            'name' => $this->candidateName($candidate),
            'summary' => $response['summary'] ?? null,
            'experienceHtml' => $response['experienceHtml'] ?? null,
            'qualificationsHtml' => $response['qualificationsHtml'] ?? null,
            'skillsHtml' => $response['skillsHtml'] ?? null,
        ])->render();

        $profile = CandidateProfile::updateOrCreate([
            'candidate_type' => $candidate->getMorphClass(),
            'candidate_id' => $candidate->getKey(),
        ], [
            'content' => $contentHtml,
        ]);

        $profile->update([
            'pdf_path' => $this->generatePdf($contentHtml, $candidate),
        ]);

        return $profile;
    }

    /**
     * Re-renders just the PDF from whatever is currently saved in
     * $profile->content — used after someone edits the profile by hand, so
     * the PDF preview never falls out of sync with their edit without
     * re-running the (AI, slower, and edit-destroying) generation step.
     */
    public function regeneratePdf(Model $candidate, CandidateProfile $profile): void
    {
        $profile->update([
            'pdf_path' => $this->generatePdf($profile->content ?? '', $candidate),
        ]);
    }

    /**
     * A candidate's sector is fixed by its model class, not by whichever
     * industry the viewing consultant currently has selected — so sample
     * profiles are scoped to the candidate's own industry, resolved the
     * same way {@see Document} resolves a
     * candidate's storage directory.
     *
     * @return Collection<int, SampleProfile>
     */
    private function samplesFor(Model $candidate): Collection
    {
        $slug = Industry::slugForCandidateModel($candidate::class);
        $industryId = $slug ? Industry::where('slug', $slug)->value('id') : null;

        if (! $industryId) {
            return collect();
        }

        return SampleProfile::query()
            ->where('company_id', $candidate->company_id)
            ->where('industry_id', $industryId)
            ->get();
    }

    private function attachmentFor(SampleProfile $sample): mixed
    {
        $disk = Storage::disk(config('filesystems.default'));
        $extension = pathinfo($sample->path, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'sample-profile-').($extension ? ".{$extension}" : '');

        file_put_contents($tempPath, $disk->get($sample->path));

        try {
            return DocumentAttachment::for($tempPath);
        } finally {
            unlink($tempPath);
        }
    }

    private function promptFor(Model $candidate): string
    {
        $lines = [
            'Write a candidate profile for: '.$this->candidateName($candidate),
        ];

        if ($candidate->qualification?->name) {
            $lines[] = "Qualification: {$candidate->qualification->name}";
        }

        if (method_exists($candidate, 'skills')) {
            $skills = $candidate->skills->pluck('name');

            if ($skills->isNotEmpty()) {
                $lines[] = 'Skills: '.$skills->implode(', ');
            }
        }

        $employmentHistory = $this->employmentHistoryFor($candidate);

        if ($employmentHistory !== '') {
            $lines[] = "Employment history:\n{$employmentHistory}";
        }

        $educationAndQualification = $candidate->education_and_qualification ?? null;

        if (filled($educationAndQualification)) {
            $lines[] = "Education and qualifications:\n{$educationAndQualification}";
        }

        return implode("\n\n", $lines);
    }

    /**
     * Prefers the structured employmentHistories relation (both sectors);
     * EducationCandidate additionally carries a raw employment_history text
     * column (populated by CV parsing, see CvParser) that's used only as a
     * fallback when no structured rows exist — HealthcareCandidate has no
     * such column at all.
     */
    private function employmentHistoryFor(Model $candidate): string
    {
        if (method_exists($candidate, 'employmentHistories')) {
            $rows = $candidate->employmentHistories;

            if ($rows->isNotEmpty()) {
                return $rows
                    ->map(function (CandidateEmploymentHistory $row): string {
                        $dates = trim(($row->worked_from?->format('M Y') ?? '?').' - '.($row->worked_to?->format('M Y') ?? 'present'));

                        return "- {$row->job_title} at {$row->company_name} ({$dates})";
                    })
                    ->implode("\n");
            }
        }

        return (string) ($candidate->employment_history ?? '');
    }

    private function candidateName(Model $candidate): string
    {
        return trim("{$candidate->first_name} {$candidate->last_name}") ?: 'Candidate';
    }

    private function logoDataUri(Model $candidate): string
    {
        $company = $candidate->company;

        $contents = $company ? $company->logoContents() : file_get_contents(public_path('images/appletech.png'));
        $mimeType = $company ? $company->logoMimeType() : 'image/png';

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    private function generatePdf(string $contentHtml, Model $candidate): string
    {
        $html = view('pdfs.candidate-profile', [
            'contentHtml' => $contentHtml,
            'logoDataUri' => $this->logoDataUri($candidate),
        ])->render();

        $pdf = Pdf::loadHTML($html)->output();

        $nameSlug = Str::slug($this->candidateName($candidate)) ?: 'candidate';
        $filename = "{$nameSlug}-profile.pdf";

        return Document::putGeneratedViewable($pdf, $candidate, $filename, 'profiles');
    }
}
