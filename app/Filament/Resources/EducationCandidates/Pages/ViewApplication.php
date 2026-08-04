<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\EducationCandidate;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ViewApplication extends ViewRecord
{
    protected static string $resource = EducationCandidateResource::class;

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

                Section::make('Terms of Engagement')
                    ->schema([
                        TextEntry::make('application.terms_of_engagement_accepted_at')
                            ->label('Accepted')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Not accepted'),
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.terms-of-engagement',
                            ['companyName' => $this->employmentBusinessName()],
                        )->render()))
                            ->extraAttributes(['class' => 'max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700']),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Keeping Children Safe in Education (KCSIE)')
                    ->schema([
                        TextEntry::make('application.terms_accepted_at')
                            ->label('Confirmed')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Not confirmed'),
                        Html::make(fn (): HtmlString => new HtmlString(Blade::render(
                            <<<'BLADE'
                                <div class="flex flex-col gap-2">
                                    <embed src="{{ $url }}" type="application/pdf" class="h-[70vh] w-full rounded-lg border border-gray-200 dark:border-gray-700" />
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-primary-600 underline dark:text-primary-400">{{ __('Open KCSIE document in a new tab') }}</a>
                                </div>
                                BLADE,
                            ['url' => asset('documents/kcsie.pdf')],
                        ))),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Declaration')
                    ->schema([
                        TextEntry::make('application.declaration_accepted_at')
                            ->label('Accepted')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Not accepted'),
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.declaration',
                        )->render()))
                            ->extraAttributes(['class' => 'max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700']),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Security Clearance & Overseas Residency')
                    ->columns(2)
                    ->schema([
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.security-clearance',
                            ['companyName' => $this->employmentBusinessName()],
                        )->render()))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700']),
                        TextEntry::make('application.security_clearance_agreed')->label('Security Clearance Agreed')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('lived_overseas_six_months')->label('Lived Overseas 6+ Months')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('overseas_details')->label('Overseas Details')->placeholder('—')->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Rehabilitation of Offenders')
                    ->columns(2)
                    ->schema([
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.rehabilitation-of-offenders',
                        )->render()))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700']),
                        TextEntry::make('unspent_convictions')->label('Unspent Convictions')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('unspent_convictions_details')->label('Conviction Details')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('spent_convictions_not_protected')->label('Spent Convictions Not Protected')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Working Time Regulations')
                    ->schema([
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.working-time-regulations',
                        )->render())),
                        TextEntry::make('application.working_time_regulations_opt_out')->label('Opted Out')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Childcare Act 2006 Disqualification')
                    ->columns(2)
                    ->schema([
                        Html::make(fn (): HtmlString => new HtmlString(view(
                            'components.application.application-form-steps.consent.childcare-act-disqualification',
                        )->render()))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700']),
                        TextEntry::make('application.childcare_act_guidance_read')->label('Guidance Read')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.childcare_act_no_disqualification_reasons')->label('No Disqualification Reasons')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('application.childcare_act_will_notify_changes')->label('Will Notify of Future Changes')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Skills & Work Preferences')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('qualification.name')->label('Qualification')->placeholder('Not set'),
                        TextEntry::make('skill_names')
                            ->label('Skills')
                            ->state(fn (EducationCandidate $record): string => $record->skills->pluck('name')->implode(', ') ?: 'None selected')
                            ->columnSpanFull(),
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
                        TextEntry::make('right_to_work_expiry_date')->label('Right to Work Expiry Date')
                            ->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('has_dbs')->label('Has DBS')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('dbs_certificate_number')->label('DBS Certificate Number')->placeholder('—'),
                        TextEntry::make('dbs_expiry_date')->label('DBS Expiry Date')
                            ->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('has_naric')->label('Has NARIC')
                            ->formatStateUsing(fn (?string $state): string => static::formatYesNo($state)),
                        TextEntry::make('safeguarding_expiry_date')->label('Safeguarding Certificate Expiry Date')
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
                    ->visible(fn (EducationCandidate $record): bool => $record->employmentHistories->isNotEmpty()),

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
                    ->visible(fn (EducationCandidate $record): bool => $record->references->isNotEmpty()),

                Section::make('Uploaded Documents')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cv')
                            ->label('CV')
                            ->state(fn (EducationCandidate $record): string => $this->hasDocument($record, DocumentType::Cv) ? 'Uploaded' : 'Not uploaded')
                            ->badge()
                            ->color(fn (EducationCandidate $record): string => $this->hasDocument($record, DocumentType::Cv) ? 'success' : 'gray'),
                        TextEntry::make('photo')
                            ->label('Photo')
                            ->state(fn (EducationCandidate $record): string => $this->hasDocument($record, DocumentType::Photo) ? 'Uploaded' : 'Not uploaded')
                            ->badge()
                            ->color(fn (EducationCandidate $record): string => $this->hasDocument($record, DocumentType::Photo) ? 'success' : 'gray'),
                    ]),
            ]);
    }

    private function employmentBusinessName(): string
    {
        $company = $this->getRecord()->company;

        $tradingName = $company?->trading_name ?: config('app.name');

        if (! $company?->legal_name) {
            return $tradingName;
        }

        $name = "{$tradingName} (t/a {$company->legal_name})";

        return $company->company_number
            ? "{$name} (Company No: {$company->company_number})"
            : $name;
    }

    private function hasDocument(EducationCandidate $record, DocumentType $documentType): bool
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
