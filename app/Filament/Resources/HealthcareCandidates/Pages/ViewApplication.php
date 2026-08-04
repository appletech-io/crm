<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages;

use App\Enums\DocumentType;
use App\Enums\Healthcare\CareSetting;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\HealthcareCandidate;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewApplication extends ViewRecord
{
    protected static string $resource = HealthcareCandidateResource::class;

    public function getTitle(): string
    {
        return 'Submitted Application';
    }

    public function getBreadcrumb(): string
    {
        return 'Application';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Personal Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->placeholder('—'),
                        TextEntry::make('first_name')->label('First Name'),
                        TextEntry::make('last_name')->label('Last Name'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('mobile')->placeholder('—'),
                        TextEntry::make('address')->columnSpanFull(),
                        TextEntry::make('city')->label('City'),
                        TextEntry::make('postcode'),
                    ]),

                Section::make('Skills & Work Preferences')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('qualification.name')->label('Qualification')->placeholder('Not set'),
                        TextEntry::make('skill_names')
                            ->label('Skills')
                            ->state(fn (HealthcareCandidate $record): string => $record->skills->pluck('name')->implode(', ') ?: 'None selected')
                            ->columnSpanFull(),
                        TextEntry::make('care_settings')->label('Care Settings')
                            ->formatStateUsing(fn (mixed $state): string => collect(static::toArray($state))
                                ->map(fn (string $value) => CareSetting::tryFrom($value)?->label() ?? $value)
                                ->implode(', ') ?: 'None selected'),
                        TextEntry::make('availability')
                            ->formatStateUsing(fn (mixed $state): string => collect(static::toArray($state))->implode(', ') ?: 'None selected'),
                    ]),

                Section::make('Right to Work & DBS')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('right_to_work_type')->label('Right to Work'),
                        TextEntry::make('right_to_work_expiry_date')->label('Right to Work Expiry Date')
                            ->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('has_dbs')->label('Has DBS')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('dbs_expiry_date')->label('DBS Expiry Date')
                            ->date('d/m/Y')->placeholder('—'),
                    ]),

                Section::make('Employment History')
                    ->schema([
                        RepeatableEntry::make('employmentHistories')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('company_name')->label('Employer'),
                                TextEntry::make('job_title')->label('Job Title'),
                                TextEntry::make('worked_from')->label('From')->date('d/m/Y'),
                                TextEntry::make('worked_to')->label('To')->date('d/m/Y')->placeholder('Present'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (HealthcareCandidate $record): bool => $record->employmentHistories->isNotEmpty()),

                Section::make('References')
                    ->schema([
                        RepeatableEntry::make('references')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('first_name')->label('First Name'),
                                TextEntry::make('last_name')->label('Last Name'),
                                TextEntry::make('email'),
                                TextEntry::make('status')->badge(),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (HealthcareCandidate $record): bool => $record->references->isNotEmpty()),

                Section::make('Uploaded Documents')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cv')
                            ->label('CV')
                            ->state(fn (HealthcareCandidate $record): string => $this->hasDocument($record, DocumentType::Cv) ? 'Uploaded' : 'Not uploaded')
                            ->badge()
                            ->color(fn (HealthcareCandidate $record): string => $this->hasDocument($record, DocumentType::Cv) ? 'success' : 'gray'),
                    ]),
            ]);
    }

    private function hasDocument(HealthcareCandidate $record, DocumentType $documentType): bool
    {
        return $record->documents()->where('document_type', $documentType)->exists();
    }

    private static function formatYesNo(?string $value): string
    {
        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            default => 'Not set',
        };
    }

    /**
     * Normalizes a JSON-array-cast column's state for display. Handles legacy
     * rows where the value was stored as a bare JSON string rather than an
     * array, which Eloquent's array cast decodes back to a plain string.
     */
    private static function toArray(mixed $state): array
    {
        return match (true) {
            is_array($state) => $state,
            blank($state) => [],
            default => [$state],
        };
    }
}
