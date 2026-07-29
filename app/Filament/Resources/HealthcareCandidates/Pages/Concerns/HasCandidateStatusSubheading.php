<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages\Concerns;

use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Enums\DocumentType;
use App\Enums\Healthcare\CareSetting;
use App\Models\HealthcareCandidate;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasCandidateStatusSubheading
{
    public function sendApplicationEmail(): void
    {
        HealthcareCandidateCreated::run($this->record, true);

        $this->record->unsetRelation('application');

        Notification::make()
            ->success()
            ->title('Application email sent')
            ->send();
    }

    public function viewApplicationAction(): Action
    {
        return Action::make('viewApplication')
            ->label('View Application')
            ->modalHeading('Submitted Application')
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->visible(fn (): bool => filled($this->record->application?->completed_at))
            ->fillForm(function (HealthcareCandidate $record): array {
                $record->loadMissing(['application', 'employmentHistories', 'references', 'skills', 'qualification']);

                return [
                    ...$record->attributesToArray(),
                    'skill_names' => $record->skills->pluck('name')->implode(', ') ?: 'None selected',
                    'qualification_name' => $record->qualification?->name,
                    'employmentHistories' => $record->employmentHistories->map->attributesToArray()->all(),
                    'references' => $record->references->map->attributesToArray()->all(),
                ];
            })
            ->schema([
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
                        TextEntry::make('qualification_name')->label('Qualification')->placeholder('Not set'),
                        TextEntry::make('skill_names')->label('Skills')->columnSpanFull(),
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
                        TextEntry::make('has_dbs')->label('Has DBS')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
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
                    ->visible(fn (): bool => $this->record->employmentHistories->isNotEmpty()),

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
                    ->visible(fn (): bool => $this->record->references->isNotEmpty()),

                Section::make('Uploaded Documents')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cv')
                            ->label('CV')
                            ->state(fn (): string => $this->hasDocument(DocumentType::Cv) ? 'Uploaded' : 'Not uploaded')
                            ->badge()
                            ->color(fn (): string => $this->hasDocument(DocumentType::Cv) ? 'success' : 'gray'),
                    ]),
            ]);
    }

    private static function formatYesNo(?string $value): string
    {
        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            default => 'Not set',
        };
    }

    private function hasDocument(DocumentType $documentType): bool
    {
        return $this->record->documents()->where('document_type', $documentType)->exists();
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

    public function getSubheading(): string|Htmlable|null
    {
        $this->record->loadMissing(['statuses.status', 'application']);

        if ($this->record->statuses->isEmpty()) {
            $statusHtml = Blade::render('<x-filament::badge color="gray">No Status</x-filament::badge>');
        } else {
            $statusHtml = $this->record->statuses
                ->map(fn ($s) => Blade::render(
                    '<x-filament::badge color="{{ $color }}">{{ $name }}</x-filament::badge>',
                    [
                        'color' => $s->status->color ?? 'gray',
                        'name' => $s->status->name,
                    ]
                ))
                ->implode(' ');
        }

        $application = $this->record->application;

        if ($application?->completed_at) {
            $applicationHtml = Blade::render(
                '<button type="button" wire:click="mountAction(\'viewApplication\')"><x-filament::badge color="success">Application Complete</x-filament::badge></button>'
            );
        } elseif ($application) {
            $url = route('application.healthcare.form', ['token' => $application->token]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="warning">Application Pending</x-filament::badge></a>',
                ['url' => $url]
            );
        } else {
            $applicationHtml = Blade::render(
                '<x-filament::button size="sm" color="gray" wire:click="sendApplicationEmail" wire:confirm="Send the application email to this candidate?">Send Application</x-filament::button>'
            );
        }

        return new HtmlString($applicationHtml ? $statusHtml.' '.$applicationHtml : $statusHtml);
    }
}
