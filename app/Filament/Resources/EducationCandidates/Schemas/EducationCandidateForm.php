<?php

namespace App\Filament\Resources\EducationCandidates\Schemas;

use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\Nationality;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Exceptions\Dbs\DbsUpdateServiceException;
use App\Filament\Resources\Vetting\VettingResource;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Models\CandidateDocument;
use App\Models\CandidateSkill;
use App\Models\EducationCandidate;
use App\Models\Qualification;
use App\Models\User;
use App\Services\DbsUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EducationCandidateForm
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
                                            ->maxLength(255),
                                        TextInput::make('middle_name')
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->maxLength(255),
                                        TextInput::make('previous_surname')
                                            ->maxLength(255),
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
                                                ->where('company_id', Auth::user()->company_id)
                                                ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->searchable(),
                                    ]),

                                Section::make('Contact Details')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(255)
                                            ->rule('regex:/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/')
                                            ->validationMessages([
                                                'regex' => 'Please enter a valid UK mobile number.',
                                            ]),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->maxLength(255)
                                            ->rule('regex:/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/')
                                            ->validationMessages([
                                                'regex' => 'Please enter a valid UK mobile number.',
                                            ]),
                                    ]),

                                Section::make('Address')
                                    ->columns(2)
                                    ->schema([
                                        Hidden::make('address_manual')
                                            ->default(false)
                                            ->dehydrated(false),

                                        Hidden::make('address_suggestions')
                                            ->dehydrated(false),

                                        Actions::make([
                                            Action::make('toggle_manual')
                                                ->label(fn (Get $get) => $get('address_manual')
                                                    ? 'Search address instead'
                                                    : 'Enter address manually'
                                                )
                                                ->icon(fn (Get $get) => $get('address_manual')
                                                    ? 'heroicon-o-magnifying-glass'
                                                    : 'heroicon-o-pencil'
                                                )
                                                ->color('gray')
                                                ->action(function (Get $get, Set $set) {
                                                    $set('address_manual', ! $get('address_manual'));
                                                }),
                                        ])->columnSpanFull(),

                                        TextInput::make('address_search')
                                            ->label('Search Address')
                                            ->placeholder('Start typing an address or postcode...')
                                            ->prefixIcon('heroicon-o-magnifying-glass')
                                            ->live(debounce: 500)
                                            ->hidden(fn (Get $get) => (bool) $get('address_manual'))
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                if (! $state || strlen($state) < 3) {
                                                    $set('address_suggestions', []);

                                                    return;
                                                }

                                                $response = Http::withHeaders([
                                                    'X-Goog-Api-Key' => config('services.google.places_key'),
                                                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text',
                                                ])->post('https://places.googleapis.com/v1/places:autocomplete', [
                                                    'input' => $state,
                                                    'includedRegionCodes' => ['gb'],
                                                ]);

                                                if ($response->failed()) {
                                                    $set('address_suggestions', []);

                                                    return;
                                                }

                                                $suggestions = collect($response->json('suggestions') ?? [])
                                                    ->mapWithKeys(fn ($s) => [
                                                        $s['placePrediction']['placeId'] => $s['placePrediction']['text']['text'],
                                                    ])
                                                    ->toArray();

                                                $set('address_suggestions', $suggestions);
                                            })
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Select::make('address_place_id')
                                            ->label('Select Address')
                                            ->options(fn (Get $get) => $get('address_suggestions') ?? [])
                                            ->live()
                                            ->hidden(fn (Get $get) => empty($get('address_suggestions')) || (bool) $get('address_manual'))
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                if (! $state) {
                                                    return;
                                                }

                                                $response = Http::withHeaders([
                                                    'X-Goog-Api-Key' => config('services.google.places_key'),
                                                    'X-Goog-FieldMask' => 'addressComponents,formattedAddress',
                                                ])->get("https://places.googleapis.com/v1/places/{$state}");

                                                if ($response->failed()) {
                                                    return;
                                                }

                                                $components = collect($response->json('addressComponents') ?? []);

                                                $getComponent = fn (string $type) => $components
                                                    ->first(fn ($c) => in_array($type, $c['types'] ?? []))['longText'] ?? '';

                                                $streetNumber = $getComponent('street_number');
                                                $route = $getComponent('route');

                                                $set('address', collect([$streetNumber, $route])->filter()->implode(' '));
                                                $set('city', $getComponent('postal_town') ?: $getComponent('locality'));
                                                $set('county', $getComponent('administrative_area_level_2'));
                                                $set('country', $getComponent('country'));
                                                $set('postcode', $getComponent('postal_code'));
                                                $set('address_search', $response->json('formattedAddress'));
                                                $set('address_suggestions', []);
                                            })
                                            ->placeholder('Select an address...')
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Textarea::make('address')
                                            ->columnSpanFull()
                                            ->hidden(fn (Get $get) => ! (bool) $get('address_manual') && empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('postcode')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => ! (bool) $get('address_manual') && empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('city')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => ! (bool) $get('address_manual') && empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('county')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => ! (bool) $get('address_manual') && empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('country')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => ! (bool) $get('address_manual') && empty($get('address')) && empty($get('postcode'))),
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
                                        Qualification::where('company_id', auth()->user()->company_id)
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
                                    ->label('Education & Qualification')
                                    ->columnSpanFull(),

                                RichEditor::make('employment_history')
                                    ->label('Employment History')
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
                                            ->mapWithKeys(fn (Availability $case) => [
                                                $case->value => $case->label(),
                                            ])
                                            ->toArray()
                                    )
                                    ->columns(3)
                                    ->columnSpanFull(),

                                CheckboxList::make('key_stages')
                                    ->label('KeyStages')
                                    ->options(
                                        collect(KeyStage::cases())
                                            ->mapWithKeys(fn (KeyStage $case) => [
                                                $case->value => $case->label(),
                                            ])
                                            ->toArray()
                                    )
                                    ->columns(3)
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
                                            ->label('Company / School')
                                            ->required()
                                            ->maxLength(255),
                                        DatePicker::make('worked_from')
                                            ->native(false),
                                        DatePicker::make('worked_to')
                                            ->native(false),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(function (array $state): ?string {
                                        $company = $state['company_name'] ?? '';

                                        $from = $state['worked_from'] ?? null;
                                        $to = $state['worked_to'] ?? null;

                                        $years = match (true) {
                                            filled($from) && filled($to) => Carbon::parse($from)->format('Y').' - '.Carbon::parse($to)->format('Y'),
                                            filled($from) => Carbon::parse($from)->format('Y').' - Present',
                                            default => null,
                                        };

                                        return trim($company.($years ? " ({$years})" : '')) ?: 'Job';
                                    })
                                    ->collapsible()
                                    ->collapsed()
                                    ->extraAttributes(['class' => 'employment-timeline'])
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
                                                    ->mapWithKeys(fn (ReferenceType $case) => [
                                                        $case->value => $case->label(),
                                                    ])
                                                    ->toArray()
                                            )
                                            ->required(),
                                        TextInput::make('title')
                                            ->maxLength(10),
                                        TextInput::make('first_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('job_title')
                                            ->maxLength(255),
                                        DatePicker::make('worked_from')
                                            ->native(false),
                                        DatePicker::make('worked_to')
                                            ->native(false),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->maxLength(255),
                                        TextInput::make('address')
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                        TextInput::make('city')
                                            ->label('City / Town')
                                            ->maxLength(255),
                                        TextInput::make('postcode')
                                            ->maxLength(10),
                                        TextInput::make('county')
                                            ->maxLength(255),
                                        TextInput::make('country')
                                            ->maxLength(255),
                                        Checkbox::make('consent_to_contact')
                                            ->label('Candidate consents to us contacting this referee')
                                            ->columnSpanFull(),
                                        Checkbox::make('contact_now')
                                            ->label('Contact this referee now')
                                            ->helperText('Switch off if the candidate isn\'t ready for this referee to be contacted yet.')
                                            ->default(true)
                                            ->columnSpanFull(),
                                        Select::make('status')
                                            ->options(
                                                collect(ReferenceStatus::cases())
                                                    ->mapWithKeys(fn (ReferenceStatus $case) => [
                                                        $case->value => $case->label(),
                                                    ])
                                                    ->toArray()
                                            )
                                            ->default(ReferenceStatus::Pending->value)
                                            ->required()
                                            ->live()
                                            ->suffixIcon(fn (Get $get) => ReferenceStatus::tryFrom($get('status') ?? '')?->icon())
                                            ->suffixIconColor(fn (Get $get) => ReferenceStatus::tryFrom($get('status') ?? '')?->color()),
                                        DatePicker::make('last_contacted')
                                            ->native(false),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(function (array $state): ?string {
                                        $name = trim(($state['first_name'] ?? '').' '.($state['last_name'] ?? '')) ?: 'Reference';

                                        $status = ReferenceStatus::tryFrom($state['status'] ?? '');

                                        return $status ? "{$name} — {$status->label()} {$status->emoji()}" : $name;
                                    })
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Compliance')
                            ->schema([
                                Actions::make([
                                    Action::make('viewVetting')
                                        ->label('View Vetting')
                                        ->icon('heroicon-o-shield-check')
                                        ->color('gray')
                                        ->url(fn (?EducationCandidate $record): ?string => $record ? VettingResource::getUrl('edit', ['record' => $record]) : null)
                                        ->openUrlInNewTab(),
                                ])->columnSpanFull(),

                                static::trnSection(),
                                static::dbsSection(),
                                static::rightToWorkSection(),
                                static::safeguardingSection(),
                            ]),
                    ]),
            ]);
    }

    protected static function trnSection(): Section
    {
        return Section::make('TRN, Sanctions and Restrictions')
            ->schema([
                TextEntry::make('trn_number')
                    ->label('TRN Number')
                    ->placeholder('Not set'),

                TextEntry::make('trn_issue_date')
                    ->label('TRA Date')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),

                TextEntry::make('sanctions')
                    ->label('Sanctions')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'yes' ? 'danger' : 'success'),

                TextEntry::make('restrictions')
                    ->label('Restrictions')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'yes' ? 'danger' : 'success'),

                TextEntry::make('sanction_restrictions_details')
                    ->label('Sanctions / Restrictions Details')
                    ->placeholder('None recorded')
                    ->visible(fn (?EducationCandidate $record): bool => $record?->sanctions === 'yes' || $record?->restrictions === 'yes')
                    ->columnSpanFull(),

                TextEntry::make('has_naric')
                    ->label('UK Naric')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                static::documentEntry('UK Naric Document', DocumentType::UkNaric),

                TextEntry::make('has_health_condition_or_disability')
                    ->label('Any Medical Issue')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),
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

                Actions::make([
                    Action::make('callUpdateService')
                        ->label('Call Update Service')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->visible(fn (?EducationCandidate $record): bool => filled($record?->dbs_certificate_number))
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

                static::documentEntry('DBS File (Front)', DocumentType::DbsFront),
                static::documentEntry('DBS File (Back)', DocumentType::DbsBack),

                TextEntry::make('overseas_police_clearance_check')
                    ->label('Has Overseas Police Check')
                    ->getStateUsing(fn (?EducationCandidate $record): string => static::overseasPoliceCheckDisplay($record)),
            ])
            ->columns(2);
    }

    protected static function rightToWorkSection(): Section
    {
        return Section::make('Right to Work')
            ->schema([
                TextEntry::make('right_to_work_type')
                    ->label('Right to Work Type')
                    ->formatStateUsing(fn (?EducationCandidate $record): string => static::rightToWorkTypeLabel($record))
                    ->placeholder('Not set'),

                TextEntry::make('visa_expiry_date')
                    ->label('Expiry Date')
                    ->date('d/m/Y')
                    ->placeholder('Not set')
                    ->visible(fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'visa'),

                TextEntry::make('visa_notes')
                    ->label('Notes')
                    ->placeholder('None recorded')
                    ->visible(fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'visa'),

                static::documentEntry(
                    'Right to Work Document',
                    DocumentType::Passport,
                    visible: fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'passport',
                ),
                static::documentEntry(
                    'Right to Work Document',
                    DocumentType::BirthCertificate,
                    visible: fn (?EducationCandidate $record): bool => $record?->right_to_work_type === 'birth_certificate',
                ),
            ])
            ->columns(2);
    }

    protected static function safeguardingSection(): Section
    {
        return Section::make('Safeguarding')
            ->schema([
                TextEntry::make('safeguarding_certified_date')
                    ->label('Certified On')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),

                static::documentEntry('Certificate', DocumentType::SafeguardingTraining),

                TextEntry::make('prevent_training_completed')
                    ->label('Prevent Training')
                    ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state))
                    ->placeholder('Not set'),

                TextEntry::make('application.declaration_accepted_at')
                    ->label('Keeping Children Safe in Education (Read on Application)')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),
            ])
            ->columns(2);
    }

    protected static function documentEntry(string $label, DocumentType $documentType, ?\Closure $visible = null): TextEntry
    {
        $entry = TextEntry::make("document_{$documentType->value}")
            ->label($label)
            ->getStateUsing(fn (?EducationCandidate $record): string => static::document($record, $documentType) ? 'Uploaded' : 'Not uploaded')
            ->badge()
            ->color(fn (?EducationCandidate $record): string => static::document($record, $documentType) ? 'success' : 'gray')
            ->url(fn (?EducationCandidate $record): ?string => static::documentUrl($record, $documentType))
            ->openUrlInNewTab();

        if ($visible) {
            $entry->visible($visible);
        }

        return $entry;
    }

    protected static function document(?EducationCandidate $record, DocumentType $documentType): ?CandidateDocument
    {
        return $record?->documents()->where('document_type', $documentType)->first();
    }

    protected static function documentUrl(?EducationCandidate $record, DocumentType $documentType): ?string
    {
        $document = static::document($record, $documentType);

        return $document
            ? Storage::disk('local')->temporaryUrl($document->path, now()->addMinutes(10))
            : null;
    }

    protected static function formatYesNo(?string $value): string
    {
        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            default => 'Not set',
        };
    }

    protected static function overseasPoliceCheckDisplay(?EducationCandidate $record): string
    {
        if (! $record) {
            return 'Not set';
        }

        if ($record->lived_overseas_six_months !== 'yes') {
            return 'Not applicable';
        }

        return match ($record->overseas_police_clearance_check) {
            'yes' => 'Yes',
            'no' => 'No',
            default => 'Not yet checked',
        };
    }

    protected static function rightToWorkTypeLabel(?EducationCandidate $record): string
    {
        return match ($record?->right_to_work_type) {
            'passport' => 'UK Passport',
            'visa' => 'Visa',
            'birth_certificate' => 'UK Birth Certificate',
            default => 'Not set',
        };
    }
}
