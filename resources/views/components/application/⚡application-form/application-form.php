<?php

use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\ReferenceType;
use App\Models\CandidateSkill;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Services\CvParserService;
use App\Services\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.auth')] class extends Component
{
    use WithFileUploads;

    private const STEP_LABELS = [
        1 => 'Upload CV',
        2 => 'Your Details',
        3 => 'Photo',
        4 => 'Skills & Work',
        5 => 'References',
    ];

    private const REFERENCE_HISTORY_YEARS = 3;

    private const DATE_DISPLAY_FORMAT = 'M j, Y';

    public string $token = '';

    public ?EducationApplication $application = null;

    public int $currentStep = 1;

    public mixed $cv = null;

    public ?string $parseError = null;

    public mixed $photo = null;

    // Personal information
    public ?string $title = null;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $previous_surname = '';

    public ?string $gender = null;

    public ?string $nationality = null;

    public ?string $date_of_birth = null;

    // Address
    public string $address = '';

    public string $city = '';

    public string $county = '';

    public string $country = '';

    public string $postcode = '';

    // Contact
    public string $phone = '';

    public string $mobile = '';

    // Emergency contact
    public string $emergency_contact_name = '';

    public string $emergency_contact_number = '';

    // Employment
    public string $employment_history = '';

    // Skills & work preferences
    public ?int $qualification_id = null;

    public array $availability = [];

    public ?string $available_from = null;

    public array $key_stages = [];

    public array $skills = [];

    // References
    public array $references = [];

    public array $cv_parsed_data = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = EducationApplication::where('token', $token)->first();

        if (! $this->application) {
            abort(404);
        }

        if ($this->application->status === 'completed') {
            abort(403, 'Application has been completed.');
        }

        if ($this->application->status !== 'pending' || $this->application->expires_on < today()) {
            abort(403, 'This application link has expired.');
        }

        if (! $this->application->email_verified) {
            $this->redirect(route('application.verify', ['token' => $token]));
        }

        $this->currentStep = $this->application->current_step ?: 1;

        if ($this->currentStep >= 2) {
            $this->hydrateFromCandidate($this->application->educationCandidate);
        }

        if ($this->currentStep === 2 && ! empty($this->application->cv_parsed_data)) {
            $this->hydrateFromParsedData($this->application->cv_parsed_data, onlyFillBlanks: true);
        }

        if (empty($this->references)) {
            $this->references = [$this->blankReference()];
        }
    }

    public function parseCv(CvParserService $service): void
    {
        $this->parseError = null;

        if (! $this->cv) {
            if (! $this->application->cv_temp_path) {
                $this->addError('cv', 'Please upload your CV.');

                return;
            }

            $this->goToStep(2);

            return;
        }

        $this->validate([
            'cv' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        $documentPath = Document::upload($this->cv, $this->application);
        $this->application->update(['cv_temp_path' => $documentPath]);

        $localPath = 'cv-uploads/'.$this->application->id.'.pdf';
        Storage::disk('local')->put($localPath, Storage::readStream($documentPath));

        try {
            $extracted = $service->parse(Storage::disk('local')->path($localPath));

            $this->first_name = $extracted->firstName ?? '';
            $this->middle_name = $extracted->middleName ?? '';
            $this->last_name = $extracted->lastName ?? '';
            $this->date_of_birth = $this->formatDateForDisplay($extracted->dateOfBirth);
            $this->address = $extracted->address ?? '';
            $this->city = $extracted->city ?? '';
            $this->county = $extracted->county ?? '';
            $this->country = $extracted->country ?? '';
            $this->postcode = $extracted->postcode ?? '';
            $this->phone = $extracted->phone ?? '';
            $this->mobile = $extracted->mobile ?? '';
            $this->gender = $extracted->gender ?? null;
            $this->nationality = $extracted->nationality ?? null;
            $this->employment_history = $extracted->employmentHistory ?? '';
            $this->cv_parsed_data = (array) $extracted;
        } catch (Throwable $e) {
            $this->parseError = 'CV parsing failed. Please fill in your details manually below.';
            report($e);
        } finally {
            Storage::disk('local')->delete($localPath);
        }

        $this->goToStep(2, ['cv_parsed_data' => $this->cv_parsed_data]);
    }

    public function nextStep(): void
    {
        $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:10'],
            'title' => ['nullable', 'string', 'max:10'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'previous_surname' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,non_binary,prefer_not_to_say'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:20'],
            'employment_history' => ['nullable', 'string'],
        ]);

        $this->application->educationCandidate->update([
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'previous_surname' => $this->previous_surname ?: null,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->date_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'county' => $this->county ?: null,
            'country' => $this->country ?: null,
            'postcode' => $this->postcode,
            'phone' => $this->phone ?: null,
            'mobile' => $this->mobile ?: null,
            'emergency_contact_name' => $this->emergency_contact_name ?: null,
            'emergency_contact_number' => $this->emergency_contact_number ?: null,
            'employment_history' => $this->employment_history ?: null,
        ]);

        $this->goToStep(3);
    }

    public function savePhoto(): void
    {
        if (! $this->photo && ! $this->application->educationCandidate->photo_path) {
            $this->addError('photo', 'Please add a photo before continuing.');

            return;
        }

        if ($this->photo) {
            $this->validate([
                'photo' => ['image', 'max:5120'],
            ]);

            $photoPath = Document::upload($this->photo, $this->application);

            $this->application->educationCandidate->update([
                'photo_path' => $photoPath,
            ]);
        }

        $this->goToStep(4);
    }

    public function saveWorkPreferences(): void
    {
        $this->validate([
            'qualification_id' => ['nullable', 'integer', 'exists:qualifications,id'],
            'availability' => ['nullable', 'array'],
            'availability.*' => ['string', Rule::enum(Availability::class)],
            'available_from' => ['nullable', 'date'],
            'key_stages' => ['nullable', 'array'],
            'key_stages.*' => ['string', Rule::enum(KeyStage::class)],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['integer', 'exists:candidate_skills,id'],
        ]);

        $skillIds = collect($this->skills);

        $parentIds = CandidateSkill::whereIn('id', $skillIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        $candidate = $this->application->educationCandidate;

        $candidate->update([
            'qualification_id' => $this->qualification_id,
            'availability' => $this->availability,
            'available_from' => $this->available_from,
            'key_stages' => $this->key_stages,
        ]);

        $candidate->skills()->sync($skillIds->merge($parentIds)->unique()->values());

        $this->goToStep(5);
    }

    public function addReference(): void
    {
        $this->references[] = $this->blankReference();
    }

    public function removeReference(int $index): void
    {
        $reference = $this->references[$index] ?? null;

        if ($reference && ! empty($reference['id'])) {
            $this->application->educationCandidate->references()->whereKey($reference['id'])->delete();
        }

        unset($this->references[$index]);

        $this->references = array_values($this->references);

        if (empty($this->references)) {
            $this->references = [$this->blankReference()];
        }
    }

    public function toggleReferenceCollapsed(int $index): void
    {
        $this->references[$index]['collapsed'] = ! ($this->references[$index]['collapsed'] ?? false);
    }

    public function saveReference(int $index): void
    {
        $this->validate($this->referenceValidationRules((string) $index));

        $this->persistReference($index);

        $this->references[$index]['collapsed'] = true;
    }

    public function submitApplication(): void
    {
        $this->validate($this->referenceValidationRules('*') + [
            'references' => ['required', 'array', 'min:1'],
        ]);

        $this->validateReferenceHistoryCoverage();

        if ($this->getErrorBag()->has('references')) {
            return;
        }

        foreach (array_keys($this->references) as $index) {
            $this->persistReference($index);
        }

        $this->application->update([
            'status' => 'completed',
            'current_step' => 5,
            'completed_at' => now(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function referenceValidationRules(string $index): array
    {
        return [
            "references.{$index}.type" => ['required', 'string', Rule::enum(ReferenceType::class)],
            "references.{$index}.title" => ['nullable', 'string', 'max:10'],
            "references.{$index}.first_name" => ['required', 'string', 'max:255'],
            "references.{$index}.last_name" => ['required', 'string', 'max:255'],
            "references.{$index}.job_title" => ['nullable', 'string', 'max:255'],
            "references.{$index}.worked_from" => ['required', 'date'],
            "references.{$index}.worked_to" => ['nullable', 'date', "after_or_equal:references.{$index}.worked_from"],
            "references.{$index}.email" => ['nullable', 'email', 'max:255'],
            "references.{$index}.mobile" => ['nullable', 'string', 'max:20'],
            "references.{$index}.address" => ['nullable', 'string', 'max:500'],
            "references.{$index}.city" => ['nullable', 'string', 'max:255'],
            "references.{$index}.county" => ['nullable', 'string', 'max:255'],
            "references.{$index}.country" => ['nullable', 'string', 'max:255'],
            "references.{$index}.postcode" => ['nullable', 'string', 'max:10'],
            "references.{$index}.consent_to_contact" => ['accepted'],
        ];
    }

    private function persistReference(int $index): void
    {
        $reference = $this->references[$index];

        $data = [
            'type' => $reference['type'],
            'title' => $reference['title'] ?: null,
            'first_name' => $reference['first_name'],
            'last_name' => $reference['last_name'],
            'job_title' => $reference['job_title'] ?: null,
            'worked_from' => $reference['worked_from'],
            'worked_to' => $reference['worked_to'] ?: null,
            'email' => $reference['email'] ?: null,
            'mobile' => $reference['mobile'] ?: null,
            'address' => $reference['address'] ?: null,
            'city' => data_get($reference, 'city') ?: null,
            'county' => data_get($reference, 'county') ?: null,
            'country' => data_get($reference, 'country') ?: null,
            'postcode' => data_get($reference, 'postcode') ?: null,
            'consent_to_contact' => (bool) $reference['consent_to_contact'],
        ];

        $candidate = $this->application->educationCandidate;

        if (! empty($reference['id'])) {
            $candidate->references()->findOrFail($reference['id'])->update($data);

            return;
        }

        $record = $candidate->references()->create($data);

        $this->references[$index]['id'] = $record->id;
    }

    /** @param array<string, mixed> $reference */
    public function referencePeriodLabel(array $reference): ?string
    {
        if (empty($reference['worked_from'])) {
            return null;
        }

        try {
            $from = Carbon::parse($reference['worked_from']);
            $to = $reference['worked_to'] ? Carbon::parse($reference['worked_to']) : today();
        } catch (Throwable) {
            return null;
        }

        $duration = $from->diffForHumans($to, syntax: Carbon::DIFF_ABSOLUTE, parts: 2);
        $toLabel = $reference['worked_to'] ?: 'Present';

        return $reference['worked_from'].' – '.$toLabel.' ('.$duration.')';
    }

    private function validateReferenceHistoryCoverage(): void
    {
        $coverage = $this->referenceCoverage();

        if (! $coverage['is_complete']) {
            $this->addError(
                'references',
                'Your references must account for your last '.self::REFERENCE_HISTORY_YEARS.' years of work or education history, with no gaps.'
            );
        }
    }

    /** @return array{covered_until: ?Carbon, is_complete: bool, summary: string} */
    #[Computed]
    public function referenceCoverage(): array
    {
        $cutoff = now()->subYears(self::REFERENCE_HISTORY_YEARS)->startOfDay();

        $periods = collect($this->references)
            ->filter(fn (array $reference) => ! empty($reference['worked_from']))
            ->map(fn (array $reference) => [
                'from' => Carbon::parse($reference['worked_from'])->startOfDay(),
                'to' => $reference['worked_to'] ? Carbon::parse($reference['worked_to'])->startOfDay() : today(),
            ])
            ->sortBy('from')
            ->values();

        $coveredUntil = null;

        foreach ($periods as $period) {
            if ($coveredUntil === null) {
                if ($period['from']->gt($cutoff)) {
                    break;
                }

                $coveredUntil = $period['to'];

                continue;
            }

            if ($period['from']->gt($coveredUntil->copy()->addDay())) {
                break;
            }

            if ($period['to']->gt($coveredUntil)) {
                $coveredUntil = $period['to'];
            }
        }

        $isComplete = $coveredUntil !== null && $coveredUntil->gte(today());

        if ($coveredUntil === null) {
            $summary = 'Your references currently account for 0 years, 0 months of the last '.self::REFERENCE_HISTORY_YEARS.' years.';
        } elseif ($isComplete) {
            $summary = 'Your references fully account for the last '.self::REFERENCE_HISTORY_YEARS.' years.';
        } else {
            $coveredLabel = $cutoff->diffForHumans($coveredUntil, syntax: Carbon::DIFF_ABSOLUTE, parts: 2);
            $gapFrom = $coveredUntil->copy()->addDay()->format(self::DATE_DISPLAY_FORMAT);
            $summary = 'Your references currently account for '.$coveredLabel.' of the last '.self::REFERENCE_HISTORY_YEARS.' years. There is a gap starting '.$gapFrom.'.';
        }

        return [
            'covered_until' => $coveredUntil,
            'is_complete' => $isComplete,
            'summary' => $summary,
        ];
    }

    private function blankReference(): array
    {
        return [
            'id' => null,
            'type' => null,
            'title' => null,
            'first_name' => '',
            'last_name' => '',
            'job_title' => '',
            'worked_from' => null,
            'worked_to' => null,
            'email' => '',
            'mobile' => '',
            'address' => '',
            'city' => '',
            'county' => '',
            'country' => '',
            'postcode' => '',
            'consent_to_contact' => false,
            'collapsed' => false,
        ];
    }

    public function viewStep(int $step): void
    {
        if ($step < 1 || $step > $this->application->current_step) {
            return;
        }

        $this->currentStep = $step;
    }

    private function goToStep(int $step, array $extra = []): void
    {
        $this->currentStep = $step;

        $furthestStep = max($step, $this->application->current_step);

        $this->application->update([...$extra, 'current_step' => $furthestStep]);
    }

    #[Computed]
    public function qualificationOptions(): array
    {
        return Qualification::where('company_id', $this->application->educationCandidate->company_id)
            ->where('industry_id', $this->educationIndustryId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** @return Collection<int, CandidateSkill> */
    #[Computed]
    public function skillOptions(): Collection
    {
        return CandidateSkill::where('company_id', $this->application->educationCandidate->company_id)
            ->where('industry_id', $this->educationIndustryId())
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->get();
    }

    private function educationIndustryId(): ?int
    {
        return Industry::where('slug', 'education')->value('id');
    }

    /** @return array<int, string> */
    #[Computed]
    public function stepLabels(): array
    {
        return self::STEP_LABELS;
    }

    #[Computed]
    public function totalSteps(): int
    {
        return count(self::STEP_LABELS);
    }

    #[Computed]
    public function progressPercentage(): int
    {
        return (int) round(($this->currentStep / $this->totalSteps) * 100);
    }

    #[Computed]
    public function existingPhotoUrl(): ?string
    {
        $photoPath = $this->application->educationCandidate->photo_path;

        if (! $photoPath) {
            return null;
        }

        return Storage::disk('local')->temporaryUrl($photoPath, now()->addMinutes(30));
    }

    private function hydrateFromCandidate(EducationCandidate $candidate): void
    {
        $this->title = $candidate->title;
        $this->first_name = $candidate->first_name ?? '';
        $this->middle_name = $candidate->middle_name ?? '';
        $this->last_name = $candidate->last_name ?? '';
        $this->previous_surname = $candidate->previous_surname ?? '';
        $this->gender = $candidate->gender;
        $this->nationality = $candidate->nationality;
        $this->date_of_birth = $candidate->date_of_birth?->format(self::DATE_DISPLAY_FORMAT);
        $this->address = $candidate->address ?? '';
        $this->city = $candidate->city ?? '';
        $this->county = $candidate->county ?? '';
        $this->country = $candidate->country ?? '';
        $this->postcode = $candidate->postcode ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->mobile = $candidate->mobile ?? '';
        $this->emergency_contact_name = $candidate->emergency_contact_name ?? '';
        $this->emergency_contact_number = $candidate->emergency_contact_number ?? '';
        $this->employment_history = $candidate->employment_history ?? '';

        $this->qualification_id = $candidate->qualification_id;
        $this->availability = $candidate->availability ?? [];
        $this->available_from = $candidate->available_from?->format(self::DATE_DISPLAY_FORMAT);
        $this->key_stages = $candidate->key_stages ?? [];
        $this->skills = $candidate->skills->pluck('id')->all();

        $this->references = $candidate->references->map(fn ($reference) => [
            'id' => $reference->id,
            'type' => $reference->type?->value,
            'title' => $reference->title,
            'first_name' => $reference->first_name,
            'last_name' => $reference->last_name,
            'job_title' => $reference->job_title,
            'worked_from' => $reference->worked_from?->format(self::DATE_DISPLAY_FORMAT),
            'worked_to' => $reference->worked_to?->format(self::DATE_DISPLAY_FORMAT),
            'email' => $reference->email,
            'mobile' => $reference->mobile,
            'address' => $reference->address,
            'city' => $reference->city,
            'county' => $reference->county,
            'country' => $reference->country,
            'postcode' => $reference->postcode,
            'consent_to_contact' => $reference->consent_to_contact,
            'collapsed' => true,
        ])->all();

        if (empty($this->references)) {
            $this->references = [$this->blankReference()];
        }
    }

    private function hydrateFromParsedData(array $data, bool $onlyFillBlanks = false): void
    {
        $map = [
            'first_name' => $data['firstName'] ?? '',
            'middle_name' => $data['middleName'] ?? '',
            'last_name' => $data['lastName'] ?? '',
            'date_of_birth' => $this->formatDateForDisplay($data['dateOfBirth'] ?? null),
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'county' => $data['county'] ?? '',
            'country' => $data['country'] ?? '',
            'postcode' => $data['postcode'] ?? '',
            'phone' => $data['phone'] ?? '',
            'mobile' => $data['mobile'] ?? '',
            'employment_history' => $data['employmentHistory'] ?? '',
        ];

        foreach ($map as $property => $value) {
            if ($onlyFillBlanks && ! empty($this->$property)) {
                continue;
            }

            $this->$property = $value;
        }

        $this->cv_parsed_data = $data;
    }

    private function formatDateForDisplay(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format(self::DATE_DISPLAY_FORMAT);
        } catch (Throwable) {
            return null;
        }
    }
};
