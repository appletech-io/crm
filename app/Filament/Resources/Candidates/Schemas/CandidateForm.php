<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Actions\References\ResendReferenceRequestEmail;
use App\Enums\DocumentType;
use App\Enums\Integration;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Filament\Widgets\CandidateAvailabilityCalendar;
use App\Filament\Widgets\CandidateDocumentManager;
use App\Jobs\GenerateFormattedCv;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\CandidateReference;
use App\Models\CandidateSkill;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Candidates\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
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

class CandidateForm
{
    use HasPayrollProviderErrorAlert;

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
                                                    ->visible(fn (?Candidate $record): bool => ! static::document($record, DocumentType::Photo)),
                                                Image::make(
                                                    url: fn (?Candidate $record): ?string => static::documentUrl($record, DocumentType::Photo),
                                                    alt: 'Candidate photo',
                                                )
                                                    ->imageHeight(160)
                                                    ->imageWidth(160)
                                                    ->alignCenter()
                                                    ->visible(fn (?Candidate $record): bool => (bool) static::document($record, DocumentType::Photo)),
                                                TextEntry::make('average_rating')
                                                    ->hiddenLabel()
                                                    ->getStateUsing(fn (?Candidate $record): string => $record?->average_rating !== null
                                                        ? number_format($record->average_rating, 1)." ★ ({$record->ratings_count} ".Str::plural('rating', $record->ratings_count).')'
                                                        : 'Not yet rated')
                                                    ->badge()
                                                    ->color(fn (?Candidate $record): string => match (true) {
                                                        $record?->average_rating === null => 'gray',
                                                        $record->average_rating >= 4 => 'success',
                                                        $record->average_rating >= 3 => 'warning',
                                                        default => 'danger',
                                                    })
                                                    ->alignCenter()
                                                    ->visible(fn (?Candidate $record): bool => $record !== null),
                                            ]),

                                        Section::make('Personal Details')
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
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('last_name')
                                                    ->required()
                                                    ->maxLength(255),
                                                Select::make('job_title_id')
                                                    ->label('Job Title')
                                                    ->options(fn (): array => JobTitle::query()
                                                        ->where('company_id', Auth::user()->company_id)
                                                        ->where('industry_id', active_industry_id())
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->toArray()
                                                    )
                                                    ->searchable()
                                                    ->helperText('Determines which compliance items this candidate is required to complete.'),
                                                Select::make('consultant_id')
                                                    ->label('Consultant')
                                                    ->options(fn (): array => User::role('consultant')
                                                        ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                                                        ->orderBy('name')
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
                                            ->maxLength(255),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->maxLength(255),
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

                                Section::make('Payroll')
                                    ->visible(fn (?Candidate $record): bool => $record !== null)
                                    ->schema([
                                        // Shown regardless of whether the company currently has
                                        // Evertime enabled as its active payroll_provider — a
                                        // candidate can already have a synced/manually-entered ID
                                        // from before that toggle changed, or before it's set at
                                        // all, and that shouldn't hide an ID that already exists.
                                        TextEntry::make('payroll_provider_id_display')
                                            ->label('Payroll Provider ID')
                                            ->getStateUsing(fn (?Candidate $record): ?string => $record?->providerExternalId(Integration::Evertime))
                                            ->placeholder('Not yet synced'),
                                    ]),

                                Section::make('Payroll Submission Failed')
                                    ->columnSpanFull()
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->iconColor('danger')
                                    ->visible(fn (?Candidate $record): bool => $record && static::currentProviderErrors($record)->isNotEmpty())
                                    ->schema([
                                        Textarea::make('payroll_provider_errors')
                                            ->hiddenLabel()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Textarea $component, ?Candidate $record): void {
                                                if ($record) {
                                                    $component->state(static::currentProviderErrors($record)->implode("\n"));
                                                }
                                            }),
                                    ]),
                            ]),

                        Tab::make('Availability & Skills')
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Important Notes about this candidate')
                                    ->rows(4)
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
                                    ->default([])
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
                                    ->default([])
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
                                    ->default([])
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

                                Html::make(fn (?Candidate $record): HtmlString => new HtmlString(
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
                                        ->visible(fn (?Candidate $record): bool => $record?->documents->firstWhere('document_type', DocumentType::Cv) !== null)
                                        ->action(function (?Candidate $record): void {
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

                        Tab::make('Availability')
                            ->schema([
                                LivewireComponent::make(CandidateAvailabilityCalendar::class)
                                    ->key('candidate-availability-calendar')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Compliance')
                            ->schema(fn (?Candidate $record): array => $record ? CandidateComplianceForm::stepsFor($record) : []),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function document(?Candidate $record, DocumentType $documentType): ?CandidateDocument
    {
        return $record?->documents()->where('document_type', $documentType)->first();
    }

    protected static function documentUrl(?Candidate $record, DocumentType $documentType): ?string
    {
        $document = static::document($record, $documentType);

        return $document
            ? Document::viewUrl($document->path)
            : null;
    }
}
