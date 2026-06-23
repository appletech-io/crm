<?php

namespace App\Filament\Resources\EducationCandidates\Schemas;

use App\Models\EducationCandidate;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Models\Qualification;
use Filament\Forms\Components\{DatePicker, Select, Textarea, TextInput, CheckboxList, RichEditor};

class EducationCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
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
                                        DatePicker::make('date_of_birth'),
                                        TextInput::make('place_of_birth')
                                            ->maxLength(255),
                                        Select::make('consultant_id')
                                            ->label('Consultant')
                                            ->options(fn (EducationCandidate $record): array => User::role('consultant')
                                                ->where('company_id', $record->company_id)
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
