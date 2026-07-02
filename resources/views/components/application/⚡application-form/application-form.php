<?php

use App\Enums\Education\EmploymentType;
use App\Enums\Education\KeyStage;
use App\Enums\Nationality;
use App\Models\CandidateSkill;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Services\CvParserService;
use App\Services\Document;
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
    ];

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
    public ?string $employment_type = null;
    public ?string $available_from = null;
    public array $key_stages = [];
    public array $skills = [];

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

            $this->first_name       = $extracted->firstName ?? '';
            $this->middle_name      = $extracted->middleName ?? '';
            $this->last_name        = $extracted->lastName ?? '';
            $this->date_of_birth    = $extracted->dateOfBirth ?: null;
            $this->address          = $extracted->address ?? '';
            $this->city             = $extracted->city ?? '';
            $this->county           = $extracted->county ?? '';
            $this->country          = $extracted->country ?? '';
            $this->postcode         = $extracted->postcode ?? '';
            $this->phone            = $extracted->phone ?? '';
            $this->mobile           = $extracted->mobile ?? '';
            $this->gender           = $extracted->gender ?? null;
            $this->nationality      = $extracted->nationality ?? null;
            $this->employment_history = $extracted->employmentHistory ?? '';
            $this->cv_parsed_data   = (array) $extracted;
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
            'first_name'               => ['required', 'string', 'max:255'],
            'last_name'                => ['required', 'string', 'max:255'],
            'date_of_birth'            => ['required', 'date', 'before:today'],
            'address'                  => ['required', 'string', 'max:500'],
            'city'                     => ['required', 'string', 'max:255'],
            'postcode'                 => ['required', 'string', 'max:10'],
            'title'                    => ['nullable', 'string', 'max:10'],
            'middle_name'              => ['nullable', 'string', 'max:255'],
            'previous_surname'         => ['nullable', 'string', 'max:255'],
            'gender'                   => ['nullable', 'string', 'in:male,female,non_binary,prefer_not_to_say'],
            'nationality'              => ['nullable', 'string', 'max:255'],
            'county'                   => ['nullable', 'string', 'max:255'],
            'country'                  => ['nullable', 'string', 'max:255'],
            'phone'                    => ['nullable', 'string', 'max:20'],
            'mobile'                   => ['nullable', 'string', 'max:20'],
            'emergency_contact_name'   => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:20'],
            'employment_history'       => ['nullable', 'string'],
        ]);

        $this->application->educationCandidate->update([
            'title'                    => $this->title,
            'first_name'               => $this->first_name,
            'middle_name'              => $this->middle_name ?: null,
            'last_name'                => $this->last_name,
            'previous_surname'         => $this->previous_surname ?: null,
            'gender'                   => $this->gender,
            'nationality'              => $this->nationality,
            'date_of_birth'            => $this->date_of_birth,
            'address'                  => $this->address,
            'city'                     => $this->city,
            'county'                   => $this->county ?: null,
            'country'                  => $this->country ?: null,
            'postcode'                 => $this->postcode,
            'phone'                    => $this->phone ?: null,
            'mobile'                   => $this->mobile ?: null,
            'emergency_contact_name'   => $this->emergency_contact_name ?: null,
            'emergency_contact_number' => $this->emergency_contact_number ?: null,
            'employment_history'       => $this->employment_history ?: null,
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

    public function submitApplication(): void
    {
        $this->validate([
            'qualification_id' => ['nullable', 'integer', 'exists:qualifications,id'],
            'employment_type'  => ['nullable', 'string', Rule::enum(EmploymentType::class)],
            'available_from'   => ['nullable', 'date'],
            'key_stages'       => ['nullable', 'array'],
            'key_stages.*'     => ['string', Rule::enum(KeyStage::class)],
            'skills'           => ['nullable', 'array'],
            'skills.*'         => ['integer', 'exists:candidate_skills,id'],
        ]);

        $skillIds = collect($this->skills);

        $parentIds = CandidateSkill::whereIn('id', $skillIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        $candidate = $this->application->educationCandidate;

        $candidate->update([
            'qualification_id' => $this->qualification_id,
            'employment_type'  => $this->employment_type,
            'available_from'   => $this->available_from,
            'key_stages'       => $this->key_stages,
        ]);

        $candidate->skills()->sync($skillIds->merge($parentIds)->unique()->values());

        $this->application->update([
            'status'       => 'completed',
            'current_step' => 4,
            'completed_at' => now(),
        ]);
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
        $this->title                    = $candidate->title;
        $this->first_name               = $candidate->first_name ?? '';
        $this->middle_name              = $candidate->middle_name ?? '';
        $this->last_name                = $candidate->last_name ?? '';
        $this->previous_surname         = $candidate->previous_surname ?? '';
        $this->gender                   = $candidate->gender;
        $this->nationality              = $candidate->nationality;
        $this->date_of_birth            = $candidate->date_of_birth?->toDateString();
        $this->address                  = $candidate->address ?? '';
        $this->city                     = $candidate->city ?? '';
        $this->county                   = $candidate->county ?? '';
        $this->country                  = $candidate->country ?? '';
        $this->postcode                 = $candidate->postcode ?? '';
        $this->phone                    = $candidate->phone ?? '';
        $this->mobile                   = $candidate->mobile ?? '';
        $this->emergency_contact_name   = $candidate->emergency_contact_name ?? '';
        $this->emergency_contact_number = $candidate->emergency_contact_number ?? '';
        $this->employment_history       = $candidate->employment_history ?? '';

        $this->qualification_id = $candidate->qualification_id;
        $this->employment_type  = $candidate->employment_type;
        $this->available_from   = $candidate->available_from?->toDateString();
        $this->key_stages       = $candidate->key_stages ?? [];
        $this->skills           = $candidate->skills->pluck('id')->all();
    }

    private function hydrateFromParsedData(array $data, bool $onlyFillBlanks = false): void
    {
        $map = [
            'first_name'         => $data['firstName'] ?? '',
            'middle_name'        => $data['middleName'] ?? '',
            'last_name'          => $data['lastName'] ?? '',
            'date_of_birth'      => $data['dateOfBirth'] ?? null,
            'address'            => $data['address'] ?? '',
            'city'               => $data['city'] ?? '',
            'county'             => $data['county'] ?? '',
            'country'            => $data['country'] ?? '',
            'postcode'           => $data['postcode'] ?? '',
            'phone'              => $data['phone'] ?? '',
            'mobile'             => $data['mobile'] ?? '',
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
};

