<?php

namespace App\Jobs;

use App\DTOs\CvExtraction;
use App\Enums\DocumentType;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Services\Ai\CvParserService;
use App\Services\Education\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessBulkCvUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param  array<int, int>  $skillIds */
    public function __construct(
        public readonly string $filePath,
        public readonly int $companyId,
        public readonly string $industrySlug,
        public readonly int $candidateStatusId,
        public readonly array $skillIds,
        public readonly bool $sendApplicationEmail,
    ) {}

    public function handle(CvParserService $cvParser): void
    {
        $modelClass = Industry::candidateModelForSlug($this->industrySlug);

        if (! $modelClass) {
            Storage::disk('local')->delete($this->filePath);

            return;
        }

        $extraction = $this->parse($cvParser);

        if (filled($extraction->email) && $this->candidateExistsForEmail($modelClass, $extraction->email)) {
            $this->skipAsDuplicate($extraction->email);

            return;
        }

        try {
            $candidate = $modelClass::create([
                'company_id' => $this->companyId,
                'first_name' => $extraction->firstName ?: pathinfo($this->filePath, PATHINFO_FILENAME),
                'middle_name' => $extraction->middleName,
                'last_name' => $extraction->lastName,
                'email' => $extraction->email,
                'phone' => $extraction->phone,
                'mobile' => $extraction->mobile,
                'date_of_birth' => $extraction->dateOfBirth,
                'gender' => $extraction->gender,
                'nationality' => $extraction->nationality,
                'address' => $extraction->address,
                'city' => $extraction->city,
                'county' => $extraction->county,
                'country' => $extraction->country,
                'postcode' => $extraction->postcode,
                'education_and_qualification' => $extraction->educationAndQualification,
                'notes' => collect([$extraction->summary, $extraction->skills ? "Skills (from CV): $extraction->skills" : null])
                    ->filter()
                    ->implode("\n\n") ?: null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->skipAsDuplicate($extraction->email);

            return;
        }

        $this->createEmploymentHistories($candidate, $extraction);

        $documentPath = Document::move($this->filePath, $candidate, 'cv');

        $candidate->documents()->updateOrCreate(
            ['document_type' => DocumentType::Cv],
            ['path' => $documentPath],
        );

        $candidate->statuses()->firstOrCreate(['candidate_status_id' => $this->candidateStatusId]);

        if (filled($this->skillIds)) {
            $candidate->skills()->sync($this->skillIds);
        }

        if ($this->sendApplicationEmail) {
            $application = $candidate->application()->create([
                'email' => $candidate->email,
                'status' => 'pending',
                'token' => Str::uuid(),
                'expires_on' => now()->addDays(7)->toDateString(),
            ]);

            SendApplicationEmail::dispatch($candidate, $application);
        }
    }

    private function parse(CvParserService $cvParser): CvExtraction
    {
        try {
            return $cvParser->parse(Storage::disk('local')->path($this->filePath));
        } catch (Throwable $e) {
            report($e);

            return new CvExtraction;
        }
    }

    private function skipAsDuplicate(string $email): void
    {
        Storage::disk('local')->delete($this->filePath);

        Log::warning('Skipped bulk CV upload: a candidate with this email already exists.', [
            'company_id' => $this->companyId,
            'email' => $email,
        ]);
    }

    /** @param  class-string<EducationCandidate>  $modelClass */
    protected function candidateExistsForEmail(string $modelClass, string $email): bool
    {
        return $modelClass::withTrashed()
            ->where('company_id', $this->companyId)
            ->where('email', $email)
            ->exists();
    }

    private function createEmploymentHistories(EducationCandidate $candidate, CvExtraction $extraction): void
    {
        $histories = collect($extraction->employmentHistory)
            ->filter(fn (array $entry): bool => filled($entry['companyName'] ?? null) && filled($entry['jobTitle'] ?? null))
            ->map(fn (array $entry): array => [
                'company_name' => $entry['companyName'],
                'job_title' => $entry['jobTitle'],
                'worked_from' => $entry['workedFrom'] ?? null,
                'worked_to' => $entry['workedTo'] ?? null,
            ])
            ->values()
            ->all();

        if (filled($histories)) {
            $candidate->employmentHistories()->createMany($histories);
        }
    }
}
