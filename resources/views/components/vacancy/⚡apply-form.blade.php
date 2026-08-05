<?php

use App\Enums\ActivityType;
use App\Enums\DocumentType;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Services\Ai\CvParserService;
use App\Services\Candidates\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.application', ['maxWidth' => 'max-w-4xl'])] class extends Component
{
    use WithFileUploads;

    private const STEP_LABELS = [
        1 => 'Upload CV',
        2 => 'Your Details',
        3 => 'Employment History',
        4 => 'Skills',
        5 => 'Application Complete',
    ];

    private const DATE_DISPLAY_FORMAT = 'M Y';

    public Vacancy $vacancy;

    public int $step = 1;

    public $cv = null;

    public ?string $parseError = null;

    public ?string $title = null;

    public ?string $first_name = null;

    public ?string $last_name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $mobile = null;

    public bool $address_manual = false;

    public string $address_search = '';

    /** @var array<string, string> */
    public array $address_suggestions = [];

    public ?string $address = null;

    public ?string $city = null;

    public ?string $county = null;

    public ?string $country = null;

    public ?string $postcode = null;

    /** @var array<int, array<string, mixed>> */
    public array $employmentHistories = [];

    public ?string $qualification_id = null;

    /** @var array<int, int> */
    public array $skill_ids = [];

    public bool $isReturningCandidate = false;

    public function mount(Vacancy $vacancy): void
    {
        $this->vacancy = $vacancy;
        $this->employmentHistories = [$this->blankEmploymentHistory()];
    }

    /** @return \Illuminate\Support\Collection<int, Qualification> */
    public function getQualificationsProperty()
    {
        return Qualification::where('company_id', $this->companyId())
            ->where('industry_id', $this->industryId())
            ->orderBy('name')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, CandidateSkill> */
    public function getSkillsProperty()
    {
        return CandidateSkill::where('company_id', $this->companyId())
            ->where('industry_id', $this->industryId())
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->get();
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min($step, count(self::STEP_LABELS)));
    }

    public function skipCv(): void
    {
        $this->goToStep(2);
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

    /**
     * Livewire calls this automatically once a file selected via
     * wire:model="cv" finishes uploading — the candidate doesn't need to
     * press a separate button, parsing starts as soon as they pick a file.
     */
    public function updatedCv(): void
    {
        $this->parseCv(app(CvParserService::class));
    }

    public function parseCv(CvParserService $service): void
    {
        $this->parseError = null;

        if (! $this->cv) {
            $this->addError('cv', 'Please upload your CV, or skip this step.');

            return;
        }

        $this->validate([
            'cv' => ['file', 'mimes:pdf,docx', 'max:10240'],
        ]);

        $localPath = 'vacancy-apply-cv-uploads/'.Str::uuid().'.'.$this->cv->getClientOriginalExtension();
        Storage::disk('local')->put($localPath, file_get_contents($this->cv->getRealPath()));

        try {
            $extracted = $service->parse(Storage::disk('local')->path($localPath));

            $this->first_name = $extracted->firstName ?? '';
            $this->last_name = $extracted->lastName ?? '';
            $this->email = $extracted->email ?? '';
            $this->phone = $extracted->phone ?? '';
            $this->mobile = $extracted->mobile ?? '';
            $this->address = $extracted->address ?? '';
            $this->city = $extracted->city ?? '';
            $this->county = $extracted->county ?? '';
            $this->country = $extracted->country ?? '';
            $this->postcode = $extracted->postcode ?? '';

            $seeded = $this->seedEmploymentHistoriesFromCvData((array) $extracted);

            if (! empty($seeded)) {
                $this->employmentHistories = $seeded;
            }
        } catch (Throwable $e) {
            $this->parseError = 'CV parsing failed. Please fill in your details manually below.';
            report($e);
        } finally {
            Storage::disk('local')->delete($localPath);
        }

        $this->goToStep(2);
    }

    public function savePersonalDetails(): void
    {
        $this->validate([
            'title' => ['nullable', 'string', 'in:Mr,Mrs,Miss,Ms,Dr,Prof'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:10'],
        ]);

        $this->goToStep(3);
    }

    public function addEmploymentHistory(): void
    {
        $this->employmentHistories[] = $this->blankEmploymentHistory();
    }

    public function removeEmploymentHistory(int $index): void
    {
        unset($this->employmentHistories[$index]);

        $this->employmentHistories = array_values($this->employmentHistories);

        if (empty($this->employmentHistories)) {
            $this->employmentHistories = [$this->blankEmploymentHistory()];
        }
    }

    public function saveEmploymentHistory(): void
    {
        $this->goToStep(4);
    }

    public function saveSkills(): void
    {
        $this->validate([
            'qualification_id' => [
                'nullable', 'integer',
                Rule::exists('qualifications', 'id')->where('company_id', $this->companyId())->where('industry_id', $this->industryId()),
            ],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => [
                'integer',
                Rule::exists('candidate_skills', 'id')->where('company_id', $this->companyId())->where('industry_id', $this->industryId()),
            ],
        ], [
            'skill_ids.required' => 'Please select at least one skill.',
        ]);

        $this->createCandidate();

        $this->goToStep(5);
    }

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

        $toLabel = $item['worked_to'] ? $to->format(self::DATE_DISPLAY_FORMAT) : 'Present';

        return $from->format(self::DATE_DISPLAY_FORMAT).' – '.$toLabel;
    }

    private function createCandidate(): void
    {
        $candidateModelClass = $this->candidateModelClass();
        $companyId = $this->companyId();

        DB::transaction(function () use ($candidateModelClass, $companyId): void {
            // A returning candidate (matched by email within this company) is
            // updated in place rather than rejected or duplicated. Only the
            // basic details this form collects are touched here — name,
            // contact info, qualification, employment history, and skills —
            // none of which are specific to temp or perm work, so refreshing
            // them for someone applying to a new vacancy is always safe. Any
            // compliance/vetting fields on the candidate are never written
            // to by this flow, so they're untouched either way.
            $existingCandidate = $candidateModelClass::where('company_id', $companyId)
                ->where('email', $this->email)
                ->first();

            $this->isReturningCandidate = $existingCandidate !== null;

            $basicDetails = [
                'title' => $this->title,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone ?: null,
                'mobile' => $this->mobile ?: null,
                'address' => $this->address ?: null,
                'city' => $this->city ?: null,
                'county' => $this->county ?: null,
                'country' => $this->country ?: null,
                'postcode' => $this->postcode ?: null,
                'qualification_id' => $this->qualification_id ?: null,
            ];

            if ($existingCandidate) {
                $existingCandidate->update($basicDetails);
                $candidate = $existingCandidate;
            } else {
                $candidate = $candidateModelClass::create([
                    ...$basicDetails,
                    'company_id' => $companyId,
                    'email' => $this->email,
                ]);
            }

            if ($this->cv) {
                $path = Document::upload($this->cv, $candidate, 'cv');

                $candidate->documents()->updateOrCreate(
                    ['document_type' => DocumentType::Cv],
                    ['path' => $path],
                );
            }

            foreach ($this->employmentHistories as $job) {
                if (blank($job['company_name']) && blank($job['job_title'])) {
                    continue;
                }

                $candidate->employmentHistories()->create([
                    'company_name' => $job['company_name'] ?: null,
                    'job_title' => $job['job_title'] ?: null,
                    'worked_from' => $job['worked_from'] ?: null,
                    'worked_to' => $job['worked_to'] ?: null,
                ]);
            }

            $parentIds = CandidateSkill::whereIn('id', $this->skill_ids)
                ->whereNotNull('parent_id')
                ->pluck('parent_id');

            // syncWithoutDetaching rather than sync: a returning candidate's
            // previously recorded skills are additive, never lost just
            // because this particular application didn't re-select them.
            $candidate->skills()->syncWithoutDetaching(collect($this->skill_ids)->merge($parentIds)->unique());

            if ($candidate->statuses()->doesntExist()) {
                $onboarding = CandidateStatus::where('company_id', $companyId)
                    ->where('industry_id', $this->industryId())
                    ->where('name', 'Onboarding')
                    ->first();

                if ($onboarding) {
                    $candidate->statuses()->firstOrCreate(['candidate_status_id' => $onboarding->id]);
                }
            }

            VacancyApplication::firstOrCreate([
                'vacancy_id' => $this->vacancy->id,
                'candidate_type' => $candidate::class,
                'candidate_id' => $candidate->id,
            ]);

            $candidateName = trim("{$candidate->first_name} {$candidate->last_name}");
            $note = $this->isReturningCandidate
                ? "Updated their details and applied via the public link for vacancy: {$this->vacancy->title}"
                : "Applied via the public link for vacancy: {$this->vacancy->title}";

            $candidate->activities()->create([
                'user_id' => null,
                'type' => ActivityType::Note->value,
                'note' => $note,
                'contacted' => false,
            ]);

            $this->vacancy->activities()->create([
                'user_id' => null,
                'type' => ActivityType::Note->value,
                'note' => $this->isReturningCandidate
                    ? "Returning applicant: {$candidateName}"
                    : "New applicant: {$candidateName}",
                'contacted' => false,
            ]);
        });
    }

    /** @return array<int, array{id: null, company_name: string, job_title: string, worked_from: ?string, worked_to: ?string, collapsed: bool}> */
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

    /** @return array{id: null, company_name: string, job_title: string, worked_from: null, worked_to: null, collapsed: bool} */
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

    private function candidateModelClass(): string
    {
        return Industry::candidateModelForSlug($this->vacancy->client->industry->slug);
    }

    private function companyId(): int
    {
        return $this->vacancy->client->company_id;
    }

    private function industryId(): int
    {
        return $this->vacancy->client->industry_id;
    }
};

?>

<div class="flex flex-col gap-6">
    @if ($vacancy->open_for_applications)
        <div class="flex flex-col gap-3">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                Step {{ $step }} of {{ count(self::STEP_LABELS) }} &middot; {{ self::STEP_LABELS[$step] }}
            </span>
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-full rounded-full bg-[var(--color-accent)] transition-all duration-300" style="width: {{ ($step / count(self::STEP_LABELS)) * 100 }}%"></div>
            </div>
        </div>
    @endif

    <div class="flex flex-col gap-1">
        <flux:heading size="lg">{{ $vacancy->title }}</flux:heading>
        <flux:subheading>{{ $vacancy->client->name }}</flux:subheading>
    </div>

    @if (! $vacancy->open_for_applications)
        <flux:callout variant="warning" icon="information-circle" heading="Applications for this role are now closed." />
    @else
        @if ($parseError)
            <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ $parseError }}" />
        @endif

        @if ($step === 1)
            <div class="flex flex-col gap-6">
                <div wire:loading.remove wire:target="cv">
                    <flux:input type="file" wire:model="cv" label="Upload your CV (PDF or Word document) — optional" />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Selecting a file will analyse it and pre-fill the next steps automatically.</p>
                </div>

                <div wire:loading wire:target="cv" class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon.loading class="size-4" />
                    Analysing your CV…
                </div>

                @error('cv') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:button type="button" variant="ghost" wire:click="skipCv" wire:loading.attr="disabled" wire:target="cv">
                    Skip — I'll enter my details manually
                </flux:button>
            </div>
        @endif

        @if ($step === 2)
            <form wire:submit="savePersonalDetails" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:select wire:model="title" label="Title" placeholder="Select…">
                    @foreach (['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'] as $t)
                        <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div></div>

                <flux:input wire:model="first_name" label="First Name" required />
                <flux:input wire:model="last_name" label="Last Name" required />

                <flux:input type="email" wire:model="email" label="Email" required />
                <flux:input wire:model="phone" label="Phone" />

                <flux:input wire:model="mobile" label="Mobile" />
                <div></div>

                <div
                    class="col-span-full flex flex-col gap-4"
                    x-data="{ get isDark() { return document.documentElement.classList.contains('dark'); } }"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Address</p>

                        <flux:button type="button" wire:click="$toggle('address_manual')" variant="ghost" size="sm">
                            @if ($address_manual)
                                Search address instead
                            @else
                                Enter address manually
                            @endif
                        </flux:button>
                    </div>

                    @unless ($address_manual)
                        <div class="relative">
                            <flux:input
                                wire:model.live.debounce.500ms="address_search"
                                label="Search Address"
                                placeholder="Start typing an address or postcode…"
                                icon="magnifying-glass"
                            />

                            @if (! empty($address_suggestions))
                                <div
                                    class="absolute z-10 mt-1 w-full rounded-lg border shadow-lg"
                                    :style="isDark
                                        ? 'background-color: #27272a; border-color: rgba(255,255,255,0.1);'
                                        : 'background-color: #ffffff; border-color: #e4e4e7;'"
                                >
                                    @foreach ($address_suggestions as $placeId => $label)
                                        <button
                                            type="button"
                                            wire:click="selectAddress('{{ $placeId }}')"
                                            class="block w-full px-3 py-2 text-left text-sm"
                                            :class="isDark ? 'text-zinc-100 hover:bg-zinc-700' : 'text-zinc-900 hover:bg-zinc-100'"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endunless

                    @if ($address_manual || $address || $postcode)
                        <flux:input wire:model="address" label="Address" placeholder="123 Example Street" />

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="city" label="City / Town" />
                            <flux:input wire:model="postcode" label="Postcode" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="county" label="County" />
                            <flux:input wire:model="country" label="Country" />
                        </div>
                    @endif
                </div>

                <flux:button type="submit" variant="primary" class="col-span-full">Continue</flux:button>
            </form>
        @endif

        @if ($step === 3)
            <form wire:submit="saveEmploymentHistory" class="flex flex-col gap-6">
                @foreach ($employmentHistories as $index => $job)
                    <div wire:key="employment-history-{{ $index }}" class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                Job {{ $index + 1 }}
                            </p>

                            @if (count($employmentHistories) > 1)
                                <flux:button type="button" size="sm" variant="danger" wire:click="removeEmploymentHistory({{ $index }})">
                                    Remove
                                </flux:button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:input wire:model="employmentHistories.{{ $index }}.job_title" label="Job Title" />
                            <flux:input wire:model="employmentHistories.{{ $index }}.company_name" label="Employer" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:input type="date" wire:model="employmentHistories.{{ $index }}.worked_from" label="Worked From" max="{{ now()->format('Y-m-d') }}" />
                            <flux:input type="date" wire:model="employmentHistories.{{ $index }}.worked_to" label="Worked To" max="{{ now()->format('Y-m-d') }}" />
                        </div>
                    </div>
                @endforeach

                <flux:button type="button" variant="ghost" wire:click="addEmploymentHistory" class="self-start">
                    Add another job
                </flux:button>

                <flux:button type="submit" variant="primary">Continue</flux:button>
            </form>
        @endif

        @if ($step === 4)
            <form wire:submit="saveSkills" class="flex flex-col gap-6">
                <flux:select wire:model="qualification_id" label="Qualification (optional)" placeholder="Select…">
                    @foreach ($this->qualifications as $qualification)
                        <flux:select.option value="{{ $qualification->id }}">{{ $qualification->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div>
                    <flux:label>Skills</flux:label>
                    <flux:error name="skill_ids" />

                    <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($this->skills as $skill)
                            <flux:checkbox wire:model="skill_ids" value="{{ $skill->id }}" label="{{ $skill->parent_id ? '↳ '.$skill->name : $skill->name }}" />
                        @endforeach
                    </div>
                </div>

                <flux:button type="submit" variant="primary">Submit Application</flux:button>
            </form>
        @endif

        @if ($step === 5)
            <div class="flex flex-col items-center gap-3 py-6 text-center">
                <flux:icon.check-circle class="size-12 text-emerald-500" />
                <flux:heading size="lg">Application Received</flux:heading>
                @if ($isReturningCandidate)
                    <flux:subheading>
                        Welcome back! We've updated your details and registered your interest in {{ $vacancy->title }}. We'll be in touch if it's a good match.
                    </flux:subheading>
                @else
                    <flux:subheading>
                        Thanks for applying for {{ $vacancy->title }}. We'll be in touch if your application is a good match.
                    </flux:subheading>
                @endif
            </div>
        @endif
    @endif
</div>
