<?php

namespace App\Filament\Resources\HealthcareCandidates\Schemas;

use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Healthcare\CareSetting;
use App\Enums\Nationality;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Filament\Resources\HealthcareVetting\HealthcareVettingResource;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Filament\Widgets\CandidateAvailabilityCalendar;
use App\Filament\Widgets\CandidateDocumentManager;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateDocument;
use App\Models\HealthcareCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\User;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class HealthcareCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Activity')
                            ->schema([
                                LivewireComponent::make(CandidateActivityTimeline::class)
                                    ->key('candidate-activity-timeline')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Personal Details')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Section::make('Photo')
                                            ->schema([
                                                Text::make('No photo uploaded.')
                                                    ->color('gray')
                                                    ->visible(fn (?HealthcareCandidate $record): bool => ! static::document($record, DocumentType::Photo)),
                                                Image::make(
                                                    url: fn (?HealthcareCandidate $record): ?string => static::documentUrl($record, DocumentType::Photo),
                                                    alt: 'Candidate photo',
                                                )
                                                    ->imageHeight(160)
                                                    ->imageWidth(160)
                                                    ->alignCenter()
                                                    ->visible(fn (?HealthcareCandidate $record): bool => (bool) static::document($record, DocumentType::Photo)),
                                                TextEntry::make('average_rating')
                                                    ->hiddenLabel()
                                                    ->getStateUsing(fn (?HealthcareCandidate $record): string => $record?->average_rating !== null
                                                        ? number_format($record->average_rating, 1)." ★ ({$record->ratings_count} ".Str::plural('rating', $record->ratings_count).')'
                                                        : 'Not yet rated')
                                                    ->badge()
                                                    ->color(fn (?HealthcareCandidate $record): string => match (true) {
                                                        $record?->average_rating === null => 'gray',
                                                        $record->average_rating >= 4 => 'success',
                                                        $record->average_rating >= 3 => 'warning',
                                                        default => 'danger',
                                                    })
                                                    ->alignCenter()
                                                    ->visible(fn (?HealthcareCandidate $record): bool => $record !== null),
                                            ]),

                                        Section::make('Personal Information')
                                            ->columnSpan(2)
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
                                                    ->maxLength(255),
                                                TextInput::make('middle_name')
                                                    ->maxLength(255),
                                                TextInput::make('last_name')
                                                    ->maxLength(255),
                                                TextInput::make('previous_surname')
                                                    ->maxLength(255),
                                                TextInput::make('ni_number')
                                                    ->label('NI Number')
                                                    ->rule('regex:/^[A-Za-z]{2}[0-9]{6}[A-Za-z]$/')
                                                    ->validationMessages([
                                                        'regex' => 'Please enter a valid National Insurance number (e.g. QQ123456C).',
                                                    ]),
                                                Select::make('gender')
                                                    ->options([
                                                        'male' => 'Male',
                                                        'female' => 'Female',
                                                        'non_binary' => 'Non-binary',
                                                        'prefer_not_to_say' => 'Prefer not to say',
                                                    ]),
                                                Select::make('nationality')
                                                    ->options(Nationality::options())
                                                    ->searchable(),
                                                DatePicker::make('date_of_birth')
                                                    ->label('Date of Birth')
                                                    ->native(false),
                                                Select::make('consultant_id')
                                                    ->label('Consultant')
                                                    ->options(fn (): array => User::role('consultant')
                                                        ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                                                        ->pluck('name', 'id')
                                                        ->toArray()
                                                    )
                                                    ->searchable(),
                                            ]),
                                    ]),

                                Section::make('Contact Details')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->telRegex('/^[0-9+\-.\s()]+$/')
                                            ->maxLength(255)
                                            ->validationMessages([
                                                'regex' => 'Please enter a valid phone number.',
                                            ]),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->rule('regex:/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/')
                                            ->maxLength(255)
                                            ->validationMessages([
                                                'regex' => 'Please enter a valid UK mobile number.',
                                            ]),
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

                                Section::make('Emergency Contact')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('emergency_contact_name')
                                            ->maxLength(255),
                                        TextInput::make('emergency_contact_number')
                                            ->tel()
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tab::make('Availability & Skills')
                            ->schema([
                                Select::make('qualification_id')
                                    ->label('Qualification')
                                    ->options(
                                        Qualification::where('company_id', Auth::user()->company_id)
                                            ->where('industry_id', active_industry_id())
                                            ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Important Notes about this candidate')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                RichEditor::make('education_and_qualification')
                                    ->label('Qualifications & Training')
                                    ->columnSpanFull(),

                                RichEditor::make('employment_history')
                                    ->label('Employment History Notes')
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
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                Select::make('candidatePools')
                                    ->label('Pools')
                                    ->multiple()
                                    ->relationship(
                                        name: 'candidatePools',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query
                                            ->where('candidate_pools.company_id', Auth::user()->company_id)
                                            ->where('candidate_pools.industry_id', active_industry_id())
                                            ->where(fn ($q) => $q
                                                ->where('candidate_pools.user_id', Auth::id())
                                                ->orWhere(fn ($q) => $q
                                                    ->where('candidate_pools.company_pool', true)
                                                    ->whereNull('candidate_pools.user_id')
                                                )
                                            ),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                CheckboxList::make('availability')
                                    ->label('Availability')
                                    ->options(
                                        collect(Availability::cases())
                                            ->mapWithKeys(fn (Availability $case) => [$case->value => $case->label()])
                                            ->toArray()
                                    )
                                    ->columns(3)
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

                        Tab::make('Pay Rates')
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
                            ]),

                        Tab::make('Employment History')
                            ->schema([
                                Repeater::make('employmentHistories')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->schema([
                                        TextInput::make('job_title')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('company_name')
                                            ->label('Employer')
                                            ->required()
                                            ->maxLength(255),
                                        DatePicker::make('worked_from')
                                            ->native(false),
                                        DatePicker::make('worked_to')
                                            ->native(false),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('References')
                            ->schema([
                                Repeater::make('references')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->schema([
                                        Select::make('type')
                                            ->label('Reference Type')
                                            ->options(
                                                collect(ReferenceType::cases())
                                                    ->mapWithKeys(fn (ReferenceType $case) => [$case->value => $case->label()])
                                                    ->toArray()
                                            )
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                if ($get('type') === ReferenceType::GapStatement->value) {
                                                    $set('consent_to_contact', false);
                                                }
                                            }),
                                        Textarea::make('statement')
                                            ->label('Statement')
                                            ->helperText('Briefly explain this period, e.g. "Travelling in the USA" or "Between roles, actively job seeking".')
                                            ->visible(fn (Get $get): bool => $get('type') === ReferenceType::GapStatement->value)
                                            ->required(fn (Get $get): bool => $get('type') === ReferenceType::GapStatement->value)
                                            ->columnSpanFull(),
                                        TextInput::make('title')
                                            ->maxLength(10)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('first_name')
                                            ->required(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value)
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->required(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value)
                                            ->maxLength(255),
                                        TextInput::make('job_title')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        DatePicker::make('worked_from')
                                            ->label('From')
                                            ->native(false),
                                        DatePicker::make('worked_to')
                                            ->label('To')
                                            ->native(false),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        Checkbox::make('consent_to_contact')
                                            ->label('Candidate consents to us contacting this referee')
                                            ->columnSpanFull()
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        Select::make('status')
                                            ->options(
                                                collect(ReferenceStatus::cases())
                                                    ->mapWithKeys(fn (ReferenceStatus $case) => [$case->value => $case->label()])
                                                    ->toArray()
                                            )
                                            ->default(ReferenceStatus::Pending->value)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documents')
                            ->schema([
                                LivewireComponent::make(CandidateDocumentManager::class)
                                    ->key('candidate-document-manager')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Formatted CV')
                            ->hidden(fn (?Model $record): bool => $record === null)
                            ->schema([
                                Section::make('Formatted CV')
                                    ->relationship('formattedCv')
                                    ->schema([
                                        RichEditor::make('content')
                                            ->hiddenLabel()
                                            ->extraAttributes(['class' => 'formatted-cv-editor'])
                                            ->columnSpanFull(),
                                    ]),

                                Html::make(fn (?HealthcareCandidate $record): HtmlString => new HtmlString(
                                    $record?->formattedCv?->pdf_path
                                        ? Blade::render(
                                            <<<'BLADE'
                                                <div class="flex flex-col gap-2">
                                                    <embed src="{{ $url }}" type="application/pdf" class="h-[70vh] w-full rounded-lg border border-gray-200 dark:border-gray-700" />
                                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-primary-600 underline dark:text-primary-400">{{ __('Open formatted CV in a new tab') }}</a>
                                                </div>
                                                BLADE,
                                            ['url' => Document::viewUrl($record->formattedCv->pdf_path)],
                                        )
                                        : '<p class="text-sm text-gray-500 dark:text-gray-400">No formatted CV yet — upload a CV on the Documents tab to generate one automatically.</p>'
                                )),

                                Actions::make([
                                    Action::make('regenerateFormattedCv')
                                        ->label('Regenerate from CV')
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('gray')
                                        ->visible(fn (?HealthcareCandidate $record): bool => $record?->documents->firstWhere('document_type', DocumentType::Cv) !== null)
                                        ->action(function (?HealthcareCandidate $record): void {
                                            $cvDocument = $record->documents->firstWhere('document_type', DocumentType::Cv);

                                            GenerateFormattedCv::dispatch($record, $cvDocument);

                                            Notification::make()
                                                ->success()
                                                ->title('Regenerating formatted CV')
                                                ->body('The updated version will appear here shortly.')
                                                ->send();
                                        }),
                                ])->columnSpanFull(),
                            ]),

                        Tab::make('Weekly Availability')
                            ->schema([
                                LivewireComponent::make(CandidateAvailabilityCalendar::class)
                                    ->key('candidate-availability-calendar')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Compliance')
                            ->schema([
                                Actions::make([
                                    Action::make('viewVetting')
                                        ->label('View Vetting')
                                        ->icon('heroicon-o-shield-check')
                                        ->color('gray')
                                        ->url(fn (?HealthcareCandidate $record): ?string => $record ? HealthcareVettingResource::getUrl('edit', ['record' => $record]) : null)
                                        ->openUrlInNewTab(),
                                ])->columnSpanFull(),

                                static::rightToWorkSection(),
                                static::dbsSection(),
                                static::professionalRegistrationSection(),
                                static::medicalInformationSection(),
                                static::employmentConductSection(),
                                static::disclosureSection(),
                            ]),
                    ]),
            ]);
    }

    protected static function rightToWorkSection(): Section
    {
        return Section::make('Right to Work')
            ->schema([
                TextEntry::make('right_to_work_type')
                    ->label('Right to Work Type')
                    ->placeholder('Not set'),

                DatePicker::make('right_to_work_expiry_date')
                    ->label('Right to Work Document Expiry Date')
                    ->native(false)
                    ->visible(fn (?HealthcareCandidate $record): bool => in_array($record?->right_to_work_type, ['visa', 'passport'], true)),
            ])
            ->columns(2);
    }

    protected static function dbsSection(): Section
    {
        return Section::make('DBS Checks')
            ->schema([
                TextEntry::make('dbs_certificate_number')
                    ->label('DBS No')
                    ->placeholder('Not set'),

                TextEntry::make('update_service_checked_at')
                    ->label('Update Service Issue Date')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),

                TextEntry::make('update_service_response')
                    ->label('DBS Update')
                    ->placeholder('Not yet checked')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray'),

                DatePicker::make('dbs_expiry_date')
                    ->label('Expiry Date')
                    ->native(false),
            ])
            ->columns(2);
    }

    protected static function professionalRegistrationSection(): Section
    {
        return Section::make('Professional Registration')
            ->schema([
                TextEntry::make('professional_registration_body')
                    ->label('Registration Body')
                    ->placeholder('Not set'),

                TextEntry::make('professional_registration_number')
                    ->label('Registration Number')
                    ->placeholder('Not set'),

                TextEntry::make('professional_registration_checked_at')
                    ->label('Checked On')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),
            ])
            ->columns(2);
    }

    protected static function medicalInformationSection(): Section
    {
        return Section::make('Medical Information')
            ->schema([
                TextEntry::make('has_health_condition_or_disability')
                    ->label('Health Condition or Disability')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'yes' ? 'warning' : 'success'),

                TextEntry::make('health_condition_details')
                    ->label('Details')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),

                TextEntry::make('reasonable_accommodations')
                    ->label('Reasonable Accommodations Needed')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function employmentConductSection(): Section
    {
        return Section::make('Employment & Conduct')
            ->schema([
                TextEntry::make('retired_early')
                    ->label('Retired Early')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('retired_early_medical_grounds')
                    ->label('On Medical Grounds')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('dismissed_from_relevant_position')
                    ->label('Dismissed from a Relevant Position')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('dismissal_details')
                    ->label('Dismissal Details')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),

                TextEntry::make('subject_to_disciplinary_action')
                    ->label('Subject to Disciplinary Action')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('disciplinary_action_details')
                    ->label('Disciplinary Action Details')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function disclosureSection(): Section
    {
        return Section::make('Disclosure & Rehabilitation of Offenders')
            ->schema([
                TextEntry::make('lived_overseas_six_months')
                    ->label('Lived Overseas 6+ Months')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('overseas_details')
                    ->label('Overseas Details')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),

                TextEntry::make('unspent_convictions')
                    ->label('Unspent Convictions')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('unspent_convictions_details')
                    ->label('Conviction Details')
                    ->placeholder('None recorded')
                    ->columnSpanFull(),

                TextEntry::make('spent_convictions_not_protected')
                    ->label('Spent Convictions Not Protected')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),
            ])
            ->columns(2);
    }

    protected static function formatYesNo(?string $value): string
    {
        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            default => 'Not set',
        };
    }

    protected static function document(?HealthcareCandidate $record, DocumentType $documentType): ?CandidateDocument
    {
        return $record?->documents()->where('document_type', $documentType)->first();
    }

    protected static function documentUrl(?HealthcareCandidate $record, DocumentType $documentType): ?string
    {
        $document = static::document($record, $documentType);

        return $document
            ? Storage::disk(config('filesystems.default'))->temporaryUrl($document->path, now()->addMinutes(10))
            : null;
    }
}
