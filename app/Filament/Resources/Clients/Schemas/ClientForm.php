<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Actions\Clients\CreateClientContactPortalAccount;
use App\Enums\Education\KeyStage;
use App\Enums\Integration;
use App\Filament\Concerns\HasPayrollProviderErrorAlert;
use App\Filament\Widgets\ClientActivityTimeline;
use App\Filament\Widgets\ClientPipelineOverview;
use App\Filament\Widgets\ClientTimesheetOverview;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactJobTitle;
use App\Models\ClientType;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

class ClientForm
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
                                LivewireComponent::make(ClientActivityTimeline::class)
                                    ->key('client-activity-timeline')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Bookings')
                            ->schema([
                                LivewireComponent::make(ClientTimesheetOverview::class)
                                    ->key('client-timesheet-overview')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Pipeline')
                            ->schema([
                                LivewireComponent::make(ClientPipelineOverview::class)
                                    ->key('client-pipeline-overview')
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
                                        Select::make('client_type_id')
                                            ->label('Client Type')
                                            ->options(fn (): array => ClientType::query()
                                                ->where('company_id', Auth::user()->company_id)
                                                ->where('industry_id', active_industry_id())
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->searchable()
                                            ->preload(),
                                        Select::make('consultant_id')
                                            ->label('Consultant')
                                            ->options(fn (): array => User::role('consultant')
                                                ->whereHas('industries', fn ($query) => $query->where('industries.id', active_industry_id()))
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            // A client can be owned by whoever created it (e.g. an
                                            // admin), not only users with the consultant role — this
                                            // keeps that value valid/displayed even when it falls
                                            // outside the consultant-only options list above.
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => User::find($value)?->name)
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
                                        Text::make(function (?Client $record): string {
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
                                            ->color(fn (?Client $record): string => $record?->mainContact ? 'success' : 'gray')
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

                                Section::make('Payroll Provider')
                                    ->hidden()
                                    ->schema([
                                        TextInput::make('payroll_provider_id')
                                            ->label('Payroll Provider ID')
                                            ->helperText('This client\'s existing ID in the agency\'s payroll provider, if one already exists there.')
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (TextInput $component, ?Client $record): void {
                                                $provider = Auth::user()->company->payroll_provider;

                                                if ($record && $provider instanceof Integration) {
                                                    $component->state($record->providerExternalId($provider));
                                                }
                                            }),
                                    ]),

                                Section::make('Payroll')
                                    ->visible(fn (?Client $record): bool => $record !== null)
                                    ->schema([
                                        // Shown regardless of whether the company currently has
                                        // Evertime enabled as its active payroll_provider — a
                                        // client can already have a synced/manually-entered ID
                                        // from before that toggle changed, or before it's set at
                                        // all, and that shouldn't hide an ID that already exists.
                                        TextEntry::make('payroll_provider_id_display')
                                            ->label('Payroll Provider ID')
                                            ->getStateUsing(fn (?Client $record): ?string => $record?->providerExternalId(Integration::Evertime))
                                            ->placeholder('Not yet synced'),
                                    ]),

                                Section::make('Payroll Submission Failed')
                                    ->columnSpanFull()
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->iconColor('danger')
                                    ->visible(fn (?Client $record): bool => $record && static::currentProviderErrors($record)->isNotEmpty())
                                    ->schema([
                                        Textarea::make('payroll_provider_errors')
                                            ->hiddenLabel()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Textarea $component, ?Client $record): void {
                                                if ($record) {
                                                    $component->state(static::currentProviderErrors($record)->implode("\n"));
                                                }
                                            }),
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
                                        Select::make('client_contact_job_title_id')
                                            ->label('Job Title')
                                            ->options(fn (): array => ClientContactJobTitle::query()
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
                                            // This repeater collapses each contact by default, so a
                                            // malformed legacy email (e.g. from an old import) sitting
                                            // in a collapsed row can't be focused by the browser's own
                                            // type="email" constraint check — it just blocks saving
                                            // the whole client with an inscrutable error. Forcing the
                                            // input back to type="text" keeps ->email()'s validation
                                            // rule (so Livewire still catches and reports it, expanding
                                            // the offending row) without the native browser check.
                                            ->extraInputAttributes(['type' => 'text'])
                                            ->maxLength(255)
                                            // Only bites on a brand new contact — an already-saved one
                                            // can be edited freely even without an email, since the
                                            // toggle below no longer does anything for them anyway.
                                            ->required(fn (Get $get): bool => blank($get('id')) && (bool) $get('wants_portal_access'))
                                            ->columnSpanFull(),
                                        Toggle::make('wants_portal_access')
                                            ->label('Create User Account')
                                            ->helperText(fn (Get $get): string => filled($get('id'))
                                                ? 'Only takes effect when the contact is created — use "Create User Account" below for an existing contact.'
                                                : 'Sends this contact a login to the client portal with an auto-generated password.'
                                            )
                                            ->default(true)
                                            ->disabled(fn (Get $get): bool => filled($get('id')))
                                            ->live()
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
                                        Actions::make([
                                            Action::make('create_portal_account')
                                                ->label('Create User Account')
                                                ->icon('heroicon-o-key')
                                                ->color('gray')
                                                ->requiresConfirmation()
                                                ->modalDescription('This emails the contact a login to the client portal with an auto-generated password.')
                                                ->visible(fn (Get $get): bool => filled($get('id'))
                                                    && filled($get('email'))
                                                    && ! User::withoutGlobalScope('company')->where('client_contact_id', $get('id'))->exists()
                                                )
                                                ->action(function (Get $get): void {
                                                    $contact = ClientContact::find($get('id'));

                                                    if (! $contact) {
                                                        return;
                                                    }

                                                    $user = CreateClientContactPortalAccount::run($contact);

                                                    $notification = Notification::make()
                                                        ->title($user ? 'Portal account created' : 'Could not create a portal account — that email may already be in use');

                                                    $user ? $notification->success() : $notification->warning();

                                                    $notification->send();
                                                }),
                                        ])
                                            ->visible(fn (Get $get): bool => filled($get('id')))
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
