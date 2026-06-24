<?php

namespace App\Filament\Resources\EducationCandidates\Schemas;

use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Models\CandidateSkill;
use App\Models\Qualification;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
                                        TextInput::make('nationality')
                                            ->maxLength(255),
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
                                        TextInput::make('address_search')
                                            ->label('Search Address')
                                            ->placeholder('Start typing an address or postcode...')
                                            ->prefixIcon('heroicon-o-magnifying-glass')
                                            ->live(debounce: 500)
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
                                            ->hidden(fn (Get $get) => empty($get('address_suggestions')))
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Hidden::make('address_suggestions')
                                            ->dehydrated(false),

                                        Textarea::make('address')
                                            ->columnSpanFull()
                                            ->hidden(fn (Get $get) => empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('postcode')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('city')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('county')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => empty($get('address')) && empty($get('postcode'))),

                                        TextInput::make('country')
                                            ->maxLength(255)
                                            ->hidden(fn (Get $get) => empty($get('address')) && empty($get('postcode'))),
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
                    ]),
            ]);
    }
}
