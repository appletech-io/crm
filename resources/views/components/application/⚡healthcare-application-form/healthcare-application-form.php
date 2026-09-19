<?php

use App\Actions\Applications\HealthcareApplicationCompleted;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Healthcare\CareSetting;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateSkill;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\ReferenceForm;
use App\Models\User;
use App\Services\Ai\CvParserService;
use App\Services\ApplicationAccessSession;
use App\Services\Candidates\Document;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.application')] class extends Component
{
    use WithFileUploads;

    private const STEP_LABELS = [
        1 => 'Upload CV',
        2 => 'Your Details',
        3 => 'Right to Work & Skills',
        4 => 'Employment History',
        5 => 'References',
        6 => 'Create Your Account',
    ];

    private const REFERENCE_HISTORY_YEARS = 5;

    private const REFERENCE_HISTORY_COVERAGE_THRESHOLD = 0.7;

    private const DATE_DISPLAY_FORMAT = 'M j, Y';

    public string $token = '';

    public ?HealthcareApplication $application = null;

    public int $currentStep = 1;

    public $cv = null;

    public ?string $parseError = null;

    /** @var array<string, mixed> */
    public array $cv_parsed_data = [];

    public ?string $title = null;

    public ?string $first_name = null;

    public ?string $last_name = null;

    public ?string $phone = null;

    public ?string $mobile = null;

    public ?string $address = null;

    public ?string $postcode = null;

    public ?string $city = null;

    public ?int $qualification_id = null;

    /** @var array<int, int> */
    public array $skills = [];

    /** @var array<int, string> */
    public array $availability = [];

    /** @var array<int, string> */
    public array $care_settings = [];

    public ?string $right_to_work_type = null;

    public ?string $right_to_work_expiry_date = null;

    public ?string $has_dbs = null;

    public ?string $dbs_expiry_date = null;

    /** @var array<int, array<string, mixed>> */
    public array $employmentHistories = [];

    /** @var array<int, array<string, mixed>> */
    public array $references = [];

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = HealthcareApplication::where('token', $token)->first();

        if (! $this->application) {
            abort(404);
        }

        if (! ApplicationAccessSession::hasVerified($token)) {
            $this->redirect(route('application.healthcare.verify', ['token' => $token]));

            return;
        }

        $this->currentStep = $this->application->current_step ?: 1;

        if ($this->currentStep >= 2) {
            $this->hydrateFromCandidate($this->application->candidate);
        }

        if ($this->currentStep === 2 && ! empty($this->application->cv_parsed_data)) {
            $this->hydrateFromParsedData($this->application->cv_parsed_data, onlyFillBlanks: true);
        }

        if (empty($this->employmentHistories)) {
            $this->employmentHistories = $this->seedEmploymentHistoriesFromCvData($this->application->cv_parsed_data ?? []);
        }

        if (empty($this->employmentHistories)) {
            $this->employmentHistories = [$this->blankEmploymentHistory()];
        }

        if (empty($this->references)) {
            $this->references = [$this->blankReference()];
        }
    }

    public function parseCv(CvParserService $service): void
    {
        $this->parseError = null;

        if (! $this->cv) {
            if (! $this->existingCvPath) {
                $this->addError('cv', 'Please upload your CV.');

                return;
            }

            $this->goToStep(2);

            return;
        }

        $this->validate([
            'cv' => ['file', 'mimes:pdf,docx', 'max:10240'],
        ]);

        $candidate = $this->application->candidate;

        $documentPath = Document::upload($this->cv, $candidate, 'cv');
        $cvDocument = $candidate->documents()->updateOrCreate(
            ['document_type' => DocumentType::Cv],
            ['path' => $documentPath],
        );

        GenerateFormattedCv::dispatch($candidate, $cvDocument);

        $localPath = 'cv-uploads/'.$this->application->id.'.'.pathinfo($documentPath, PATHINFO_EXTENSION);
        Storage::disk('local')->put($localPath, Storage::readStream($documentPath));

        try {
            $extracted = $service->parse(Storage::disk('local')->path($localPath));

            $this->first_name = $extracted->firstName ?? '';
            $this->last_name = $extracted->lastName ?? '';
            $this->address = $extracted->address ?? '';
            $this->city = $extracted->city ?? '';
            $this->postcode = $extracted->postcode ?? '';
            $this->phone = $extracted->phone ?? '';
            $this->mobile = $extracted->mobile ?? '';
            $this->cv_parsed_data = (array) $extracted;

            if ($this->employmentHistoriesAreUntouched()) {
                $seededEmploymentHistories = $this->seedEmploymentHistoriesFromCvData($this->cv_parsed_data);

                if (! empty($seededEmploymentHistories)) {
                    $this->employmentHistories = $seededEmploymentHistories;
                }
            }
        } catch (Throwable $e) {
            $this->parseError = 'CV parsing failed. Please fill in your details manually below.';
            report($e);
        } finally {
            Storage::disk('local')->delete($localPath);
        }

        $this->goToStep(2, ['cv_parsed_data' => $this->cv_parsed_data]);
    }

    public function savePersonalDetails(): void
    {
        $this->validate([
            'title' => ['nullable', 'string', 'in:Mr,Mrs,Miss,Ms,Dr,Prof'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $this->application->candidate->update([
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'city' => $this->city,
        ]);

        $this->goToStep(3);
    }

    public function saveSkillsAndRightToWork(): void
    {
        $this->validate([
            'qualification_id' => ['nullable', 'integer', 'exists:qualifications,id'],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['integer', 'exists:candidate_skills,id'],
            'right_to_work_type' => ['required', 'in:birth_certificate,passport,visa'],
            'right_to_work_expiry_date' => ['nullable', 'date'],
            'has_dbs' => ['required', 'in:yes,no'],
            'dbs_expiry_date' => ['nullable', 'date'],
        ]);

        $skillIds = collect($this->skills);

        $parentIds = CandidateSkill::whereIn('id', $skillIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        $candidate = $this->application->candidate;

        $candidate->update([
            'qualification_id' => $this->qualification_id,
            'availability' => $this->availability,
            'care_settings' => $this->care_settings,
            'right_to_work_type' => $this->right_to_work_type,
            'right_to_work_expiry_date' => in_array($this->right_to_work_type, ['visa', 'passport'], true) ? $this->right_to_work_expiry_date : null,
            'has_dbs' => $this->has_dbs,
            'dbs_expiry_date' => $this->has_dbs === 'yes' ? $this->dbs_expiry_date : null,
        ]);

        $candidate->skills()->sync($skillIds->merge($parentIds)->unique()->values());

        $this->goToStep(4);
    }

    public function addEmploymentHistory(): void
    {
        $this->employmentHistories[] = $this->blankEmploymentHistory();
    }

    public function removeEmploymentHistory(int $index): void
    {
        $entry = $this->employmentHistories[$index] ?? null;

        if ($entry && ! empty($entry['id'])) {
            $this->application->candidate->employmentHistories()->whereKey($entry['id'])->delete();
        }

        unset($this->employmentHistories[$index]);

        $this->employmentHistories = array_values($this->employmentHistories);

        if (empty($this->employmentHistories)) {
            $this->employmentHistories = [$this->blankEmploymentHistory()];
        }
    }

    public function toggleEmploymentHistoryCollapsed(int $index): void
    {
        $this->employmentHistories[$index]['collapsed'] = ! ($this->employmentHistories[$index]['collapsed'] ?? false);
    }

    public function saveEmploymentHistory(int $index): void
    {
        $this->validate($this->employmentHistoryValidationRules((string) $index));

        $this->persistEmploymentHistory($index);

        $this->employmentHistories[$index]['collapsed'] = true;
    }

    public function submitEmploymentHistory(): void
    {
        $this->validate($this->employmentHistoryValidationRules('*') + [
            'employmentHistories' => ['required', 'array', 'min:1'],
        ]);

        foreach (array_keys($this->employmentHistories) as $index) {
            $this->persistEmploymentHistory($index);
        }

        $this->goToStep(5);
    }

    /** @return array<string, array<int, mixed>> */
    private function employmentHistoryValidationRules(string $index): array
    {
        return [
            "employmentHistories.{$index}.company_name" => ['required', 'string', 'max:255'],
            "employmentHistories.{$index}.job_title" => ['required', 'string', 'max:255'],
            "employmentHistories.{$index}.worked_from" => ['required', 'date'],
            "employmentHistories.{$index}.worked_to" => ['nullable', 'date', "after_or_equal:employmentHistories.{$index}.worked_from"],
        ];
    }

    private function persistEmploymentHistory(int $index): void
    {
        $entry = $this->employmentHistories[$index];

        $data = [
            'company_name' => $entry['company_name'],
            'job_title' => $entry['job_title'],
            'worked_from' => $entry['worked_from'],
            'worked_to' => $entry['worked_to'] ?: null,
        ];

        $candidate = $this->application->candidate;

        if (! empty($entry['id'])) {
            $candidate->employmentHistories()->findOrFail($entry['id'])->update($data);

            return;
        }

        $record = $candidate->employmentHistories()->create($data);

        $this->employmentHistories[$index]['id'] = $record->id;
    }

    private function employmentHistoriesAreUntouched(): bool
    {
        if (count($this->employmentHistories) !== 1) {
            return false;
        }

        $entry = $this->employmentHistories[0];

        return blank($entry['company_name'] ?? null)
            && blank($entry['job_title'] ?? null)
            && blank($entry['worked_from'] ?? null)
            && blank($entry['worked_to'] ?? null);
    }

    private function blankEmploymentHistory(): array
    {
        return [
            'id' => null,
            'company_name' => '',
            'job_title' => '',
            'worked_from' => null,
            'worked_to' => null,
            'collapsed' => false,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function seedEmploymentHistoriesFromCvData(array $data): array
    {
        $entries = $data['employmentHistory'] ?? null;

        if (! is_array($entries) || empty($entries)) {
            return [];
        }

        return collect($entries)
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn (array $entry) => [
                'id' => null,
                'company_name' => $entry['companyName'] ?? '',
                'job_title' => $entry['jobTitle'] ?? '',
                'worked_from' => $this->formatDateForInput($entry['workedFrom'] ?? null),
                'worked_to' => $this->formatDateForInput($entry['workedTo'] ?? null),
                'collapsed' => false,
            ])
            ->values()
            ->all();
    }

    public function addReference(): void
    {
        $this->references[] = $this->blankReference();
    }

    public function removeReference(int $index): void
    {
        $reference = $this->references[$index] ?? null;

        if ($reference && ! empty($reference['id'])) {
            $this->application->candidate->references()->whereKey($reference['id'])->delete();
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
        $this->validate($this->referenceValidationRules((string) $index), attributes: $this->referenceAttributeNames());

        $this->persistReference($index);

        $this->references[$index]['collapsed'] = true;
    }

    public function submitReferences(): void
    {
        try {
            $this->validate($this->referenceValidationRules('*') + [
                'references' => ['required', 'array', 'min:1'],
            ], attributes: $this->referenceAttributeNames() + [
                'references' => 'references',
            ]);
        } catch (ValidationException $e) {
            $this->expandReferencesWithErrors($e->validator->errors()->keys());

            throw $e;
        }

        $this->validateReferenceHistoryCoverage();

        if ($this->getErrorBag()->has('references')) {
            return;
        }

        foreach (array_keys($this->references) as $index) {
            $this->persistReference($index);
        }

        $this->goToStep(6);
    }

    /** @param array<int, string> $errorKeys */
    private function expandReferencesWithErrors(array $errorKeys): void
    {
        collect($errorKeys)
            ->map(fn (string $key): ?int => preg_match('/^references\.(\d+)\./', $key, $matches) ? (int) $matches[1] : null)
            ->filter(fn (?int $index): bool => $index !== null)
            ->unique()
            ->each(function (int $index): void {
                if (isset($this->references[$index])) {
                    $this->references[$index]['collapsed'] = false;
                }
            });
    }

    public function completeApplication(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $candidate = $this->application->candidate;

        $this->application->update([
            'status' => 'completed',
            'current_step' => 6,
            'completed_at' => now(),
        ]);

        $user = User::updateOrCreate(
            ['email' => $candidate->email],
            [
                'name' => trim("{$candidate->first_name} {$candidate->last_name}"),
                'password' => $this->password,
                'company_id' => $candidate->company_id,
                'candidate_id' => $candidate->id,
                'candidate_type' => $candidate::class,
            ]
        );

        $industryId = $this->healthcareIndustryId();

        if ($industryId) {
            $user->industries()->syncWithoutDetaching([$industryId]);
        }

        $user->assignRole('candidate');

        HealthcareApplicationCompleted::run($this->application);

        Auth::login($user);

        $this->redirect('/candidate');
    }

    /** @return array<string, array<int, mixed>> */
    private function referenceValidationRules(string $index): array
    {
        return [
            "references.{$index}.reference_form_id" => [
                'required',
                Rule::exists('reference_forms', 'id')
                    ->where('company_id', $this->application->candidate->company_id)
                    ->where('industry_id', $this->healthcareIndustryId()),
            ],
            "references.{$index}.title" => ['nullable', 'string', 'in:Mr,Mrs,Miss,Ms,Dr,Prof'],
            "references.{$index}.first_name" => ['nullable', 'string', 'max:255', $this->requiredUnlessGapStatement('first name')],
            "references.{$index}.last_name" => ['nullable', 'string', 'max:255', $this->requiredUnlessGapStatement('last name')],
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
            "references.{$index}.consent_to_contact" => [$this->acceptedUnlessGapStatement()],
            "references.{$index}.contact_now" => ['boolean'],
            "references.{$index}.statement" => ['nullable', 'string', 'max:2000', $this->requiredIfGapStatement('statement')],
        ];
    }

    /**
     * A gap/statement entry has no referee — it's a self-declared explanation
     * covering a period with no employer or character reference, so
     * name/contact fields don't apply. See the equivalent helpers on
     * Education's application form for the full rationale on why these need
     * to be real ImplicitRule objects rather than plain Closures.
     */
    private function requiredUnlessGapStatement(string $label): ImplicitRule
    {
        $references = $this->references;
        $statementOnlyIds = $this->statementOnlyReferenceFormIds;

        return new class($label, $references, $statementOnlyIds) implements ImplicitRule
        {
            private string $failMessage = '';

            public function __construct(private string $label, private array $references, private array $statementOnlyIds) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $formId = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.reference_form_id");

                if (! in_array((int) $formId, $this->statementOnlyIds, true) && blank($value)) {
                    $this->failMessage = "The {$this->label} field is required.";

                    return false;
                }

                return true;
            }

            public function message()
            {
                return $this->failMessage;
            }
        };
    }

    private function requiredIfGapStatement(string $label): ImplicitRule
    {
        $references = $this->references;
        $statementOnlyIds = $this->statementOnlyReferenceFormIds;

        return new class($label, $references, $statementOnlyIds) implements ImplicitRule
        {
            private string $failMessage = '';

            public function __construct(private string $label, private array $references, private array $statementOnlyIds) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $formId = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.reference_form_id");

                if (in_array((int) $formId, $this->statementOnlyIds, true) && blank($value)) {
                    $this->failMessage = "The {$this->label} field is required.";

                    return false;
                }

                return true;
            }

            public function message()
            {
                return $this->failMessage;
            }
        };
    }

    private function acceptedUnlessGapStatement(): ImplicitRule
    {
        $references = $this->references;
        $statementOnlyIds = $this->statementOnlyReferenceFormIds;

        return new class($references, $statementOnlyIds) implements ImplicitRule
        {
            public function __construct(private array $references, private array $statementOnlyIds) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $formId = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.reference_form_id");

                return in_array((int) $formId, $this->statementOnlyIds, true) || (bool) $value;
            }

            public function message()
            {
                return 'The consent to contact must be accepted.';
            }
        };
    }

    /** @return array<string, string> */
    private function referenceFieldLabels(): array
    {
        return [
            'reference_form_id' => 'reference type',
            'title' => 'title',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'job_title' => 'job title',
            'worked_from' => 'worked from date',
            'worked_to' => 'worked to date',
            'email' => 'email',
            'mobile' => 'mobile number',
            'address' => 'address',
            'city' => 'city',
            'county' => 'county',
            'country' => 'country',
            'postcode' => 'postcode',
            'consent_to_contact' => 'consent to contact',
            'contact_now' => 'contact now',
            'statement' => 'statement',
        ];
    }

    /** @return array<string, string> */
    private function referenceAttributeNames(): array
    {
        return collect($this->referenceFieldLabels())
            ->mapWithKeys(fn (string $label, string $field): array => ["references.*.{$field}" => $label])
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function referenceErrorSummary(): array
    {
        $errors = $this->getErrorBag();

        if ($errors->isEmpty()) {
            return [];
        }

        $summary = $errors->get('references');

        $labels = $this->referenceFieldLabels();
        $fieldsByIndex = [];

        foreach ($errors->keys() as $key) {
            if (! preg_match('/^references\.(\d+)\.(\w+)$/', $key, $matches)) {
                continue;
            }

            $fieldsByIndex[(int) $matches[1]][] = $labels[$matches[2]] ?? $matches[2];
        }

        ksort($fieldsByIndex);

        foreach ($fieldsByIndex as $index => $fields) {
            $summary[] = __('Reference :number needs: :fields.', [
                'number' => $index + 1,
                'fields' => collect($fields)->unique()->implode(', '),
            ]);
        }

        return $summary;
    }

    private function persistReference(int $index): void
    {
        $reference = $this->references[$index];
        $isGapStatement = in_array((int) ($reference['reference_form_id'] ?? null), $this->statementOnlyReferenceFormIds, true);

        $data = [
            'reference_form_id' => $reference['reference_form_id'],
            'title' => $reference['title'] ?: null,
            'first_name' => $reference['first_name'] ?: null,
            'last_name' => $reference['last_name'] ?: null,
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
            'statement' => data_get($reference, 'statement') ?: null,
            'consent_to_contact' => $isGapStatement ? false : (bool) $reference['consent_to_contact'],
            'contact_now' => $isGapStatement ? false : (bool) ($reference['contact_now'] ?? false),
        ];

        if ($isGapStatement) {
            $data['status'] = 'confirmed';
        }

        $candidate = $this->application->candidate;

        if (! empty($reference['id'])) {
            $candidate->references()->findOrFail($reference['id'])->update($data);

            return;
        }

        $record = $candidate->references()->create($data);

        $this->references[$index]['id'] = $record->id;
    }

    /** @param array<string, mixed> $item */
    public function workPeriodLabel(array $item): ?string
    {
        if (empty($item['worked_from'])) {
            return null;
        }

        try {
            $from = Carbon::parse($item['worked_from']);
            $to = $item['worked_to'] ? Carbon::parse($item['worked_to']) : today();
        } catch (Throwable) {
            return null;
        }

        $duration = $from->diffForHumans($to, syntax: Carbon::DIFF_ABSOLUTE, parts: 2);
        $toLabel = $item['worked_to'] ? $to->format(self::DATE_DISPLAY_FORMAT) : 'Present';

        return $from->format(self::DATE_DISPLAY_FORMAT).' – '.$toLabel.' ('.$duration.')';
    }

    private function validateReferenceHistoryCoverage(): void
    {
        $coverage = $this->referenceCoverage();

        if (! $coverage['is_complete']) {
            $this->addError('references', $coverage['summary']);
        }
    }

    /**
     * Coverage passes once at least REFERENCE_HISTORY_COVERAGE_THRESHOLD of
     * the REFERENCE_HISTORY_YEARS window is accounted for — it doesn't need
     * to be a single unbroken block. When it isn't met, the summary calls
     * out the single largest uncovered span so the candidate knows exactly
     * which reference to add next.
     *
     * @return array{percentage: int, is_complete: bool, summary: string}
     */
    #[Computed]
    public function referenceCoverage(): array
    {
        $cutoff = now()->subYears(self::REFERENCE_HISTORY_YEARS)->startOfDay();
        $today = today();
        $windowDays = $cutoff->diffInDays($today) + 1;

        $periods = collect($this->references)
            ->filter(fn (array $reference) => ! empty($reference['worked_from']))
            ->map(function (array $reference) use ($cutoff, $today): array {
                $from = Carbon::parse($reference['worked_from'])->startOfDay();
                $to = $reference['worked_to'] ? Carbon::parse($reference['worked_to'])->startOfDay() : $today;

                return [
                    'from' => $from->lt($cutoff) ? $cutoff->copy() : $from,
                    'to' => $to->gt($today) ? $today->copy() : $to,
                ];
            })
            ->filter(fn (array $period) => $period['from']->lte($period['to']))
            ->sortBy('from')
            ->values();

        $covered = [];

        foreach ($periods as $period) {
            $lastIndex = count($covered) - 1;

            if ($lastIndex >= 0 && $period['from']->lte($covered[$lastIndex]['to']->copy()->addDay())) {
                if ($period['to']->gt($covered[$lastIndex]['to'])) {
                    $covered[$lastIndex]['to'] = $period['to'];
                }

                continue;
            }

            $covered[] = $period;
        }

        $coveredDays = collect($covered)->sum(fn (array $period) => $period['from']->diffInDays($period['to']) + 1);
        $percentage = $windowDays > 0 ? $coveredDays / $windowDays : 0;
        $isComplete = $percentage >= self::REFERENCE_HISTORY_COVERAGE_THRESHOLD;
        $percentageLabel = (int) round($percentage * 100);

        $summary = $isComplete
            ? 'Your references cover the last '.self::REFERENCE_HISTORY_YEARS.' years.'
            : $this->referenceCoverageGapSummary($covered, $cutoff, $today);

        return [
            'percentage' => $percentageLabel,
            'is_complete' => $isComplete,
            'summary' => $summary,
        ];
    }

    /** @param array<int, array{from: CarbonInterface, to: CarbonInterface}> $covered */
    private function referenceCoverageGapSummary(array $covered, CarbonInterface $cutoff, CarbonInterface $today): string
    {
        $gaps = [];
        $cursor = $cutoff->copy();

        foreach ($covered as $period) {
            if ($period['from']->gt($cursor)) {
                $gaps[] = ['from' => $cursor->copy(), 'to' => $period['from']->copy()->subDay()];
            }

            $cursor = $period['to']->copy()->addDay();
        }

        if ($cursor->lte($today)) {
            $gaps[] = ['from' => $cursor->copy(), 'to' => $today->copy()];
        }

        $largestGap = collect($gaps)->sortByDesc(fn (array $gap) => $gap['from']->diffInDays($gap['to']))->first();

        if (! $largestGap) {
            return 'Your references don\'t yet cover enough of the last '.self::REFERENCE_HISTORY_YEARS.' years.';
        }

        return 'There is a gap between '.$largestGap['from']->format(self::DATE_DISPLAY_FORMAT)
            .' and '.$largestGap['to']->format(self::DATE_DISPLAY_FORMAT)
            .' — please add a reference to cover this period.';
    }

    private function blankReference(): array
    {
        return [
            'id' => null,
            'reference_form_id' => null,
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
            'contact_now' => false,
            'statement' => '',
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

    /** @return array<int, string> */
    #[Computed]
    public function qualificationOptions(): array
    {
        return Qualification::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', $this->healthcareIndustryId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** @return Collection<int, CandidateSkill> */
    #[Computed]
    public function skillOptions(): Collection
    {
        return CandidateSkill::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', $this->healthcareIndustryId())
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function availabilityOptions(): array
    {
        return collect(Availability::cases())->mapWithKeys(fn (Availability $case) => [$case->value => $case->label()])->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function careSettingOptions(): array
    {
        return collect(CareSetting::cases())->mapWithKeys(fn (CareSetting $case) => [$case->value => $case->label()])->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function referenceFormOptions(): array
    {
        return ReferenceForm::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', $this->healthcareIndustryId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** @return array<int, int> */
    #[Computed]
    public function statementOnlyReferenceFormIds(): array
    {
        return ReferenceForm::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', $this->healthcareIndustryId())
            ->where('is_statement_only', true)
            ->pluck('id')
            ->all();
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
    public function existingCvPath(): ?string
    {
        return $this->application->candidate->documents()
            ->where('document_type', DocumentType::Cv)
            ->value('path');
    }

    #[Computed]
    public function companyName(): string
    {
        return $this->application->candidate->company?->trading_name ?: config('app.name');
    }

    private function healthcareIndustryId(): ?int
    {
        return Industry::where('slug', 'healthcare')->value('id');
    }

    private function hydrateFromCandidate(HealthcareCandidate $candidate): void
    {
        $this->title = $candidate->title ?? '';
        $this->first_name = $candidate->first_name ?? '';
        $this->last_name = $candidate->last_name ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->mobile = $candidate->mobile ?? '';
        $this->address = $candidate->address ?? '';
        $this->city = $candidate->city ?? '';
        $this->postcode = $candidate->postcode ?? '';

        $this->qualification_id = $candidate->qualification_id;
        $this->skills = $candidate->skills->pluck('id')->all();
        $this->availability = $candidate->availability ?? [];
        $this->care_settings = $candidate->care_settings ?? [];
        $this->right_to_work_type = $candidate->right_to_work_type;
        $this->right_to_work_expiry_date = $candidate->right_to_work_expiry_date?->toDateString();
        $this->has_dbs = $candidate->has_dbs;
        $this->dbs_expiry_date = $candidate->dbs_expiry_date?->toDateString();

        $this->employmentHistories = $candidate->employmentHistories->map(fn ($entry) => [
            'id' => $entry->id,
            'company_name' => $entry->company_name,
            'job_title' => $entry->job_title,
            'worked_from' => $entry->worked_from?->format('Y-m-d'),
            'worked_to' => $entry->worked_to?->format('Y-m-d'),
            'collapsed' => true,
        ])->all();

        $this->references = $candidate->references->map(fn ($reference) => [
            'id' => $reference->id,
            'reference_form_id' => $reference->reference_form_id,
            'title' => $reference->title,
            'first_name' => $reference->first_name,
            'last_name' => $reference->last_name,
            'job_title' => $reference->job_title,
            'worked_from' => $reference->worked_from?->format('Y-m-d'),
            'worked_to' => $reference->worked_to?->format('Y-m-d'),
            'email' => $reference->email,
            'mobile' => $reference->mobile,
            'address' => $reference->address,
            'city' => $reference->city,
            'county' => $reference->county,
            'country' => $reference->country,
            'postcode' => $reference->postcode,
            'consent_to_contact' => $reference->consent_to_contact,
            'contact_now' => $reference->contact_now,
            'statement' => $reference->statement,
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
            'last_name' => $data['lastName'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'postcode' => $data['postcode'] ?? '',
            'phone' => $data['phone'] ?? '',
            'mobile' => $data['mobile'] ?? '',
        ];

        foreach ($map as $property => $value) {
            if ($onlyFillBlanks && ! empty($this->$property)) {
                continue;
            }

            $this->$property = $value;
        }

        $this->cv_parsed_data = $data;
    }

    private function formatDateForInput(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
};
