<?php

use App\Enums\Nationality;
use App\Models\EducationApplication;
use App\Services\CvParserService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.auth')] class extends Component
{
    use WithFileUploads;

    public string $token = '';
    public ?EducationApplication $application = null;
    public int $currentStep = 1;

    public mixed $cv = null;
    public ?string $parseError = null;

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

        if (! empty($this->application->cv_parsed_data)) {
            $this->hydrateFromParsedData($this->application->cv_parsed_data);
            $this->currentStep = 2;
        }
    }

    public function parseCv(CvParserService $service): void
    {
        $this->parseError = null;

        $this->validate([
            'cv' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $this->cv->store('cv-uploads', 'local');

        try {
            $extracted = $service->parse(Storage::disk('local')->path($path));

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
            $this->employment_history = $extracted->employmentHistory ?? '';
            $this->cv_parsed_data   = (array) $extracted;
        } catch (Throwable $e) {
            $this->parseError = 'CV parsing failed. Please fill in your details manually below.';
            report($e);
        } finally {
            Storage::delete($path);
        }

        $this->currentStep = 2;
    }

    public function saveApplication(): void
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

        $this->application->update([
            'cv_parsed_data' => $this->cv_parsed_data,
            'status'         => 'completed',
            'completed_at'   => now(),
        ]);
    }

    private function hydrateFromParsedData(array $data): void
    {
        $this->first_name       = $data['firstName'] ?? '';
        $this->middle_name      = $data['middleName'] ?? '';
        $this->last_name        = $data['lastName'] ?? '';
        $this->date_of_birth    = $data['dateOfBirth'] ?? null;
        $this->address          = $data['address'] ?? '';
        $this->city             = $data['city'] ?? '';
        $this->county           = $data['county'] ?? '';
        $this->country          = $data['country'] ?? '';
        $this->postcode         = $data['postcode'] ?? '';
        $this->phone            = $data['phone'] ?? '';
        $this->mobile           = $data['mobile'] ?? '';
        $this->employment_history = $data['employmentHistory'] ?? '';
        $this->cv_parsed_data   = $data;
    }
};

?>

<div class="flex flex-col gap-6">

    {{-- AI parse error --}}
    @if ($parseError)
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $parseError }}
        </div>
    @endif

    {{-- Loading state while CV is being parsed --}}
    <div wire:loading wire:target="parseCv" class="py-12 text-center">
        <flux:icon.loading class="mx-auto block size-10 text-zinc-500" />
        <div class="mt-12">
            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Analysing your CV&hellip;</p>
            <p class="mt-1 text-xs text-zinc-500">This usually takes around 30 seconds.</p>
        </div>
    </div>

    {{-- Step 1: CV upload --}}
    @if ($currentStep === 1)
        <div wire:loading.remove wire:target="parseCv">

            <x-auth-header
                :title="__('Upload Your CV')"
                :description="__('Upload your CV as a PDF and we\'ll pre-fill your details automatically.')"
            />

            <form wire:submit="parseCv" class="mt-6 flex flex-col gap-6">

                <div class="flex flex-col gap-2">
                    <label for="cv" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('CV / Resume') }} <span class="text-red-500">*</span>
                    </label>

                    <div class="relative flex items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-center">
                            <svg class="mx-auto size-10 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                @if ($cv)
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $cv->getClientOriginalName() }}</span>
                                @else
                                    <span>{{ __('Click to select or drag and drop') }}</span>
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('PDF up to 10MB') }}</p>
                            <input
                                id="cv"
                                type="file"
                                wire:model="cv"
                                accept=".pdf"
                                class="absolute inset-0 cursor-pointer opacity-0"
                            />
                        </div>
                    </div>

                    @error('cv')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="parseCv">
                    <span wire:loading.remove wire:target="parseCv">{{ __('Analyse CV') }}</span>
                    <span wire:loading wire:target="parseCv">{{ __('Analysing…') }}</span>
                </flux:button>

            </form>
        </div>
    @endif

    {{-- Step 2: Personal details --}}
    @if ($currentStep === 2)
        <x-auth-header
            :title="__('Your Details')"
            :description="__('Review and complete your personal information below.')"
        />

        <form wire:submit="saveApplication" class="mt-6 flex flex-col gap-8">

            {{-- Personal Information --}}
            <div class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Personal Information') }}</p>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="title" :label="__('Title')" placeholder="{{ __('Select…') }}">
                        @foreach(['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'] as $t)
                            <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model="first_name"
                        :label="__('First Name')"
                        placeholder="John"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="middle_name"
                        :label="__('Middle Name')"
                        placeholder="William"
                    />

                    <flux:input
                        wire:model="last_name"
                        :label="__('Last Name')"
                        placeholder="Smith"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="previous_surname"
                        :label="__('Previous Name')"
                        placeholder="Jones"
                    />

                    <div
                        x-data="{
                            fp: null,
                            init() {
                                this.fp = flatpickr(this.$refs.dobInput, {
                                    dateFormat: 'Y-m-d',
                                    maxDate: 'today',
                                    allowInput: true,
                                    defaultDate: this.$refs.dobInput.value || null,
                                    onChange: (dates, dateStr) => {
                                        this.$refs.dobInput.value = dateStr;
                                        this.$refs.dobInput.dispatchEvent(new Event('input', { bubbles: true }));
                                    },
                                });
                                this.$watch('$wire.date_of_birth', (value) => {
                                    if (this.fp) this.fp.setDate(value || null, false);
                                });
                            },
                            destroy() {
                                if (this.fp) this.fp.destroy();
                            },
                        }"
                    >
                        <flux:input
                            input:x-ref="dobInput"
                            wire:model="date_of_birth"
                            :label="__('Date of Birth')"
                            placeholder="YYYY-MM-DD"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="gender" :label="__('Gender')" placeholder="{{ __('Select…') }}">
                        <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                        <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                        <flux:select.option value="non_binary">{{ __('Non-binary') }}</flux:select.option>
                        <flux:select.option value="prefer_not_to_say">{{ __('Prefer not to say') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="nationality" :label="__('Nationality')" placeholder="{{ __('Select…') }}">
                        @foreach(\App\Enums\Nationality::options() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            {{-- Address --}}
            <div class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Address') }}</p>

                <flux:input
                    wire:model="address"
                    :label="__('Address')"
                    placeholder="123 Example Street"
                    required
                />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="city"
                        :label="__('City / Town')"
                        placeholder="London"
                        required
                    />

                    <flux:input
                        wire:model="postcode"
                        :label="__('Postcode')"
                        placeholder="SW1A 1AA"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="county"
                        :label="__('County')"
                        placeholder="Greater London"
                    />

                    <flux:input
                        wire:model="country"
                        :label="__('Country')"
                        placeholder="United Kingdom"
                    />
                </div>
            </div>

            {{-- Contact Details --}}
            <div class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Contact Details') }}</p>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="phone"
                        type="tel"
                        :label="__('Phone')"
                        placeholder="+44 20 7946 0000"
                    />

                    <flux:input
                        wire:model="mobile"
                        type="tel"
                        :label="__('Mobile')"
                        placeholder="+44 7700 900000"
                    />
                </div>
            </div>

            {{-- Emergency Contact --}}
            <div class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Emergency Contact') }}</p>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="emergency_contact_name"
                        :label="__('Name')"
                        placeholder="Jane Smith"
                    />

                    <flux:input
                        wire:model="emergency_contact_number"
                        type="tel"
                        :label="__('Phone Number')"
                        placeholder="+44 7700 900000"
                    />
                </div>
            </div>

            {{-- Employment History --}}
            <div class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Employment History') }}</p>

                <flux:textarea
                    wire:model="employment_history"
                    :description="__('List your previous roles, employers, and dates.')"
                    rows="6"
                    placeholder="e.g. Class Teacher, Oakwood Primary School, Sept 2020 – Present"
                />
            </div>

            @foreach(['first_name', 'last_name', 'date_of_birth', 'address', 'city', 'postcode'] as $field)
                @error($field)
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            @endforeach

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Submit Application') }}
            </flux:button>

        </form>
    @endif

</div>
