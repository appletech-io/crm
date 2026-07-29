<?php

namespace App\Filament\Resources\EducationCandidates\Pages\Concerns;

use App\Actions\Candidates\CandidateCreated;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Models\EducationCandidate;
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
        CandidateCreated::run($this->record, true);

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
            ->fillForm(function (EducationCandidate $record): array {
                $record->loadMissing(['application', 'employmentHistories', 'references', 'skills', 'qualification']);

                return [
                    ...$record->attributesToArray(),
                    'application' => $record->application?->attributesToArray() ?? [],
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
                        TextEntry::make('title'),
                        TextEntry::make('first_name')->label('First Name'),
                        TextEntry::make('middle_name')->label('Middle Name')->placeholder('—'),
                        TextEntry::make('last_name')->label('Last Name'),
                        TextEntry::make('previous_surname')->label('Previous Surname')->placeholder('—'),
                        TextEntry::make('gender'),
                        TextEntry::make('nationality'),
                        TextEntry::make('date_of_birth')->label('Date of Birth')->date('d/m/Y'),
                        TextEntry::make('phone'),
                        TextEntry::make('mobile'),
                        TextEntry::make('address')->columnSpanFull(),
                        TextEntry::make('city')->label('City'),
                        TextEntry::make('county'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                    ]),

                Section::make('Medical Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('has_health_condition_or_disability')
                            ->label('Health Condition or Disability')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('health_condition_details')->label('Details')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('reasonable_accommodations')->label('Reasonable Accommodations Needed')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('emergency_contact_name')->label('Emergency Contact Name'),
                        TextEntry::make('emergency_contact_number')->label('Emergency Contact Number'),
                    ]),

                Section::make('Employment & Conduct')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('retired_early')->label('Retired Early')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('retired_early_medical_grounds')->label('On Medical Grounds')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('dismissed_from_relevant_position')->label('Dismissed from a Relevant Position')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('dismissal_details')->label('Dismissal Details')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('subject_to_disciplinary_action')->label('Subject to Disciplinary Action')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('disciplinary_action_details')->label('Disciplinary Action Details')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Consent & Declarations')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('application.terms_of_engagement_accepted_at')->label('Terms of Engagement Accepted')->dateTime('d/m/Y H:i')->placeholder('Not accepted'),
                        TextEntry::make('application.declaration_accepted_at')->label('Declaration Accepted')->dateTime('d/m/Y H:i')->placeholder('Not accepted'),
                        TextEntry::make('application.security_clearance_agreed')->label('Security Clearance Agreed')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('lived_overseas_six_months')->label('Lived Overseas 6+ Months')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('overseas_details')->label('Overseas Details')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('unspent_convictions')->label('Unspent Convictions')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('unspent_convictions_details')->label('Conviction Details')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('spent_convictions_not_protected')->label('Spent Convictions Not Protected')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.working_time_regulations_opt_out')->label('Working Time Regulations Opt-Out')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.childcare_act_guidance_read')->label('Childcare Act Guidance Read')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.childcare_act_no_disqualification_reasons')->label('No Disqualification Reasons')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.childcare_act_will_notify_changes')->label('Will Notify of Future Changes')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                    ]),

                Section::make('Skills & Work Preferences')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('qualification_name')->label('Qualification')->placeholder('Not set'),
                        TextEntry::make('skill_names')->label('Skills')->columnSpanFull(),
                        TextEntry::make('key_stages')->label('Key Stages')
                            ->formatStateUsing(fn (mixed $state): string => collect(static::toArray($state))
                                ->map(fn (string $value) => KeyStage::tryFrom($value)?->label() ?? $value)
                                ->implode(', ') ?: 'None selected'),
                        TextEntry::make('availability')
                            ->formatStateUsing(fn (mixed $state): string => collect(static::toArray($state))
                                ->map(fn (string $value) => Availability::tryFrom($value)?->label() ?? $value)
                                ->implode(', ') ?: 'None selected'),
                        TextEntry::make('available_from')->label('Available From')->date('d/m/Y')->placeholder('Not set'),
                        TextEntry::make('ni_number')->label('NI Number'),
                        TextEntry::make('trn_number')->label('TRN Number')->placeholder('—'),
                    ]),

                Section::make('Right to Work & DBS')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('right_to_work_type')->label('Right to Work'),
                        TextEntry::make('visa_share_code')->label('Visa Share Code')->placeholder('—'),
                        TextEntry::make('has_dbs')->label('Has DBS')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('dbs_certificate_number')->label('DBS Certificate Number')->placeholder('—'),
                        TextEntry::make('has_naric')->label('Has NARIC')
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
                                TextEntry::make('type')->badge(),
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
                        TextEntry::make('photo')
                            ->label('Photo')
                            ->state(fn (): string => $this->hasDocument(DocumentType::Photo) ? 'Uploaded' : 'Not uploaded')
                            ->badge()
                            ->color(fn (): string => $this->hasDocument(DocumentType::Photo) ? 'success' : 'gray'),
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
            $url = route('application.form', ['token' => $application->token]);
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
