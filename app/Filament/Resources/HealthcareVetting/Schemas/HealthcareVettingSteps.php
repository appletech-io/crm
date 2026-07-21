<?php

namespace App\Filament\Resources\HealthcareVetting\Schemas;

use App\Enums\DocumentType;
use App\Enums\Healthcare\CareSetting;
use App\Filament\Widgets\CandidateDocumentStatus;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\HealthcareCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Services\Ai\NiNumberVerificationService;
use App\Services\Ai\ProofOfAddressVerificationService;
use App\Services\Healthcare\CandidateVettingRequirements;
use App\Services\Healthcare\DbsUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HealthcareVettingSteps
{
    /** @return array<int, Step> */
    public static function steps(): array
    {
        $steps = [
            static::personalDetails(),
            static::payRates(),
            static::skills(),
            static::documents(),
            static::securityChecks(),
            static::professionalRegistration(),
            static::dbs(),
            static::placeholder('References'),
            static::confirm(),
        ];

        $fieldsByIndex = [
            0 => static::personalDetailsFields(),
            2 => static::skillsFields(),
            4 => static::securityChecksFields(),
            5 => static::professionalRegistrationFields(),
            6 => static::dbsFields(),
        ];

        $relationshipsByIndex = [
            2 => ['skills'],
        ];

        foreach ($steps as $index => $step) {
            $nextStepNumber = $index + 2;
            $fields = $fieldsByIndex[$index] ?? [];
            $relationships = $relationshipsByIndex[$index] ?? [];
            $isPayRatesStep = $index === 1;

            $step->afterValidation(function (?HealthcareCandidate $record, Get $get) use ($nextStepNumber, $fields, $relationships, $isPayRatesStep): void {
                if (! $record) {
                    return;
                }

                $data = collect($fields)
                    ->mapWithKeys(fn (string $field): array => [$field => $get($field)])
                    ->toArray();

                if (array_key_exists('ni_number', $data) && filled($data['ni_number'])) {
                    $data['ni_number'] = strtoupper($data['ni_number']);
                }

                $record->update($data);

                foreach ($relationships as $relationship) {
                    $record->{$relationship}()->sync($get($relationship) ?? []);
                }

                if ($isPayRatesStep) {
                    static::syncPayRates($record, $get('payRates'));
                }

                $record->update(['compliance_step' => $nextStepNumber]);
            });
        }

        return $steps;
    }

    /** @param  array<string, array<string, mixed>>|null  $items */
    protected static function syncPayRates(HealthcareCandidate $record, ?array $items): void
    {
        $items ??= [];

        $idsToKeep = collect($items)
            ->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'record-'))
            ->map(fn (string $key): int => (int) str_replace('record-', '', $key));

        $record->payRates()->whereNotIn('id', $idsToKeep->all() ?: [0])->delete();

        foreach ($items as $key => $item) {
            $attributes = [
                'job_title_id' => $item['job_title_id'] ?? null,
                'day_rate' => $item['day_rate'] ?? null,
                'half_day_rate' => $item['half_day_rate'] ?? null,
                'hourly_rate' => $item['hourly_rate'] ?? null,
            ];

            if (str_starts_with($key, 'record-')) {
                $record->payRates()->find((int) str_replace('record-', '', $key))?->update($attributes);
            } else {
                $record->payRates()->create($attributes);
            }
        }
    }

    /** @return array<int, string> */
    protected static function personalDetailsFields(): array
    {
        return [
            'title', 'first_name', 'middle_name', 'last_name', 'previous_surname',
            'ni_number', 'date_of_birth', 'place_of_birth',
            'email', 'phone', 'mobile',
            'address', 'postcode', 'city', 'county', 'country',
        ];
    }

    /** @return array<int, string> */
    protected static function skillsFields(): array
    {
        return ['qualification_id', 'care_settings'];
    }

    protected static function payRates(): Step
    {
        return Step::make('Pay Rates')
            ->schema([
                Repeater::make('payRates')
                    ->relationship()
                    ->hiddenLabel()
                    ->schema([
                        Select::make('job_title_id')
                            ->label('Job Title')
                            ->options(fn (): array => JobTitle::query()
                                ->where('company_id', Auth::user()->company_id)
                                ->where('industry_id', active_industry_id())
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->searchable()
                            ->columnSpanFull(),
                        TextInput::make('day_rate')
                            ->label('Day Rate')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('half_day_rate')
                            ->label('Half Day Rate')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('hourly_rate')
                            ->label('Hourly Rate')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                    ])
                    ->columns(3)
                    ->itemLabel(fn (?array $state): ?string => filled($state['job_title_id'] ?? null)
                        ? JobTitle::find($state['job_title_id'])?->name
                        : 'Pay Rate'
                    )
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    protected static function skills(): Step
    {
        return Step::make('Skills')
            ->schema([
                Section::make('Skills')
                    ->schema([
                        Select::make('qualification_id')
                            ->label('Qualification')
                            ->options(fn (): array => Qualification::query()
                                ->where('company_id', Auth::user()->company_id)
                                ->where('industry_id', active_industry_id())
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->searchable()
                            ->columnSpanFull(),

                        Select::make('skills')
                            ->label('Skills')
                            ->multiple()
                            ->relationship(
                                name: 'skills',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('candidate_skills.company_id', Auth::user()->company_id)
                                    ->where('candidate_skills.industry_id', active_industry_id())
                                    ->orderByRaw('COALESCE(parent_id, candidate_skills.id), parent_id IS NOT NULL, candidate_skills.name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (CandidateSkill $record): string => $record->parent_id
                                ? '↳ '.$record->name
                                : $record->name
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                $selectedIds = collect($get('skills') ?? []);

                                $parentIds = CandidateSkill::whereIn('id', $selectedIds)
                                    ->whereNotNull('parent_id')
                                    ->pluck('parent_id');

                                $set('skills', $selectedIds->merge($parentIds)->unique()->values()->all());
                            })
                            ->columnSpanFull(),

                        CheckboxList::make('care_settings')
                            ->label('Care Settings')
                            ->options(
                                collect(CareSetting::cases())
                                    ->mapWithKeys(fn (CareSetting $case) => [$case->value => $case->label()])
                                    ->toArray()
                            )
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function documents(): Step
    {
        return Step::make('Documents')
            ->schema([
                LivewireComponent::make(CandidateDocumentStatus::class)
                    ->key('candidate-document-status'),
            ]);
    }

    protected static function confirm(): Step
    {
        return Step::make('Confirm')
            ->schema([
                Section::make('Vetting Checklist')
                    ->schema(fn (?HealthcareCandidate $record): array => $record
                        ? collect(CandidateVettingRequirements::for($record))
                            ->map(function (array $check, string $key): Flex {
                                $items = [
                                    Icon::make(Heroicon::InformationCircle)
                                        ->tooltip($check['description'])
                                        ->color('gray')
                                        ->grow(false),
                                    Text::make($check['label']),
                                ];

                                if ($manualConfirmAction = static::manualConfirmAction($key)) {
                                    $items[] = $manualConfirmAction;
                                }

                                if ($recheckAction = static::recheckAction($key)) {
                                    $items[] = $recheckAction;
                                }

                                $items[] = Icon::make($check['complete'] ? Heroicon::CheckCircle : Heroicon::XCircle)
                                    ->color($check['complete'] ? 'success' : 'danger')
                                    ->grow(false);

                                return Flex::make($items);
                            })
                            ->values()
                            ->all()
                        : []
                    )
                    ->columns(2),
            ]);
    }

    protected static function manualConfirmAction(string $key): ?Action
    {
        return match ($key) {
            'proof_of_address' => Action::make('confirm_proof_of_address')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm proof of address matches')
                ->requiresConfirmation()
                ->modalHeading('Confirm proof of address matches')
                ->modalSubmitActionLabel('Confirm match')
                ->action(fn (?HealthcareCandidate $record) => static::runManualConfirm(
                    $record, 'proof_of_address_match', 'proof_of_address_checked_at', 'Proof of address'
                )),
            'proof_of_ni' => Action::make('confirm_proof_of_ni')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm proof of NI matches')
                ->requiresConfirmation()
                ->modalHeading('Confirm proof of NI matches')
                ->modalSubmitActionLabel('Confirm match')
                ->action(fn (?HealthcareCandidate $record) => static::runManualConfirm(
                    $record, 'ni_number_match', 'ni_number_checked_at', 'Proof of NI'
                )),
            default => null,
        };
    }

    protected static function runManualConfirm(?HealthcareCandidate $record, string $matchField, string $checkedAtField, string $label): void
    {
        if (! $record) {
            return;
        }

        $record->update([
            $matchField => 'yes',
            $checkedAtField => now(),
        ]);

        Notification::make()
            ->success()
            ->title("{$label} manually confirmed")
            ->send();
    }

    protected static function recheckAction(string $key): ?Action
    {
        return match ($key) {
            'proof_of_address' => Action::make('recheck_proof_of_address')
                ->iconButton()
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Recheck proof of address')
                ->action(fn (?HealthcareCandidate $record) => static::runRecheck(
                    $record, ProofOfAddressVerificationService::class, 'proof of address'
                )),
            'proof_of_ni' => Action::make('recheck_proof_of_ni')
                ->iconButton()
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Recheck proof of NI')
                ->action(fn (?HealthcareCandidate $record) => static::runRecheck(
                    $record, NiNumberVerificationService::class, 'proof of NI'
                )),
            default => null,
        };
    }

    protected static function runRecheck(?HealthcareCandidate $record, string $serviceClass, string $label): void
    {
        if (! $record) {
            return;
        }

        try {
            $matches = app($serviceClass)->verify($record);

            Notification::make()
                ->success()
                ->title(ucfirst($label).' rechecked')
                ->body($matches
                    ? 'The uploaded document matches the candidate\'s stored details.'
                    : 'The uploaded document does not match the candidate\'s stored details.'
                )
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title("Unable to recheck {$label}")
                ->body($exception->getMessage())
                ->send();
        }
    }

    /** @return array<int, string> */
    protected static function securityChecksFields(): array
    {
        return [
            'overseas_police_clearance_check', 'overseas_police_clearance_check_date',
            'visa_issue_date', 'visa_expiry_date', 'visa_notes',
        ];
    }

    protected static function securityChecks(): Step
    {
        return Step::make('Security Checks')
            ->schema([
                Text::make('This candidate is flagged as DNU. They cannot progress any further through the compliance process.')
                    ->color('danger')
                    ->visible(fn (?HealthcareCandidate $record): bool => (bool) $record?->dnuCandidate()),

                Section::make('Overseas Police Clearance')
                    ->schema([
                        Select::make('overseas_police_clearance_check')
                            ->label('Cleared')
                            ->options(['yes' => 'Yes', 'no' => 'No'])
                            ->native(false)
                            ->required(),
                        DatePicker::make('overseas_police_clearance_check_date')
                            ->label('Date Checked')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->visible(fn (?HealthcareCandidate $record): bool => $record?->lived_overseas_six_months === 'yes'),

                Section::make('Visa')
                    ->schema([
                        DatePicker::make('visa_issue_date')
                            ->native(false),
                        DatePicker::make('visa_expiry_date')
                            ->native(false),
                        Textarea::make('visa_notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?HealthcareCandidate $record): bool => $record?->right_to_work_type === 'visa'),
            ]);
    }

    /** @return array<int, string> */
    protected static function professionalRegistrationFields(): array
    {
        return ['professional_registration_body', 'professional_registration_number', 'professional_registration_checked_at'];
    }

    protected static function professionalRegistration(): Step
    {
        return Step::make('Professional Registration')
            ->schema([
                Section::make('Professional Registration Check')
                    ->description('Check the candidate\'s registration with their professional body (e.g. NMC, HCPC), then record the details below.')
                    ->schema([
                        TextInput::make('professional_registration_body')
                            ->label('Registration Body')
                            ->placeholder('e.g. NMC, HCPC')
                            ->maxLength(255),
                        TextInput::make('professional_registration_number')
                            ->label('Registration Number')
                            ->maxLength(255),
                        DatePicker::make('professional_registration_checked_at')
                            ->label('Date Checked')
                            ->native(false),
                    ])
                    ->columns(3),
            ]);
    }

    /** @return array<int, string> */
    protected static function dbsFields(): array
    {
        return ['dbs_certificate_number', 'dbs_checked_date'];
    }

    protected static function dbs(): Step
    {
        return Step::make('DBS')
            ->schema([
                Section::make('DBS Update Service')
                    ->schema([
                        TextInput::make('dbs_certificate_number')
                            ->label('DBS Certificate Number')
                            ->disabled(),

                        Actions::make([
                            Action::make('callUpdateService')
                                ->label('Call Update Service')
                                ->icon('heroicon-o-arrow-path')
                                ->color('primary')
                                ->action(function (?HealthcareCandidate $record): void {
                                    if (! $record) {
                                        return;
                                    }

                                    try {
                                        $status = app(DbsUpdateService::class)->check($record);

                                        Notification::make()
                                            ->success()
                                            ->title('DBS Update Service checked')
                                            ->body("Status: {$status}")
                                            ->send();
                                    } catch (\Throwable $exception) {
                                        Notification::make()
                                            ->danger()
                                            ->title('DBS Update Service check failed')
                                            ->body($exception->getMessage())
                                            ->send();
                                    }
                                }),
                        ])->columnSpanFull(),

                        Text::make(function (?HealthcareCandidate $record): string {
                            if (! $record?->update_service_response) {
                                return 'Not yet checked.';
                            }

                            $checkedAt = $record->update_service_checked_at?->format('d/m/Y');

                            return $checkedAt
                                ? "Last result: {$record->update_service_response} (checked {$checkedAt})"
                                : "Last result: {$record->update_service_response}";
                        })
                            ->color(fn (?HealthcareCandidate $record): string => filled($record?->update_service_response) ? 'success' : 'gray')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?HealthcareCandidate $record): bool => filled($record?->dbs_certificate_number)),

                Section::make('New DBS')
                    ->description('This candidate does not currently have a DBS on file. Once their new DBS certificate is received, check it and record the details below.')
                    ->schema([
                        DatePicker::make('dbs_checked_date')
                            ->label('Date Checked')
                            ->native(false),
                        TextInput::make('dbs_certificate_number')
                            ->label('DBS Certificate Number')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->visible(fn (?HealthcareCandidate $record): bool => blank($record?->dbs_certificate_number)),
            ]);
    }

    protected static function personalDetails(): Step
    {
        return Step::make('Personal Details')
            ->schema([
                Grid::make(2)
                    ->schema([
                        Section::make('Photo')
                            ->schema([
                                Text::make('No photo uploaded.')
                                    ->color('gray')
                                    ->visible(fn (?HealthcareCandidate $record): bool => ! static::photoDocument($record)),
                                Image::make(
                                    url: fn (?HealthcareCandidate $record): string => Storage::disk('local')->temporaryUrl(
                                        static::photoDocument($record)->path,
                                        now()->addMinutes(10)
                                    ),
                                    alt: 'Candidate photo',
                                )
                                    ->imageHeight(160)
                                    ->imageWidth(160)
                                    ->alignCenter()
                                    ->visible(fn (?HealthcareCandidate $record): bool => (bool) static::photoDocument($record)),
                            ]),

                        Section::make('Contact Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('mobile')
                                    ->tel()
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        Select::make('title')
                            ->options([
                                'Mr' => 'Mr',
                                'Mrs' => 'Mrs',
                                'Miss' => 'Miss',
                                'Ms' => 'Ms',
                                'Dr' => 'Dr',
                                'Prof' => 'Prof',
                            ]),
                        TextInput::make('first_name')
                            ->label('Name')
                            ->maxLength(255),
                        TextInput::make('middle_name')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->maxLength(255),
                        TextInput::make('previous_surname')
                            ->label('Previous Name')
                            ->maxLength(255),
                        TextInput::make('ni_number')
                            ->label('NI Number')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null)
                            ->maxLength(255),
                        DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->native(false),
                        TextInput::make('place_of_birth')
                            ->maxLength(255),
                    ]),

                Section::make('Address')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->columnSpanFull(),
                        TextInput::make('postcode')
                            ->maxLength(255),
                        TextInput::make('city')
                            ->maxLength(255),
                        TextInput::make('county')
                            ->maxLength(255),
                        TextInput::make('country')
                            ->maxLength(255),
                    ]),
            ]);
    }

    protected static function placeholder(string $title): Step
    {
        return Step::make($title)
            ->schema([
                Text::make('This step has not been built yet.')
                    ->color('gray'),
            ]);
    }

    protected static function photoDocument(?HealthcareCandidate $record): ?CandidateDocument
    {
        /** @var CandidateDocument|null $document */
        $document = $record?->documents->firstWhere('document_type', DocumentType::Photo);

        return $document;
    }
}
