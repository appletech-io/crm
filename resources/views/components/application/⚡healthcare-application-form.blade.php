<?php

use App\Actions\Applications\HealthcareApplicationCompleted;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Healthcare\CareSetting;
use App\Enums\ReferenceType;
use App\Models\CandidateSkill;
use App\Models\HealthcareApplication;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\User;
use App\Services\ApplicationAccessSession;
use App\Services\Education\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
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
        4 => 'Employment & References',
        5 => 'Create Your Account',
    ];

    public string $token = '';

    public ?HealthcareApplication $application = null;

    public int $step = 1;

    public $cv = null;

    public ?string $title = null;

    public ?string $first_name = null;

    public ?string $last_name = null;

    public ?string $phone = null;

    public ?string $mobile = null;

    public ?string $address = null;

    public ?string $postcode = null;

    public ?string $city = null;

    public ?string $qualification_id = null;

    /** @var array<int, int> */
    public array $skill_ids = [];

    /** @var array<int, string> */
    public array $availability = [];

    /** @var array<int, string> */
    public array $care_settings = [];

    public ?string $right_to_work_type = null;

    public ?string $has_dbs = null;

    public ?string $employer_name = null;

    public ?string $employer_job_title = null;

    public ?string $reference_first_name = null;

    public ?string $reference_last_name = null;

    public ?string $reference_email = null;

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

        $candidate = $this->application->candidate;

        $this->title = $candidate->title;
        $this->first_name = $candidate->first_name;
        $this->last_name = $candidate->last_name;
        $this->phone = $candidate->phone;
        $this->mobile = $candidate->mobile;
        $this->address = $candidate->address;
        $this->postcode = $candidate->postcode;
        $this->city = $candidate->city;
        $this->qualification_id = $candidate->qualification_id;
        $this->skill_ids = $candidate->skills()->pluck('candidate_skills.id')->all();
        $this->availability = $candidate->availability ?? [];
        $this->care_settings = $candidate->care_settings ?? [];
        $this->right_to_work_type = $candidate->right_to_work_type;
        $this->has_dbs = $candidate->has_dbs;
    }

    /** @return \Illuminate\Support\Collection<int, Qualification> */
    public function getQualificationsProperty()
    {
        return Qualification::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', Industry::where('slug', 'healthcare')->value('id'))
            ->orderBy('name')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, CandidateSkill> */
    public function getSkillsProperty()
    {
        return CandidateSkill::where('company_id', $this->application->candidate->company_id)
            ->where('industry_id', Industry::where('slug', 'healthcare')->value('id'))
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string> */
    public function getAvailabilityOptionsProperty(): array
    {
        return collect(Availability::cases())->mapWithKeys(fn (Availability $case) => [$case->value => $case->label()])->all();
    }

    /** @return array<int, string> */
    public function getCareSettingOptionsProperty(): array
    {
        return collect(CareSetting::cases())->mapWithKeys(fn (CareSetting $case) => [$case->value => $case->label()])->all();
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min($step, count(self::STEP_LABELS)));
    }

    public function saveCv(): void
    {
        $this->validate(['cv' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $candidate = $this->application->candidate;
        $path = Document::upload($this->cv, $candidate, 'cv');

        $candidate->documents()->updateOrCreate(
            ['document_type' => DocumentType::Cv->value],
            ['path' => $path],
        );

        $this->goToStep(2);
    }

    public function savePersonalDetails(): void
    {
        $this->validate([
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
            'right_to_work_type' => ['required', 'in:birth_certificate,passport,visa'],
            'has_dbs' => ['required', 'in:yes,no'],
        ]);

        $candidate = $this->application->candidate;

        $candidate->update([
            'qualification_id' => $this->qualification_id,
            'availability' => $this->availability,
            'care_settings' => $this->care_settings,
            'right_to_work_type' => $this->right_to_work_type,
            'has_dbs' => $this->has_dbs,
        ]);

        $candidate->skills()->sync($this->skill_ids);

        $this->goToStep(4);
    }

    public function saveEmploymentAndReferences(): void
    {
        $candidate = $this->application->candidate;

        if (filled($this->employer_name)) {
            $candidate->employmentHistories()->create([
                'company_name' => $this->employer_name,
                'job_title' => $this->employer_job_title ?? 'Not specified',
            ]);
        }

        if (filled($this->reference_first_name) && filled($this->reference_last_name)) {
            $candidate->references()->create([
                'type' => ReferenceType::Professional->value,
                'first_name' => $this->reference_first_name,
                'last_name' => $this->reference_last_name,
                'email' => $this->reference_email,
                'consent_to_contact' => true,
                'contact_now' => true,
                'status' => 'pending',
            ]);
        }

        $this->goToStep(5);
    }

    public function completeApplication(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $candidate = $this->application->candidate;

        $this->application->update([
            'status' => 'completed',
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

        $industryId = Industry::where('slug', 'healthcare')->value('id');

        if ($industryId) {
            $user->industries()->syncWithoutDetaching([$industryId]);
        }

        $user->assignRole('candidate');

        HealthcareApplicationCompleted::run($this->application);

        Auth::login($user);

        $this->redirect('/candidate');
    }
};

?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <div class="flex flex-col gap-3">
        <span class="text-sm text-zinc-500 dark:text-zinc-400">
            Step {{ $step }} of {{ count(self::STEP_LABELS) }} &middot; {{ self::STEP_LABELS[$step] }}
        </span>
        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
            <div class="h-full rounded-full bg-[var(--color-accent)] transition-all duration-300" style="width: {{ ($step / count(self::STEP_LABELS)) * 100 }}%"></div>
        </div>
    </div>

    @if ($step === 1)
        <form wire:submit="saveCv" class="flex flex-col gap-6">
            <flux:input type="file" wire:model="cv" label="Upload your CV (PDF)" required />
            @error('cv') <flux:error>{{ $message }}</flux:error> @enderror
            <flux:button type="submit" variant="primary" class="w-full">Continue</flux:button>
        </form>
    @endif

    @if ($step === 2)
        <form wire:submit="savePersonalDetails" class="flex flex-col gap-4">
            <flux:input wire:model="title" label="Title" />
            <flux:input wire:model="first_name" label="First Name" required />
            <flux:input wire:model="last_name" label="Last Name" required />
            <flux:input wire:model="phone" label="Phone" />
            <flux:input wire:model="mobile" label="Mobile" />
            <flux:input wire:model="address" label="Address" />
            <flux:input wire:model="city" label="City" />
            <flux:input wire:model="postcode" label="Postcode" />
            <flux:button type="submit" variant="primary" class="w-full">Continue</flux:button>
        </form>
    @endif

    @if ($step === 3)
        <form wire:submit="saveSkillsAndRightToWork" class="flex flex-col gap-4">
            <flux:select wire:model="qualification_id" label="Qualification">
                <flux:select.option value="">Select a qualification</flux:select.option>
                @foreach ($this->qualifications as $qualification)
                    <flux:select.option value="{{ $qualification->id }}">{{ $qualification->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <flux:label>Skills</flux:label>
                @foreach ($this->skills as $skill)
                    <flux:checkbox wire:model="skill_ids" value="{{ $skill->id }}" label="{{ $skill->name }}" />
                @endforeach
            </div>

            <div>
                <flux:label>Availability</flux:label>
                @foreach ($this->availabilityOptions as $value => $label)
                    <flux:checkbox wire:model="availability" value="{{ $value }}" label="{{ $label }}" />
                @endforeach
            </div>

            <div>
                <flux:label>Care Settings</flux:label>
                @foreach ($this->careSettingOptions as $value => $label)
                    <flux:checkbox wire:model="care_settings" value="{{ $value }}" label="{{ $label }}" />
                @endforeach
            </div>

            <flux:select wire:model="right_to_work_type" label="Right to Work" required>
                <flux:select.option value="">Select an option</flux:select.option>
                <flux:select.option value="passport">UK Passport</flux:select.option>
                <flux:select.option value="birth_certificate">UK Birth Certificate</flux:select.option>
                <flux:select.option value="visa">Visa</flux:select.option>
            </flux:select>
            @error('right_to_work_type') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:select wire:model="has_dbs" label="Do you currently have a DBS?" required>
                <flux:select.option value="">Select an option</flux:select.option>
                <flux:select.option value="yes">Yes</flux:select.option>
                <flux:select.option value="no">No</flux:select.option>
            </flux:select>
            @error('has_dbs') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:button type="submit" variant="primary" class="w-full">Continue</flux:button>
        </form>
    @endif

    @if ($step === 4)
        <form wire:submit="saveEmploymentAndReferences" class="flex flex-col gap-4">
            <flux:heading size="sm">Most recent employer (optional)</flux:heading>
            <flux:input wire:model="employer_name" label="Employer Name" />
            <flux:input wire:model="employer_job_title" label="Job Title" />

            <flux:heading size="sm">A reference (optional)</flux:heading>
            <flux:input wire:model="reference_first_name" label="First Name" />
            <flux:input wire:model="reference_last_name" label="Last Name" />
            <flux:input wire:model="reference_email" type="email" label="Email" />

            <flux:button type="submit" variant="primary" class="w-full">Continue</flux:button>
        </form>
    @endif

    @if ($step === 5)
        <form wire:submit="completeApplication" class="flex flex-col gap-4">
            <flux:input type="password" wire:model="password" label="Password" required />
            <flux:input type="password" wire:model="password_confirmation" label="Confirm Password" required />
            @error('password') <flux:error>{{ $message }}</flux:error> @enderror
            <flux:button type="submit" variant="primary" class="w-full">Complete Application</flux:button>
        </form>
    @endif
</div>
