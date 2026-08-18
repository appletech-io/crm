<?php

use App\Actions\Applications\ApplicationCompleted;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateSkill;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\User;
use App\Services\Ai\CvParserService;
use App\Services\ApplicationAccessSession;
use App\Services\Candidates\Document;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Validation\ImplicitRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
        3 => 'Medical Information',
        4 => 'Consent',
        5 => 'Employment Conduct',
        6 => 'Photo',
        7 => 'Skills & Work',
        8 => 'Employment History',
        9 => 'References',
        10 => 'Document Requirements',
        11 => 'Create Your Account',
    ];

    private const CONSENT_SUB_STEP_LABELS = [
        1 => 'Terms of Engagement',
        2 => 'Keeping Children Safe in Education',
        3 => 'Declaration',
        4 => 'Security Clearance',
        5 => 'Rehabilitation of Offenders',
        6 => 'Working Time Regulations',
        7 => 'Disqualification under the Childcare Act 2006',
    ];

    private const REFERENCE_HISTORY_YEARS = 3;

    private const REFERENCE_HISTORY_COVERAGE_THRESHOLD = 0.8;

    private const DATE_DISPLAY_FORMAT = 'M j, Y';

    public string $token = '';

    public ?EducationApplication $application = null;

    public int $currentStep = 1;

    public mixed $cv = null;

    public ?string $parseError = null;

    public mixed $photo = null;

    // Personal information
    public string $title = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $previous_surname = '';

    public string $gender = '';

    public ?string $nationality = null;

    public ?string $date_of_birth = null;

    // Address
    public string $address = '';

    public string $city = '';

    public string $county = '';

    public string $country = '';

    public string $postcode = '';

    public bool $address_manual = false;

    public string $address_search = '';

    /** @var array<string, string> */
    public array $address_suggestions = [];

    // Contact
    public string $phone = '';

    public string $mobile = '';

    // Medical information
    public ?string $has_health_condition_or_disability = null;

    public string $health_condition_details = '';

    public string $reasonable_accommodations = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_number = '';

    // Employment conduct
    public ?string $retired_early = null;

    public ?string $retired_early_medical_grounds = null;

    public ?string $dismissed_from_relevant_position = null;

    public string $dismissal_details = '';

    public ?string $subject_to_disciplinary_action = null;

    public string $disciplinary_action_details = '';

    // Consent
    public int $consentSubStep = 1;

    public bool $terms_of_engagement_accepted = false;

    public bool $terms_accepted = false;

    public bool $declaration_accepted = false;

    public ?string $security_clearance_agreed = null;

    public ?string $lived_overseas_six_months = null;

    public string $overseas_details = '';

    public ?string $unspent_convictions = null;

    public string $unspent_convictions_details = '';

    public ?string $spent_convictions_not_protected = null;

    public ?string $working_time_regulations_opt_out = null;

    public ?string $childcare_act_guidance_read = null;

    public string $childcare_act_guidance_read_details = '';

    public ?string $childcare_act_no_disqualification_reasons = null;

    public string $childcare_act_no_disqualification_reasons_details = '';

    public ?string $childcare_act_will_notify_changes = null;

    public string $childcare_act_will_notify_changes_details = '';

    // Skills & work preferences
    public ?int $qualification_id = null;

    public array $availability = [];

    public ?string $available_from = null;

    public array $key_stages = [];

    public array $skills = [];

    public string $ni_number = '';

    public string $trn_number = '';

    // Employment History
    public array $employmentHistories = [];

    // References
    public array $references = [];

    public array $cv_parsed_data = [];

    // Document requirements
    public ?string $right_to_work_type = null;

    public string $visa_share_code = '';

    public ?string $right_to_work_expiry_date = null;

    public ?string $has_dbs = null;

    public string $dbs_certificate_number = '';

    public ?string $dbs_expiry_date = null;

    public ?string $has_naric = null;

    public ?string $safeguarding_expiry_date = null;

    // Account
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = EducationApplication::where('token', $token)->first();

        if (! $this->application) {
            abort(404);
        }

        if ($this->application->status === 'completed') {
            session()->put('toast', ['text' => __('Application Completed'), 'variant' => 'success']);

            Notification::make()
                ->title(__('Application Completed'))
                ->success()
                ->send();

            $this->redirect(route('filament.candidate.home'));

            return;
        }

        if ($this->application->status !== 'pending' || $this->application->expires_on < today()) {
            abort(403, 'This application link has expired.');
        }

        if (! ApplicationAccessSession::hasVerified($token)) {
            $this->redirect(route('application.verify', ['token' => $token]));

            return;
        }

        $this->currentStep = $this->application->current_step ?: 1;

        if ($this->currentStep >= 2) {
            $this->hydrateFromCandidate($this->application->educationCandidate);
        }

        $this->terms_of_engagement_accepted = $this->application->terms_of_engagement_accepted_at !== null;
        $this->terms_accepted = $this->application->terms_accepted_at !== null;
        $this->declaration_accepted = $this->application->declaration_accepted_at !== null;
        $this->security_clearance_agreed = $this->application->security_clearance_agreed;
        $this->working_time_regulations_opt_out = $this->application->working_time_regulations_opt_out;
        $this->childcare_act_guidance_read = $this->application->childcare_act_guidance_read;
        $this->childcare_act_guidance_read_details = $this->application->childcare_act_guidance_read_details ?? '';
        $this->childcare_act_no_disqualification_reasons = $this->application->childcare_act_no_disqualification_reasons;
        $this->childcare_act_no_disqualification_reasons_details = $this->application->childcare_act_no_disqualification_reasons_details ?? '';
        $this->childcare_act_will_notify_changes = $this->application->childcare_act_will_notify_changes;
        $this->childcare_act_will_notify_changes_details = $this->application->childcare_act_will_notify_changes_details ?? '';
        $this->consentSubStep = $this->furthestReachedConsentSubStep();

        $consentCompleted = $this->terms_of_engagement_accepted
            && $this->terms_accepted
            && $this->declaration_accepted
            && $this->application->security_clearance_accepted_at !== null
            && $this->application->rehabilitation_of_offenders_completed_at !== null
            && $this->application->working_time_regulations_accepted_at !== null
            && $this->application->disqualification_under_childcare_act_completed_at !== null;

        if (! $consentCompleted && $this->currentStep > 4) {
            $this->currentStep = 4;
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

        $documentPath = Document::upload($this->cv, $this->application->educationCandidate, 'cv');
        $cvDocument = $this->application->educationCandidate->documents()->updateOrCreate(
            ['document_type' => DocumentType::Cv],
            ['path' => $documentPath],
        );

        GenerateFormattedCv::dispatch($this->application->educationCandidate, $cvDocument);

        $localPath = 'cv-uploads/'.$this->application->id.'.'.pathinfo($documentPath, PATHINFO_EXTENSION);
        Storage::disk('local')->put($localPath, Storage::readStream($documentPath));

        try {
            $extracted = $service->parse(Storage::disk('local')->path($localPath));

            $this->first_name = $extracted->firstName ?? '';
            $this->middle_name = $extracted->middleName ?? '';
            $this->last_name = $extracted->lastName ?? '';
            $this->date_of_birth = $this->formatDateForInput($extracted->dateOfBirth);
            $this->address = $extracted->address ?? '';
            $this->city = $extracted->city ?? '';
            $this->county = $extracted->county ?? '';
            $this->country = $extracted->country ?? '';
            $this->postcode = $extracted->postcode ?? '';
            $this->phone = $extracted->phone ?? '';
            $this->mobile = $extracted->mobile ?? '';
            $this->gender = $extracted->gender ?? '';
            $this->nationality = $extracted->nationality ?? null;
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

    public function updatedAddressSearch(?string $value): void
    {
        if (! $value || strlen($value) < 3) {
            $this->address_suggestions = [];

            return;
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => config('services.google.places_key'),
            'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text',
        ])->post('https://places.googleapis.com/v1/places:autocomplete', [
            'input' => $value,
            'includedRegionCodes' => ['gb'],
        ]);

        if ($response->failed()) {
            $this->address_suggestions = [];

            return;
        }

        $this->address_suggestions = collect($response->json('suggestions') ?? [])
            ->mapWithKeys(fn (array $s): array => [
                $s['placePrediction']['placeId'] => $s['placePrediction']['text']['text'],
            ])
            ->toArray();
    }

    public function selectAddress(string $placeId): void
    {
        $response = Http::withHeaders([
            'X-Goog-Api-Key' => config('services.google.places_key'),
            'X-Goog-FieldMask' => 'addressComponents,formattedAddress',
        ])->get("https://places.googleapis.com/v1/places/{$placeId}");

        if ($response->failed()) {
            return;
        }

        $components = collect($response->json('addressComponents') ?? []);

        $getComponent = fn (string $type): string => $components
            ->first(fn (array $c): bool => in_array($type, $c['types'] ?? []))['longText'] ?? '';

        $streetNumber = $getComponent('street_number');
        $route = $getComponent('route');

        $this->address = collect([$streetNumber, $route])->filter()->implode(' ');
        $this->city = $getComponent('postal_town') ?: $getComponent('locality');
        $this->county = $getComponent('administrative_area_level_2');
        $this->country = $getComponent('country');
        $this->postcode = $getComponent('postal_code');
        $this->address_search = (string) $response->json('formattedAddress');
        $this->address_suggestions = [];
    }

    public function nextStep(): void
    {
        $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:10', 'regex:/^([Gg][Ii][Rr] ?0[Aa]{2})|((([A-Za-z][0-9]{1,2})|(([A-Za-z][A-Ha-hJ-Yj-y][0-9]{1,2})|(([A-Za-z][0-9][A-Za-z])|([A-Za-z][A-Ha-hJ-Yj-y][0-9][A-Za-z]?))))\s?[0-9][A-Za-z]{2})$/i'],
            'title' => ['required', 'string', 'in:Mr,Mrs,Miss,Ms,Dr,Prof'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'previous_surname' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female,non_binary,prefer_not_to_say'],
            'nationality' => ['required', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ], [
            'postcode.regex' => 'Please enter a valid UK postcode.',
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
        ]);

        $this->goToStep(3);
    }

    public function saveMedicalInformation(): void
    {
        $this->validate([
            'has_health_condition_or_disability' => ['required', 'in:yes,no'],
            'health_condition_details' => ['required_if:has_health_condition_or_disability,yes', 'nullable', 'string', 'max:2000'],
            'reasonable_accommodations' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $ownName = trim("{$this->first_name} {$this->last_name}");

                    if ($ownName !== '' && strcasecmp(trim((string) $value), $ownName) === 0) {
                        $fail('The emergency contact cannot be yourself — please provide someone else\'s details.');
                    }
                },
            ],
            'emergency_contact_number' => [
                'required', 'string', 'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $number = static::normalizePhoneNumber($value);
                    $ownNumbers = array_filter([
                        static::normalizePhoneNumber($this->phone),
                        static::normalizePhoneNumber($this->mobile),
                    ]);

                    if ($number !== '' && in_array($number, $ownNumbers, true)) {
                        $fail('The emergency contact number cannot be your own phone or mobile number.');
                    }
                },
            ],
        ]);

        $this->application->educationCandidate->update([
            'has_health_condition_or_disability' => $this->has_health_condition_or_disability,
            'health_condition_details' => $this->has_health_condition_or_disability === 'yes'
                ? ($this->health_condition_details ?: null)
                : null,
            'reasonable_accommodations' => $this->reasonable_accommodations ?: null,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_number' => $this->emergency_contact_number,
        ]);

        $this->goToStep(4);
    }

    public function acceptTermsOfEngagement(): void
    {
        $this->validate([
            'terms_of_engagement_accepted' => ['accepted'],
        ]);

        $this->application->update([
            'terms_of_engagement_accepted_at' => now(),
        ]);

        $this->consentSubStep = 2;
    }

    public function acceptTerms(): void
    {
        $this->validate([
            'terms_accepted' => ['accepted'],
        ]);

        $this->application->update([
            'terms_accepted_at' => now(),
        ]);

        $this->consentSubStep = 3;
    }

    public function acceptDeclaration(): void
    {
        $this->validate([
            'declaration_accepted' => ['accepted'],
        ]);

        $this->application->update([
            'declaration_accepted_at' => now(),
        ]);

        $this->consentSubStep = 4;
    }

    public function saveSecurityClearance(): void
    {
        $this->validate([
            'security_clearance_agreed' => ['required', 'in:yes,no'],
            'lived_overseas_six_months' => ['required', 'in:yes,no'],
            'overseas_details' => ['required_if:lived_overseas_six_months,yes', 'nullable', 'string', 'max:2000'],
        ]);

        $this->application->update([
            'security_clearance_agreed' => $this->security_clearance_agreed,
            'security_clearance_accepted_at' => now(),
        ]);

        $this->application->educationCandidate->update([
            'lived_overseas_six_months' => $this->lived_overseas_six_months,
            'overseas_details' => $this->lived_overseas_six_months === 'yes'
                ? ($this->overseas_details ?: null)
                : null,
        ]);

        $this->consentSubStep = 5;
    }

    public function saveRehabilitationOfOffenders(): void
    {
        $this->validate([
            'unspent_convictions' => ['required', 'in:yes,no'],
            'unspent_convictions_details' => ['nullable', 'string', 'max:2000'],
            'spent_convictions_not_protected' => ['required', 'in:yes,no'],
        ]);

        $this->application->educationCandidate->update([
            'unspent_convictions' => $this->unspent_convictions,
            'unspent_convictions_details' => $this->unspent_convictions === 'yes'
                ? ($this->unspent_convictions_details ?: null)
                : null,
            'spent_convictions_not_protected' => $this->spent_convictions_not_protected,
        ]);

        $this->application->update([
            'rehabilitation_of_offenders_completed_at' => now(),
        ]);

        $this->consentSubStep = 6;
    }

    public function saveWorkingTimeRegulations(): void
    {
        $this->validate([
            'working_time_regulations_opt_out' => ['required', 'in:yes,no'],
        ]);

        $this->application->update([
            'working_time_regulations_opt_out' => $this->working_time_regulations_opt_out,
            'working_time_regulations_accepted_at' => now(),
        ]);

        $this->consentSubStep = 7;
    }

    public function saveDisqualificationUnderChildcareAct(): void
    {
        $this->validate([
            'childcare_act_guidance_read' => ['required', 'in:yes,no'],
            'childcare_act_guidance_read_details' => ['required_if:childcare_act_guidance_read,no', 'nullable', 'string', 'max:2000'],
            'childcare_act_no_disqualification_reasons' => ['required', 'in:yes,no'],
            'childcare_act_no_disqualification_reasons_details' => ['required_if:childcare_act_no_disqualification_reasons,no', 'nullable', 'string', 'max:2000'],
            'childcare_act_will_notify_changes' => ['required', 'in:yes,no'],
            'childcare_act_will_notify_changes_details' => ['required_if:childcare_act_will_notify_changes,no', 'nullable', 'string', 'max:2000'],
        ]);

        $this->application->update([
            'childcare_act_guidance_read' => $this->childcare_act_guidance_read,
            'childcare_act_guidance_read_details' => $this->childcare_act_guidance_read === 'no'
                ? ($this->childcare_act_guidance_read_details ?: null)
                : null,
            'childcare_act_no_disqualification_reasons' => $this->childcare_act_no_disqualification_reasons,
            'childcare_act_no_disqualification_reasons_details' => $this->childcare_act_no_disqualification_reasons === 'no'
                ? ($this->childcare_act_no_disqualification_reasons_details ?: null)
                : null,
            'childcare_act_will_notify_changes' => $this->childcare_act_will_notify_changes,
            'childcare_act_will_notify_changes_details' => $this->childcare_act_will_notify_changes === 'no'
                ? ($this->childcare_act_will_notify_changes_details ?: null)
                : null,
            'disqualification_under_childcare_act_completed_at' => now(),
        ]);

        $this->goToStep(5);
    }

    public function saveEmploymentConduct(): void
    {
        $this->validate([
            'retired_early' => ['required', 'in:yes,no'],
            'retired_early_medical_grounds' => ['required_if:retired_early,yes', 'nullable', 'in:yes,no'],
            'dismissed_from_relevant_position' => ['required', 'in:yes,no'],
            'dismissal_details' => ['required_if:dismissed_from_relevant_position,yes', 'nullable', 'string', 'max:2000'],
            'subject_to_disciplinary_action' => ['required', 'in:yes,no'],
            'disciplinary_action_details' => ['required_if:subject_to_disciplinary_action,yes', 'nullable', 'string', 'max:2000'],
        ]);

        $this->application->educationCandidate->update([
            'retired_early' => $this->retired_early,
            'retired_early_medical_grounds' => $this->retired_early === 'yes'
                ? $this->retired_early_medical_grounds
                : null,
            'dismissed_from_relevant_position' => $this->dismissed_from_relevant_position,
            'dismissal_details' => $this->dismissed_from_relevant_position === 'yes'
                ? ($this->dismissal_details ?: null)
                : null,
            'subject_to_disciplinary_action' => $this->subject_to_disciplinary_action,
            'disciplinary_action_details' => $this->subject_to_disciplinary_action === 'yes'
                ? ($this->disciplinary_action_details ?: null)
                : null,
        ]);

        $this->goToStep(6);
    }

    public function savePhoto(): void
    {
        if (! $this->photo && ! $this->existingPhotoUrl) {
            $this->addError('photo', 'Please add a photo before continuing.');

            return;
        }

        if ($this->photo) {
            $this->validate([
                'photo' => ['image', 'max:5120'],
            ]);

            $photoPath = Document::upload($this->photo, $this->application->educationCandidate, 'photo');

            $this->application->educationCandidate->documents()->updateOrCreate(
                ['document_type' => DocumentType::Photo],
                ['path' => $photoPath],
            );
        }

        $this->goToStep(7);
    }

    public function saveWorkPreferences(): void
    {
        $this->ni_number = strtoupper(str_replace(' ', '', $this->ni_number));

        $this->validate([
            'qualification_id' => ['required', 'integer', 'exists:qualifications,id'],
            'availability' => ['nullable', 'array'],
            'availability.*' => ['string', Rule::enum(Availability::class)],
            'available_from' => ['nullable', 'date'],
            'key_stages' => ['nullable', 'array'],
            'key_stages.*' => ['string', Rule::enum(KeyStage::class)],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['integer', 'exists:candidate_skills,id'],
            'ni_number' => ['required', 'string', 'regex:/^(?!BG)(?!GB)(?!NK)(?!KN)(?!TN)(?!NT)(?!ZZ)[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z][0-9]{6}[A-D]$/i'],
            'trn_number' => ['nullable', 'string', 'max:20'],
        ], [
            'ni_number.regex' => 'Please enter a valid National Insurance number (e.g. AB123456C).',
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
            'ni_number' => $this->ni_number,
            'trn_number' => $this->trn_number ?: null,
        ]);

        $candidate->skills()->sync($skillIds->merge($parentIds)->unique()->values());

        $this->goToStep(8);
    }

    public function addEmploymentHistory(): void
    {
        $this->employmentHistories[] = $this->blankEmploymentHistory();
    }

    public function removeEmploymentHistory(int $index): void
    {
        $entry = $this->employmentHistories[$index] ?? null;

        if ($entry && ! empty($entry['id'])) {
            $this->application->educationCandidate->employmentHistories()->whereKey($entry['id'])->delete();
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

        $this->goToStep(9);
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

        $candidate = $this->application->educationCandidate;

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
        $this->validate($this->referenceValidationRules((string) $index), attributes: $this->referenceAttributeNames());

        $this->persistReference($index);

        $this->references[$index]['collapsed'] = true;
    }

    public function submitApplication(): void
    {
        try {
            $this->validate($this->referenceValidationRules('*') + [
                'references' => ['required', 'array', 'min:1'],
            ], attributes: $this->referenceAttributeNames() + [
                'references' => 'references',
            ]);
        } catch (ValidationException $e) {
            // Errors on a collapsed reference are otherwise invisible — its
            // fields aren't even rendered — so expand any row an error
            // belongs to alongside the top-of-page summary.
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

        $this->goToStep(10);
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

    public function saveDocumentRequirements(): void
    {
        $this->validate([
            'right_to_work_type' => ['required', 'in:birth_certificate,passport,visa'],
            'visa_share_code' => ['required_if:right_to_work_type,visa', 'nullable', 'string', 'max:255'],
            'right_to_work_expiry_date' => ['nullable', 'date'],
            'has_dbs' => ['required', 'in:yes,no'],
            'dbs_certificate_number' => ['required_if:has_dbs,yes', 'nullable', 'string', 'regex:/^\d+$/', 'max:20'],
            'dbs_expiry_date' => ['nullable', 'date'],
            'has_naric' => ['nullable', 'in:yes,no'],
            'safeguarding_expiry_date' => ['nullable', 'date'],
        ]);

        $this->application->educationCandidate->update([
            'right_to_work_type' => $this->right_to_work_type,
            'visa_share_code' => $this->right_to_work_type === 'visa' ? $this->visa_share_code : null,
            'right_to_work_expiry_date' => in_array($this->right_to_work_type, ['visa', 'passport'], true) ? $this->right_to_work_expiry_date : null,
            'has_dbs' => $this->has_dbs,
            'dbs_certificate_number' => $this->has_dbs === 'yes' ? $this->dbs_certificate_number : null,
            'dbs_expiry_date' => $this->has_dbs === 'yes' ? $this->dbs_expiry_date : null,
            'has_naric' => $this->has_naric,
            'safeguarding_expiry_date' => $this->safeguarding_expiry_date,
        ]);

        $this->goToStep(11);
    }

    public function completeApplication(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $candidate = $this->application->educationCandidate;

        // Marked completed before the user account is created so that the
        // UserObserver's candidate status automation check (which can key off
        // application.completed_at) sees it as already set.
        $this->application->update([
            'status' => 'completed',
            'current_step' => 11,
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

        $industrySlug = Industry::slugForCandidateModel($candidate::class);
        $industryId = $industrySlug ? Industry::where('slug', $industrySlug)->value('id') : null;

        if ($industryId) {
            $user->industries()->syncWithoutDetaching([$industryId]);
        }

        $user->assignRole('candidate');

        ApplicationCompleted::run($this->application);

        Auth::login($user);

        $this->redirect('/candidate');
    }

    /** @return array<string, array<int, mixed>> */
    private function referenceValidationRules(string $index): array
    {
        return [
            "references.{$index}.type" => ['required', 'string', Rule::enum(ReferenceType::class)],
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
     * (job seeking, illness, travel, etc.) covering a period with no
     * employer or character reference, so name/contact fields don't apply.
     *
     * These need to be real ImplicitRule objects rather than plain Closures:
     * Laravel skips non-implicit rules entirely for an attribute whose value
     * is empty (that's exactly the "required" case we need to catch), so a
     * plain closure would never even run for a blank first_name/last_name.
     */
    private function requiredUnlessGapStatement(string $label): ImplicitRule
    {
        $references = $this->references;

        return new class($label, $references) implements ImplicitRule
        {
            private string $failMessage = '';

            public function __construct(private string $label, private array $references) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $type = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.type");

                if ($type !== ReferenceType::GapStatement->value && blank($value)) {
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

        return new class($label, $references) implements ImplicitRule
        {
            private string $failMessage = '';

            public function __construct(private string $label, private array $references) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $type = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.type");

                if ($type === ReferenceType::GapStatement->value && blank($value)) {
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

        return new class($references) implements ImplicitRule
        {
            public function __construct(private array $references) {}

            public function passes($attribute, $value)
            {
                $itemIndex = explode('.', $attribute)[1] ?? null;
                $type = $itemIndex === null ? null : data_get($this->references, "{$itemIndex}.type");

                return $type === ReferenceType::GapStatement->value || (bool) $value;
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
            'type' => 'reference type',
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

    /**
     * Friendly field names for reference validation errors — without these,
     * Laravel falls back to the raw array path (e.g. "references.0.title")
     * since it has no attribute name to prettify for an indexed array field.
     *
     * @return array<string, string>
     */
    private function referenceAttributeNames(): array
    {
        return collect($this->referenceFieldLabels())
            ->mapWithKeys(fn (string $label, string $field): array => ["references.*.{$field}" => $label])
            ->all();
    }

    /**
     * Every reference error in one place, above the (possibly collapsed)
     * reference list, so nothing is left hidden inside a collapsed row —
     * e.g. "references.2.first_name" becomes "Reference 3 needs: first
     * name, last name."
     *
     * @return array<int, string>
     */
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
        $isGapStatement = ($reference['type'] ?? null) === ReferenceType::GapStatement->value;

        $data = [
            'type' => $reference['type'],
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
            // A gap/statement entry has no referee to contact — it's
            // self-declared and complete the moment it's submitted, so it
            // never enters the pending/contacted flow the way a real
            // reference does.
            'consent_to_contact' => $isGapStatement ? false : (bool) $reference['consent_to_contact'],
            'contact_now' => $isGapStatement ? false : (bool) ($reference['contact_now'] ?? false),
        ];

        // A gap/statement entry is confirmed immediately — there's no
        // referee to progress it through pending/contacted/submitted like a
        // real reference. Only forced here, never reset back to pending on
        // an existing non-gap reference someone's already progressed.
        if ($isGapStatement) {
            $data['status'] = ReferenceStatus::Confirmed->value;
        }

        $candidate = $this->application->educationCandidate;

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
     * Coverage no longer has to be a single unbroken 3-year block — it
     * passes once at least REFERENCE_HISTORY_COVERAGE_THRESHOLD of the
     * window is accounted for. When it isn't met, the summary calls out the
     * single largest uncovered span so the candidate knows exactly which
     * reference to add next; resubmitting re-runs this and surfaces
     * whatever the next-largest gap is, so it naturally iterates down to
     * nothing left once enough of the window is covered.
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

        // Merge overlapping/touching periods — back-to-back employment (one
        // job ending the day before the next starts) counts as continuous,
        // not a gap — into the smallest set of covered intervals.
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

    public function viewConsentSubStep(int $subStep): void
    {
        if ($subStep < 1 || $subStep > $this->furthestConsentSubStep) {
            return;
        }

        $this->consentSubStep = $subStep;
    }

    private function furthestReachedConsentSubStep(): int
    {
        return match (true) {
            $this->application->working_time_regulations_accepted_at !== null => 7,
            $this->application->rehabilitation_of_offenders_completed_at !== null => 6,
            $this->application->security_clearance_accepted_at !== null => 5,
            $this->application->declaration_accepted_at !== null => 4,
            $this->application->terms_accepted_at !== null => 3,
            $this->application->terms_of_engagement_accepted_at !== null => 2,
            default => 1,
        };
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

    /** @return array<int, string> */
    #[Computed]
    public function consentSubStepLabels(): array
    {
        return self::CONSENT_SUB_STEP_LABELS;
    }

    #[Computed]
    public function totalConsentSubSteps(): int
    {
        return count(self::CONSENT_SUB_STEP_LABELS);
    }

    #[Computed]
    public function consentSubStepProgressPercentage(): int
    {
        return (int) round(($this->consentSubStep / $this->totalConsentSubSteps) * 100);
    }

    #[Computed]
    public function furthestConsentSubStep(): int
    {
        return $this->furthestReachedConsentSubStep();
    }

    #[Computed]
    public function existingPhotoUrl(): ?string
    {
        $photoPath = $this->application->educationCandidate->documents()
            ->where('document_type', DocumentType::Photo)
            ->value('path');

        if (! $photoPath) {
            return null;
        }

        return Storage::disk(config('filesystems.default'))->temporaryUrl($photoPath, now()->addMinutes(30));
    }

    #[Computed]
    public function existingCvPath(): ?string
    {
        return $this->application->educationCandidate->documents()
            ->where('document_type', DocumentType::Cv)
            ->value('path');
    }

    #[Computed]
    public function kcsiePdfUrl(): string
    {
        return asset('documents/kcsie.pdf');
    }

    #[Computed]
    public function employmentBusinessName(): string
    {
        $company = $this->application->educationCandidate->company;

        $tradingName = $company?->trading_name ?: config('app.name');

        if (! $company?->legal_name) {
            return $tradingName;
        }

        $name = "{$tradingName} (t/a {$company->legal_name})";

        return $company->company_number
            ? "{$name} (Company No: {$company->company_number})"
            : $name;
    }

    private function hydrateFromCandidate(EducationCandidate $candidate): void
    {
        $this->title = $candidate->title ?? '';
        $this->first_name = $candidate->first_name ?? '';
        $this->middle_name = $candidate->middle_name ?? '';
        $this->last_name = $candidate->last_name ?? '';
        $this->previous_surname = $candidate->previous_surname ?? '';
        $this->gender = $candidate->gender ?? '';
        $this->nationality = $candidate->nationality;
        $this->date_of_birth = $candidate->date_of_birth?->format('Y-m-d');
        $this->address = $candidate->address ?? '';
        $this->city = $candidate->city ?? '';
        $this->county = $candidate->county ?? '';
        $this->country = $candidate->country ?? '';
        $this->postcode = $candidate->postcode ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->mobile = $candidate->mobile ?? '';
        $this->has_health_condition_or_disability = $candidate->has_health_condition_or_disability;
        $this->health_condition_details = $candidate->health_condition_details ?? '';
        $this->reasonable_accommodations = $candidate->reasonable_accommodations ?? '';
        $this->emergency_contact_name = $candidate->emergency_contact_name ?? '';
        $this->emergency_contact_number = $candidate->emergency_contact_number ?? '';
        $this->retired_early = $candidate->retired_early;
        $this->retired_early_medical_grounds = $candidate->retired_early_medical_grounds;
        $this->dismissed_from_relevant_position = $candidate->dismissed_from_relevant_position;
        $this->dismissal_details = $candidate->dismissal_details ?? '';
        $this->subject_to_disciplinary_action = $candidate->subject_to_disciplinary_action;
        $this->disciplinary_action_details = $candidate->disciplinary_action_details ?? '';
        $this->lived_overseas_six_months = $candidate->lived_overseas_six_months;
        $this->overseas_details = $candidate->overseas_details ?? '';
        $this->unspent_convictions = $candidate->unspent_convictions;
        $this->unspent_convictions_details = $candidate->unspent_convictions_details ?? '';
        $this->spent_convictions_not_protected = $candidate->spent_convictions_not_protected;
        $this->right_to_work_type = $candidate->right_to_work_type;
        $this->visa_share_code = $candidate->visa_share_code ?? '';
        $this->right_to_work_expiry_date = $candidate->right_to_work_expiry_date?->toDateString();
        $this->has_dbs = $candidate->has_dbs;
        $this->dbs_certificate_number = $candidate->dbs_certificate_number ?? '';
        $this->dbs_expiry_date = $candidate->dbs_expiry_date?->toDateString();
        $this->has_naric = $candidate->has_naric;
        $this->safeguarding_expiry_date = $candidate->safeguarding_expiry_date?->toDateString();

        $this->qualification_id = $candidate->qualification_id;
        $this->availability = $candidate->availability ?? [];
        $this->available_from = $candidate->available_from?->format('Y-m-d');
        $this->key_stages = $candidate->key_stages ?? [];
        $this->skills = $candidate->skills->pluck('id')->all();
        $this->ni_number = $candidate->ni_number ?? '';
        $this->trn_number = $candidate->trn_number ?? '';

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
            'type' => $reference->type?->value,
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
            'middle_name' => $data['middleName'] ?? '',
            'last_name' => $data['lastName'] ?? '',
            'date_of_birth' => $this->formatDateForInput($data['dateOfBirth'] ?? null),
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'county' => $data['county'] ?? '',
            'country' => $data['country'] ?? '',
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

    /**
     * Digits-only, with a leading UK "44" country code normalised back to a
     * "0" trunk prefix — so "+44 7700 900123" and "07700 900123" compare
     * equal for the emergency-contact-isn't-the-candidate check.
     */
    private static function normalizePhoneNumber(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return preg_match('/^44\d{10}$/', $digits) ? '0'.substr($digits, 2) : $digits;
    }
};
