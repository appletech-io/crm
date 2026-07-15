<?php

namespace App\Filament\Resources\EducationClients\Schemas;

use App\Enums\Education\KeyStage;
use App\Filament\Widgets\ClientActivityTimeline;
use App\Models\EducationClient;
use App\Models\JobTitle;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
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
use Illuminate\Support\Facades\Http;

class EducationClientForm
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
                                LivewireComponent::make(ClientActivityTimeline::class)
                                    ->key('client-activity-timeline')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Details')
                            ->schema([
                                Section::make('Client Name & Address')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Client Name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('client_type')
                                            ->maxLength(255),
                                        Select::make('consultant_id')
                                            ->label('Consultant')
                                            ->options(fn (): array => User::role('consultant')
                                                ->where('company_id', Auth::user()->company_id)
                                                ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->searchable(),

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
                                    ]),

                                Section::make('Contact Details')
                                    ->columns(2)
                                    ->schema([
                                        Text::make(function (?EducationClient $record): string {
                                            $mainContact = $record?->mainContact;

                                            if (! $mainContact) {
                                                return 'Main Contact: Not set';
                                            }

                                            $name = trim("{$mainContact->first_name} {$mainContact->last_name}");

                                            $name = $mainContact->jobTitle
                                                ? "{$name} ({$mainContact->jobTitle->name})"
                                                : $name;

                                            return "Main Contact: {$name}";
                                        })
                                            ->color(fn (?EducationClient $record): string => $record?->mainContact ? 'success' : 'gray')
                                            ->weight('bold')
                                            ->columnSpanFull(),

                                        TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(255),
                                        TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                    ]),

                                Section::make('Notes')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes for Booking Information'),
                                    ]),

                                Section::make('Keystages')
                                    ->schema([
                                        CheckboxList::make('key_stages')
                                            ->label('')
                                            ->options(
                                                collect(KeyStage::cases())
                                                    ->mapWithKeys(fn (KeyStage $case) => [$case->value => $case->label()])
                                                    ->toArray()
                                            )
                                            ->columns(3),
                                    ]),
                            ]),

                        Tab::make('Contacts')
                            ->schema([
                                Repeater::make('contacts')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->schema([
                                        TextInput::make('title')
                                            ->maxLength(255),
                                        Select::make('job_title_id')
                                            ->label('Job Title')
                                            ->options(fn (): array => JobTitle::query()
                                                ->where('company_id', Auth::user()->company_id)
                                                ->where('industry_id', active_industry_id())
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->searchable(),
                                        TextInput::make('first_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Toggle::make('main_contact')
                                            ->label('Main Contact')
                                            ->live(),
                                        Toggle::make('timesheet_contact')
                                            ->label('Timesheet Contact')
                                            ->live(),
                                        Toggle::make('invoice_contact')
                                            ->label('Invoice Contact')
                                            ->live(),
                                        Toggle::make('booking_contact')
                                            ->label('Booking Contact')
                                            ->live(),
                                        Text::make(function (Get $get): string {
                                            $roles = collect([
                                                'main_contact' => 'Main Contact',
                                                'timesheet_contact' => 'Timesheet Contact',
                                                'invoice_contact' => 'Invoice Contact',
                                                'booking_contact' => 'Booking Contact',
                                            ])->filter(fn (string $label, string $key): bool => (bool) $get($key))
                                                ->values();

                                            return $roles->isNotEmpty() ? $roles->implode(', ') : 'No roles assigned';
                                        })
                                            ->color(fn (Get $get): string => $get('main_contact') ? 'success' : 'gray')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(function (array $state): ?string {
                                        $name = trim(($state['first_name'] ?? '').' '.($state['last_name'] ?? '')) ?: 'Contact';

                                        $roles = collect([
                                            'main_contact' => 'Main',
                                            'timesheet_contact' => 'Timesheet',
                                            'invoice_contact' => 'Invoice',
                                            'booking_contact' => 'Booking',
                                        ])->filter(fn (string $label, string $key): bool => (bool) ($state[$key] ?? false))
                                            ->values();

                                        return $roles->isNotEmpty() ? "{$name} — {$roles->implode(', ')}" : $name;
                                    })
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Charge Rates')
                            ->schema([
                                Repeater::make('chargeRates')
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
                                            ->label('Day Charge Rate')
                                            ->numeric()
                                            ->prefix('£')
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->rule('regex:/^\d+(\.\d{1,2})?$/')
                                            ->validationMessages(['regex' => 'Please enter a valid monetary amount.']),
                                        TextInput::make('half_day_rate')
                                            ->label('Half Day Charge Rate')
                                            ->numeric()
                                            ->prefix('£')
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->rule('regex:/^\d+(\.\d{1,2})?$/')
                                            ->validationMessages(['regex' => 'Please enter a valid monetary amount.']),
                                        TextInput::make('hourly_rate')
                                            ->label('Hourly Charge Rate')
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
                                        : 'Charge Rate'
                                    )
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
