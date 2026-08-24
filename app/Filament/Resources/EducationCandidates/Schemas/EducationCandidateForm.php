<?php

namespace App\Filament\Resources\EducationCandidates\Schemas;

use App\Actions\References\ResendReferenceRequestEmail;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\Integration;
use App\Enums\Nationality;
use App\Enums\PaymentMethod;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Exceptions\Dbs\DbsUpdateServiceException;
use App\Filament\Resources\EducationVetting\VettingResource;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Filament\Widgets\CandidateAvailabilityCalendar;
use App\Filament\Widgets\CandidateDocumentManager;
use App\Jobs\GenerateFormattedCv;
use App\Models\CandidateDocument;
use App\Models\CandidateReference;
use App\Models\CandidateSkill;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\PaymentProvider;
use App\Models\Qualification;
use App\Models\User;
use App\Services\Candidates\Document;
use App\Services\Education\DbsUpdateService;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                                Grid::make(3)
                                    ->schema([
                                        Section::make('Photo')
                                            ->schema([
                                                Text::make('No photo uploaded.')
                                                    ->color('gray')
                                                    ->visible(fn (?EducationCandidate $record): bool => ! static::document($record, DocumentType::Photo)),
                                                Image::make(
                                                    url: fn (?EducationCandidate $record): ?string => static::documentUrl($record, DocumentType::Photo),
                                                    alt: 'Candidate photo',
                                                )
                                                    ->imageHeight(160)
                                                    ->imageWidth(160)
                                                    ->alignCenter()
                                                    ->visible(fn (?EducationCandidate $record): bool => (bool) static::document($record, DocumentType::Photo)),
                                                TextEntry::make('average_rating')
                                                    ->hiddenLabel()
                                                    ->getStateUsing(fn (?EducationCandidate $record): string => $record?->average_rating !== null
                                                        ? number_format($record->average_rating, 1)." ★ ({$record->ratings_count} ".Str::plural('rating', $record->ratings_count).')'
                                                        : 'Not yet rated')
                                                    ->badge()
                                                    ->color(fn (?EducationCandidate $record): string => match (true) {
                                                        $record?->average_rating === null => 'gray',
                                                        $record->average_rating >= 4 => 'success',
                                                        $record->average_rating >= 3 => 'warning',
                                                        default => 'danger',
                                                    })
                                                    ->alignCenter()
                                                    ->visible(fn (?EducationCandidate $record): bool => $record !== null),
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
                                                Select::make('payment_method')
                                                    ->label('Payment Method')
                                                    ->options(PaymentMethod::options())
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('payment_provider_id', null)),
                                                Select::make('payment_provider_id')
                                                    ->label('Umbrella / Ltd Company')
                                                    ->helperText('The umbrella/Ltd company this candidate is paid through.')
                                                    ->options(fn (): array => PaymentProvider::query()
                                                        ->where('company_id', Auth::user()->company_id)
                                                        ->pluck('name', 'id')
                                                        ->toArray()
                                                    )
                                                    ->required(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Umbrella->value)
                                                    ->visible(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Umbrella->value)
                                                    ->searchable(),
                                                TextInput::make('bank_account_number')
                                                    ->label('Bank Account Number')
                                                    ->maxLength(8)
                                                    ->required(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Paye->value)
                                                    ->visible(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Paye->value),
                                                TextInput::make('bank_sort_code')
                                                    ->label('Sort Code')
                                                    ->placeholder('00-00-00')
                                                    ->maxLength(8)
                                                    ->required(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Paye->value)
                                                    ->visible(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Paye->value),
                                                TextInput::make('payroll_provider_id')
                                                    ->label('Payroll Provider ID')
                                                    ->helperText('This candidate\'s existing ID in the agency\'s payroll provider, if one already exists there.')
                                                    ->hidden()
                                                    ->dehydrated(false)
                                                    ->afterStateHydrated(function (TextInput $component, ?EducationCandidate $record): void {
                                                        $provider = Auth::user()->company->payroll_provider;

                                                        if ($record && $provider instanceof Integration) {
                                                            $component->state($record->providerExternalId($provider));
                                                        }
                                                    }),
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
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                if ($get('type') === ReferenceType::GapStatement->value) {
                                                    $set('consent_to_contact', false);
                                                    $set('contact_now', false);
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
                                        TextInput::make('address')
                                            ->maxLength(500)
                                            ->columnSpanFull()
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('city')
                                            ->label('City / Town')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('postcode')
                                            ->maxLength(10)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('county')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        TextInput::make('country')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        Checkbox::make('consent_to_contact')
                                            ->label('Candidate consents to us contacting this referee')
                                            ->columnSpanFull()
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
                                        Checkbox::make('contact_now')
                                            ->label('Contact this referee now')
                                            ->helperText('Switch off if the candidate isn\'t ready for this referee to be contacted yet.')
                                            ->default(true)
                                            ->columnSpanFull()
                                            ->visible(fn (Get $get): bool => $get('type') !== ReferenceType::GapStatement->value),
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
                                        Hidden::make('token'),
                                        Hidden::make('id')
                                            ->dehydrated(false),
                                        Actions::make([
                                            Action::make('viewResponse')
                                                ->label('View Reference Response')
                                                ->icon('heroicon-o-eye')
                                                ->color('gray')
                                                ->url(fn (Get $get): ?string => filled($get('token'))
                                                    ? route('reference.form', ['token' => $get('token')])
                                                    : null
                                                )
                                                ->openUrlInNewTab()
                                                ->visible(fn (Get $get): bool => filled($get('token'))),
                                            Action::make('resendReference')
                                                ->label(fn (Get $get): string => filled($get('token')) ? 'Resend Reference Email' : 'Send Reference Email')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->requiresConfirmation()
                                                ->visible(function (Get $get): bool {
                                                    $status = ReferenceStatus::tryFrom($get('status') ?? '');

                                                    return filled($get('id'))
                                                        && filled($get('email'))
                                                        && $get('contact_now')
                                                        && ! in_array($status, [ReferenceStatus::Submitted, ReferenceStatus::Confirmed], true);
                                                })
                                                ->action(function (Get $get, Set $set): void {
                                                    $reference = CandidateReference::find($get('id'));

                                                    if (! $reference) {
                                                        return;
                                                    }

                                                    ResendReferenceRequestEmail::run($reference);
                                                    $reference->refresh();

                                                    $set('token', $reference->token);
                                                    $set('status', $reference->status->value);
                                                    $set('last_contacted', $reference->last_contacted?->toDateString());

                                                    Notification::make()
                                                        ->success()
                                                        ->title('Reference email sent')
                                                        ->body("A reference request email has been sent to {$reference->email}.")
                                                        ->send();
                                                }),
                                        ])->columnSpanFull(),
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

                                Html::make(fn (?EducationCandidate $record): HtmlString => new HtmlString(
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
                                        ->visible(fn (?EducationCandidate $record): bool => $record?->documents->firstWhere('document_type', DocumentType::Cv) !== null)
                                        ->action(function (?EducationCandidate $record): void {
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
                                        ->url(fn (?EducationCandidate $record): ?string => $record ? VettingResource::getUrl('edit', ['record' => $record]) : null)
                                        ->openUrlInNewTab(),
                                ])->columnSpanFull(),

                                static::trnSection(),
                                static::dbsSection(),
                                static::rightToWorkSection(),
                                static::safeguardingSection(),
                                static::benedictsLawSection(),
                                static::medicalInformationSection(),
                                static::employmentConductSection(),
                                static::disclosureSection(),
                            ]),
                    ]),
            ]);
    }

    protected static function trnSection(): Section
    {
        return Section::make('TRN, Sanctions and Restrictions')
            ->schema([
                TextInput::make('trn_number')
                    ->label('TRN Number')
                    ->maxLength(255),

                DatePicker::make('trn_issue_date')
                    ->label('TRA Date')
                    ->native(false),

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
                    ->visible(fn (Get $get): bool => $get('sanctions') === 'yes' || $get('restrictions') === 'yes')
                    ->columnSpanFull(),

                Select::make('has_naric')
                    ->label('UK Naric')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false),

                static::documentEntry('UK Naric Document', DocumentType::UkNaric),
            ])
            ->columns(2);
    }

    protected static function dbsSection(): Section
    {
        return Section::make('DBS Checks')
            ->schema([
                TextInput::make('dbs_certificate_number')
                    ->label('DBS No')
                    ->maxLength(255),

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

                Select::make('overseas_police_clearance_check')
                    ->label('Has Overseas Police Check')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('lived_overseas_six_months') === 'yes'),

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
            ])
            ->columns(2);
    }

    protected static function rightToWorkSection(): Section
    {
        return Section::make('Right to Work')
            ->schema([
                Select::make('right_to_work_type')
                    ->label('Right to Work Type')
                    ->options([
                        'passport' => 'UK Passport',
                        'visa' => 'Visa',
                        'birth_certificate' => 'UK Birth Certificate',
                    ])
                    ->native(false)
                    ->live(),

                TextInput::make('visa_share_code')
                    ->label('Visa Share Code')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('right_to_work_type') === 'visa'),

                DatePicker::make('visa_expiry_date')
                    ->label('Expiry Date')
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('right_to_work_type') === 'visa'),

                Textarea::make('visa_notes')
                    ->label('Notes')
                    ->visible(fn (Get $get): bool => $get('right_to_work_type') === 'visa')
                    ->columnSpanFull(),

                DatePicker::make('right_to_work_expiry_date')
                    ->label('Right to Work Document Expiry Date')
                    ->native(false)
                    ->visible(fn (Get $get): bool => in_array($get('right_to_work_type'), ['visa', 'passport'], true)),

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
                DatePicker::make('safeguarding_certified_date')
                    ->label('Certified On')
                    ->native(false),

                DatePicker::make('safeguarding_expiry_date')
                    ->label('Expiry Date')
                    ->native(false),

                static::documentEntry('Certificate', DocumentType::SafeguardingTraining),

                TextEntry::make('application.terms_accepted_at')
                    ->label('Keeping Children Safe in Education (Read on Application)')
                    ->date('d/m/Y')
                    ->placeholder('Not set'),
            ])
            ->columns(2);
    }

    protected static function benedictsLawSection(): Section
    {
        return Section::make('Benedict\'s Law')
            ->schema([
                DatePicker::make('benedicts_law_issue_date')
                    ->label('Issue Date')
                    ->native(false),

                DatePicker::make('benedicts_law_expiry_date')
                    ->label('Expiry Date')
                    ->native(false),

                static::documentEntry('Certificate', DocumentType::BenedictsLaw),
            ])
            ->columns(2);
    }

    protected static function medicalInformationSection(): Section
    {
        return Section::make('Medical Information')
            ->schema([
                Select::make('has_health_condition_or_disability')
                    ->label('Health Condition or Disability')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Textarea::make('health_condition_details')
                    ->label('Details')
                    ->visible(fn (Get $get): bool => $get('has_health_condition_or_disability') === 'yes')
                    ->columnSpanFull(),

                Textarea::make('reasonable_accommodations')
                    ->label('Reasonable Accommodations Needed')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function employmentConductSection(): Section
    {
        return Section::make('Employment & Conduct')
            ->schema([
                Select::make('retired_early')
                    ->label('Retired Early')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Select::make('retired_early_medical_grounds')
                    ->label('On Medical Grounds')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('retired_early') === 'yes'),

                Select::make('dismissed_from_relevant_position')
                    ->label('Dismissed from a Relevant Position')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Textarea::make('dismissal_details')
                    ->label('Dismissal Details')
                    ->visible(fn (Get $get): bool => $get('dismissed_from_relevant_position') === 'yes')
                    ->columnSpanFull(),

                Select::make('subject_to_disciplinary_action')
                    ->label('Subject to Disciplinary Action')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Textarea::make('disciplinary_action_details')
                    ->label('Disciplinary Action Details')
                    ->visible(fn (Get $get): bool => $get('subject_to_disciplinary_action') === 'yes')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function disclosureSection(): Section
    {
        return Section::make('Disclosure & Rehabilitation of Offenders')
            ->schema([
                Select::make('lived_overseas_six_months')
                    ->label('Lived Overseas 6+ Months')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Textarea::make('overseas_details')
                    ->label('Overseas Details')
                    ->visible(fn (Get $get): bool => $get('lived_overseas_six_months') === 'yes')
                    ->columnSpanFull(),

                Select::make('unspent_convictions')
                    ->label('Unspent Convictions')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false)
                    ->live(),

                Textarea::make('unspent_convictions_details')
                    ->label('Conviction Details')
                    ->visible(fn (Get $get): bool => $get('unspent_convictions') === 'yes')
                    ->columnSpanFull(),

                Select::make('spent_convictions_not_protected')
                    ->label('Spent Convictions Not Protected')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->native(false),
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
            ? Document::viewUrl($document->path)
            : null;
    }
}
