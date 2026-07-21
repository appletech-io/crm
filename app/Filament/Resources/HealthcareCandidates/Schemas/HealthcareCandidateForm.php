<?php

namespace App\Filament\Resources\HealthcareCandidates\Schemas;

use App\Enums\Education\Availability;
use App\Enums\Healthcare\CareSetting;
use App\Enums\Nationality;
use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Filament\Resources\HealthcareVetting\HealthcareVettingResource;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Models\HealthcareCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\User;
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
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
                                        Checkbox::make('consent_to_contact')
                                            ->label('Candidate consents to us contacting this referee')
                                            ->columnSpanFull(),
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
}
