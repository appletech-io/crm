<?php

namespace App\Filament\Resources\EducationVetting\Schemas;

use App\Enums\DocumentType;
use App\Enums\Education\KeyStage;
use App\Exceptions\Dbs\DbsUpdateServiceException;
use App\Filament\Resources\EducationVetting\Pages\VettingWizard;
use App\Filament\Widgets\CandidateAdditionalDocuments;
use App\Filament\Widgets\CandidateDocumentStatus;
use App\Filament\Widgets\CandidateReferencesSummary;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Services\Ai\NiNumberVerificationService;
use App\Services\Ai\ProofOfAddressVerificationService;
use App\Services\Candidates\Document;
use App\Services\Education\CandidateVettingRequirements;
use App\Services\Education\DbsUpdateService;
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
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VettingSteps
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
            static::traChecks(),
            static::dbs(),
            static::references(),
            static::confirm(),
        ];

        $fieldsByIndex = [
            0 => static::personalDetailsFields(),
            2 => static::skillsFields(),
            4 => static::securityChecksFields(),
            5 => static::traChecksFields(),
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
            $isSecurityChecksStep = $index === 4;

            $step->afterValidation(function (?EducationCandidate $record, Get $get) use ($nextStepNumber, $fields, $relationships, $isPayRatesStep, $isSecurityChecksStep): void {
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

                if ($isSecurityChecksStep && $record->refresh()->dnuCandidate()) {
                    Notification::make()
                        ->danger()
                        ->title('Candidate flagged as DNU')
                        ->body('This candidate has failed a required security check and cannot continue through the compliance process.')
                        ->send();

                    throw new Halt;
                }

                $record->update(['compliance_step' => $nextStepNumber]);
            });
        }

        return $steps;
    }

    /** @param  array<string, array<string, mixed>>|null  $items */
    protected static function syncPayRates(EducationCandidate $record, ?array $items): void
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
        return ['qualification_id', 'key_stages'];
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
                            ->minValue(0)
                            ->rule('regex:/^\d+(\.\d{1,2})?$/')
                            ->validationMessages(['regex' => 'Please enter a valid monetary amount.']),
                        TextInput::make('half_day_rate')
                            ->label('Half Day Rate')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0)
                            ->rule('regex:/^\d+(\.\d{1,2})?$/')
                            ->validationMessages(['regex' => 'Please enter a valid monetary amount.']),
                        TextInput::make('hourly_rate')
                            ->label('Hourly Rate')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0)
                            ->rule('regex:/^\d+(\.\d{1,2})?$/')
                            ->validationMessages(['regex' => 'Please enter a valid monetary amount.']),
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

                        CheckboxList::make('key_stages')
                            ->label('Key Stages')
                            ->options(
                                collect(KeyStage::cases())
                                    ->mapWithKeys(fn (KeyStage $case) => [$case->value => $case->label()])
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

    protected static function references(): Step
    {
        return Step::make('References')
            ->schema([
                LivewireComponent::make(CandidateReferencesSummary::class)
                    ->key('candidate-references-summary'),
                LivewireComponent::make(CandidateAdditionalDocuments::class)
                    ->key('candidate-additional-documents'),
            ]);
    }

    protected static function confirm(): Step
    {
        return Step::make('Confirm')
            ->schema([
                Section::make('Vetting Checklist')
                    ->schema(fn (?EducationCandidate $record, VettingWizard $livewire): array => $record
                        ? collect(CandidateVettingRequirements::for($record, $livewire->qualificationManuallyConfirmed))
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
            'qualification' => Action::make('confirm_qualification')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm a qualification isn\'t required for this candidate')
                ->visible(fn (?EducationCandidate $record, VettingWizard $livewire): bool => ! $livewire->qualificationManuallyConfirmed
                    && ! $record?->documents()->where('document_type', DocumentType::Qualification)->exists())
                ->requiresConfirmation()
                ->modalHeading('Confirm qualification not required')
                ->modalDescription('Use this if this candidate does not require a qualification on file. This is not saved and only applies for the rest of this session — it will need confirming again next time this candidate is vetted.')
                ->modalSubmitActionLabel('Confirm')
                ->action(function (VettingWizard $livewire): void {
                    $livewire->qualificationManuallyConfirmed = true;
                }),
            'proof_of_address' => Action::make('confirm_proof_of_address')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm proof of address matches')
                ->requiresConfirmation()
                ->modalHeading('Confirm proof of address matches')
                ->modalDescription('Use this if the automated check failed, but you have manually verified the uploaded document matches the candidate\'s address.')
                ->modalSubmitActionLabel('Confirm match')
                ->action(fn (?EducationCandidate $record) => static::runManualConfirm(
                    $record, 'proof_of_address_match', 'proof_of_address_checked_at', 'Proof of address'
                )),
            'proof_of_ni' => Action::make('confirm_proof_of_ni')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm proof of NI matches')
                ->requiresConfirmation()
                ->modalHeading('Confirm proof of NI matches')
                ->modalDescription('Use this if the automated check failed, but you have manually verified the uploaded document matches the candidate\'s NI number.')
                ->modalSubmitActionLabel('Confirm match')
                ->action(fn (?EducationCandidate $record) => static::runManualConfirm(
                    $record, 'ni_number_match', 'ni_number_checked_at', 'Proof of NI'
                )),
            'visa_restrictions_checked' => Action::make('confirm_visa_restrictions')
                ->iconButton()
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->tooltip('Manually confirm visa restrictions have been checked')
                ->visible(fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'visa')
                ->requiresConfirmation()
                ->modalHeading('Confirm visa restrictions have been checked')
                ->modalDescription('Use this once you\'ve manually checked this candidate\'s visa conditions and restrictions.')
                ->modalSubmitActionLabel('Confirm checked')
                ->action(fn (?EducationCandidate $record) => static::runManualConfirm(
                    $record, 'right_to_work_checked', 'right_to_work_checked_date', 'Visa restrictions'
                )),
            default => null,
        };
    }

    protected static function runManualConfirm(?EducationCandidate $record, string $matchField, string $checkedAtField, string $label): void
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
                ->action(fn (?EducationCandidate $record) => static::runRecheck(
                    $record, ProofOfAddressVerificationService::class, 'proof of address'
                )),
            'proof_of_ni' => Action::make('recheck_proof_of_ni')
                ->iconButton()
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Recheck proof of NI')
                ->action(fn (?EducationCandidate $record) => static::runRecheck(
                    $record, NiNumberVerificationService::class, 'proof of NI'
                )),
            default => null,
        };
    }

    protected static function runRecheck(?EducationCandidate $record, string $serviceClass, string $label): void
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
            'barred_list_check', 'barred_list_check_date',
            'overseas_police_clearance_check', 'overseas_police_clearance_check_date',
            'visa_share_code', 'visa_issue_date', 'visa_expiry_date', 'visa_notes', 'right_to_work_expiry_date',
        ];
    }

    protected static function securityChecks(): Step
    {
        return Step::make('Security Checks')
            ->schema([
                Text::make('This candidate is flagged as DNU. They cannot progress any further through the compliance process.')
                    ->color('danger')
                    ->visible(fn (?EducationCandidate $record): bool => (bool) $record?->dnuCandidate()),

                Section::make('Barred List Check')
                    ->description('Check the candidate against the barred list via the Check a Teacher\'s Record service, then record the outcome below.')
                    ->schema([
                        Actions::make([
                            Action::make('checkBarredList')
                                ->label('Open Check a Teacher\'s Record')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('gray')
                                ->url('https://interactions.signin.education.gov.uk/eK44bmhHcJw1dQ7srAMq7/signin/username?clientid=checkrecordteacher&redirect_uri=https%3A%2F%2Fcheck-a-teachers-record.education.gov.uk%2Fcheck-records%2Fauth%2Fdfe%2Fcallback')
                                ->openUrlInNewTab(),
                        ])->columnSpanFull(),

                        Select::make('barred_list_check')
                            ->label('Cleared')
                            ->options(['yes' => 'Yes', 'no' => 'No'])
                            ->native(false)
                            ->required(),
                        DatePicker::make('barred_list_check_date')
                            ->label('Date Checked')
                            ->native(false),
                    ])
                    ->columns(2),

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
                    ->visible(fn (?EducationCandidate $record): bool => $record?->lived_overseas_six_months === 'yes'),

                Section::make('Visa')
                    ->schema([
                        TextInput::make('visa_share_code')
                            ->label('Share Code')
                            ->maxLength(255),
                        DatePicker::make('visa_issue_date')
                            ->native(false),
                        DatePicker::make('visa_expiry_date')
                            ->native(false),
                        Textarea::make('visa_notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'visa'),

                Section::make('Right to Work Document')
                    ->schema([
                        DatePicker::make('right_to_work_expiry_date')
                            ->label('Expiry Date')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->visible(fn (?EducationCandidate $record): bool => in_array($record?->right_to_work_type, ['visa', 'passport'], true)),
            ]);
    }

    /** @return array<int, string> */
    protected static function traChecksFields(): array
    {
        return [
            'trn_issue_date', 'sanctions', 'restrictions', 'sanction_restrictions_details',
            'safeguarding_certified_date', 'safeguarding_expiry_date',
            'benedicts_law_issue_date', 'benedicts_law_expiry_date',
        ];
    }

    protected static function traChecks(): Step
    {
        return Step::make('TRA Checks')
            ->schema([
                Section::make('Teacher Reference Number (TRN)')
                    ->description('Check the candidate\'s TRN via the DfE sign-in service, then record when it was issued.')
                    ->schema([
                        TextInput::make('trn_number')
                            ->label('TRN'),

                        Actions::make([
                            Action::make('checkTrn')
                                ->label('Check TRN')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('gray')
                                ->url('https://services.signin.education.gov.uk/')
                                ->openUrlInNewTab(),
                        ])->columnSpanFull(),

                        DatePicker::make('trn_issue_date')
                            ->label('Issue Date')
                            ->native(false)
                            ->required(fn (?EducationCandidate $record): bool => filled($record?->trn_number)),
                    ])
                    ->columns(2)
                    ->visible(fn (?EducationCandidate $record): bool => filled($record?->trn_number)),

                Section::make('Sanctions and Restrictions')
                    ->schema([
                        Select::make('sanctions')
                            ->label('Sanctions')
                            ->options(['yes' => 'Yes', 'no' => 'No'])
                            ->native(false)
                            ->live(),

                        Select::make('restrictions')
                            ->label('Restrictions')
                            ->options(['yes' => 'Yes', 'no' => 'No'])
                            ->native(false)
                            ->live(),

                        Textarea::make('sanction_restrictions_details')
                            ->label('Sanctions / Restrictions Details')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('sanctions') === 'yes' || $get('restrictions') === 'yes'),
                    ])
                    ->columns(2),

                Section::make('Safeguarding Training')
                    ->schema([
                        DatePicker::make('safeguarding_certified_date')
                            ->label('Certified On')
                            ->native(false),

                        DatePicker::make('safeguarding_expiry_date')
                            ->label('Expiry Date')
                            ->native(false),

                        Text::make(fn (?EducationCandidate $record): string => static::safeguardingDocument($record)
                            ? 'Safeguarding certificate uploaded'
                            : 'Safeguarding certificate not uploaded'
                        )
                            ->color(fn (?EducationCandidate $record): string => static::safeguardingDocument($record) ? 'success' : 'danger')
                            ->columnSpanFull(),

                        Actions::make([
                            Action::make('viewSafeguardingCertificate')
                                ->label('View Certificate')
                                ->icon('heroicon-o-eye')
                                ->color('gray')
                                ->url(fn (?EducationCandidate $record): ?string => static::safeguardingDocumentUrl($record))
                                ->openUrlInNewTab()
                                ->visible(fn (?EducationCandidate $record): bool => (bool) static::safeguardingDocument($record)),
                        ])->columnSpanFull(),
                    ]),

                Section::make('Benedict\'s Law Training')
                    ->schema([
                        DatePicker::make('benedicts_law_issue_date')
                            ->label('Issue Date')
                            ->native(false),

                        DatePicker::make('benedicts_law_expiry_date')
                            ->label('Expiry Date')
                            ->native(false),

                        Text::make(fn (?EducationCandidate $record): string => static::benedictsLawDocument($record)
                            ? 'Benedict\'s Law certificate uploaded'
                            : 'Benedict\'s Law certificate not uploaded'
                        )
                            ->color(fn (?EducationCandidate $record): string => static::benedictsLawDocument($record) ? 'success' : 'danger')
                            ->columnSpanFull(),

                        Actions::make([
                            Action::make('viewBenedictsLawCertificate')
                                ->label('View Certificate')
                                ->icon('heroicon-o-eye')
                                ->color('gray')
                                ->url(fn (?EducationCandidate $record): ?string => static::benedictsLawDocumentUrl($record))
                                ->openUrlInNewTab()
                                ->visible(fn (?EducationCandidate $record): bool => (bool) static::benedictsLawDocument($record)),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    protected static function dbsFields(): array
    {
        return ['dbs_certificate_number', 'dbs_checked_date', 'dbs_expiry_date'];
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

                        DatePicker::make('dbs_expiry_date')
                            ->label('Expiry Date')
                            ->native(false),

                        Actions::make([
                            Action::make('callUpdateService')
                                ->label('Call Update Service')
                                ->icon('heroicon-o-arrow-path')
                                ->color('primary')
                                ->action(function (?EducationCandidate $record): void {
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
                                    } catch (DbsUpdateServiceException $exception) {
                                        Notification::make()
                                            ->danger()
                                            ->title('DBS Update Service check failed')
                                            ->body($exception->getMessage())
                                            ->send();
                                    } catch (\Throwable) {
                                        Notification::make()
                                            ->danger()
                                            ->title('DBS Update Service check failed')
                                            ->body('Unable to reach the DBS Update Service. Please try again later.')
                                            ->send();
                                    }
                                }),
                        ])->columnSpanFull(),

                        Text::make(function (?EducationCandidate $record): string {
                            if (! $record?->update_service_response) {
                                return 'Not yet checked.';
                            }

                            $checkedAt = $record->update_service_checked_at?->format('d/m/Y');

                            return $checkedAt
                                ? "Last result: {$record->update_service_response} (checked {$checkedAt})"
                                : "Last result: {$record->update_service_response}";
                        })
                            ->color(fn (?EducationCandidate $record): string => filled($record?->update_service_response) ? 'success' : 'gray')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?EducationCandidate $record): bool => filled($record?->dbs_certificate_number)),

                Section::make('New DBS')
                    ->description('This candidate does not currently have a DBS on file. Once their new DBS certificate is received, check it and record the details below.')
                    ->schema([
                        DatePicker::make('dbs_checked_date')
                            ->label('Date Checked')
                            ->native(false),
                        TextInput::make('dbs_certificate_number')
                            ->label('DBS Certificate Number')
                            ->maxLength(255),
                        DatePicker::make('dbs_expiry_date')
                            ->label('Expiry Date')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->visible(fn (?EducationCandidate $record): bool => blank($record?->dbs_certificate_number)),
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
                                    ->visible(fn (?EducationCandidate $record): bool => ! static::photoDocument($record)),
                                Image::make(
                                    url: fn (?EducationCandidate $record): string => static::photoUrl($record),
                                    alt: 'Candidate photo',
                                )
                                    ->imageHeight(160)
                                    ->imageWidth(160)
                                    ->alignCenter()
                                    ->visible(fn (?EducationCandidate $record): bool => (bool) static::photoDocument($record)),
                            ]),

                        Section::make('Contact Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->telRegex('/^[0-9+\-.\s()]+$/')
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'regex' => 'Please enter a valid phone number.',
                                    ]),
                                TextInput::make('mobile')
                                    ->tel()
                                    ->telRegex('/^[0-9+\-.\s()]+$/')
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'regex' => 'Please enter a valid phone number.',
                                    ]),
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
                            ])
                            ->placeholder('Please select…'),
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
                            ->rule('regex:/^[A-Za-z]{2}[0-9]{6}[A-Za-z]$/')
                            ->validationMessages([
                                'regex' => 'Please enter a valid National Insurance number (e.g. QQ123456C).',
                            ])
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

    protected static function photoDocument(?EducationCandidate $record): ?CandidateDocument
    {
        /** @var CandidateDocument|null $document */
        $document = $record?->documents->firstWhere('document_type', DocumentType::Photo);

        return $document;
    }

    protected static function photoUrl(?EducationCandidate $record): string
    {
        return Storage::disk(config('filesystems.default'))->temporaryUrl(
            static::photoDocument($record)->path,
            now()->addMinutes(10)
        );
    }

    protected static function safeguardingDocument(?EducationCandidate $record): ?CandidateDocument
    {
        /** @var CandidateDocument|null $document */
        $document = $record?->documents->firstWhere('document_type', DocumentType::SafeguardingTraining);

        return $document;
    }

    protected static function safeguardingDocumentUrl(?EducationCandidate $record): ?string
    {
        $document = static::safeguardingDocument($record);

        return $document
            ? Document::viewUrl($document->path)
            : null;
    }

    protected static function benedictsLawDocument(?EducationCandidate $record): ?CandidateDocument
    {
        /** @var CandidateDocument|null $document */
        $document = $record?->documents->firstWhere('document_type', DocumentType::BenedictsLaw);

        return $document;
    }

    protected static function benedictsLawDocumentUrl(?EducationCandidate $record): ?string
    {
        $document = static::benedictsLawDocument($record);

        return $document
            ? Document::viewUrl($document->path)
            : null;
    }
}
