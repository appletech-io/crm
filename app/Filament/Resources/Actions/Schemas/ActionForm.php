<?php

namespace App\Filament\Resources\Actions\Schemas;

use App\Enums\ActionAssigneeType;
use App\Enums\ActionEmailRecipient;
use App\Enums\EmailTemplateAudience;
use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Filament\Support\ConditionsRepeaterField;
use App\Models\Action;
use App\Models\Booking;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\Vacancy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class ActionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Action')
                ->tabs([
                    Tab::make('Details')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Action Name')
                                ->helperText('An internal label so you can recognise this automation in the list below.')
                                ->required()
                                ->maxLength(255),

                            Select::make('model_type')
                                ->label('Applies To')
                                ->options(static::modelTypeOptions())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('conditions', []);
                                    $set('email_recipient', null);
                                    $set('email_template_id', null);
                                }),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Conditions')
                        ->schema([
                            ConditionsRepeaterField::make('conditions', fn (Get $get): array => static::suggestionsFor($get('/data.model_type')))
                                ->hiddenLabel(),
                        ]),

                    Tab::make('To-Do')
                        ->columns(2)
                        ->schema([
                            Toggle::make('wants_todo')
                                ->label('Create a To-Do')
                                ->helperText('Create a to-do item for someone to action when this fires.')
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(fn (Toggle $component, ?Action $record) => $component->state(filled($record?->todo_name)))
                                ->afterStateUpdated(function (Set $set, bool $state): void {
                                    if (! $state) {
                                        $set('assignee_type', ActionAssigneeType::Consultant->value);
                                        $set('assignee_role', null);
                                        $set('todo_name', null);
                                        $set('todo_description', null);
                                    }
                                })
                                ->columnSpanFull(),

                            Select::make('assignee_type')
                                ->label('Notify')
                                ->options(ActionAssigneeType::options())
                                ->default(ActionAssigneeType::Consultant->value)
                                ->visible(fn (Get $get): bool => $get('wants_todo'))
                                ->required(fn (Get $get): bool => $get('wants_todo'))
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('assignee_role', null)),

                            Select::make('assignee_role')
                                ->label('Role')
                                ->options(static::roleOptions())
                                ->visible(fn (Get $get): bool => $get('wants_todo') && $get('assignee_type') === ActionAssigneeType::Role->value)
                                ->required(fn (Get $get): bool => $get('wants_todo') && $get('assignee_type') === ActionAssigneeType::Role->value),

                            TextInput::make('todo_name')
                                ->label('To-Do Name')
                                ->visible(fn (Get $get): bool => $get('wants_todo'))
                                ->required(fn (Get $get): bool => $get('wants_todo'))
                                ->maxLength(TodoItemForm::NAME_MAX_LENGTH)
                                ->columnSpanFull(),

                            Textarea::make('todo_description')
                                ->label('To-Do Description')
                                ->visible(fn (Get $get): bool => $get('wants_todo'))
                                ->rows(3)
                                ->columnSpanFull(),

                            Select::make('todo_priority')
                                ->label('To-Do Priority')
                                ->options(TodoPriority::options())
                                ->default(TodoPriority::Medium->value)
                                ->visible(fn (Get $get): bool => $get('wants_todo'))
                                ->required(fn (Get $get): bool => $get('wants_todo')),
                        ]),

                    Tab::make('Email')
                        ->columns(2)
                        ->schema([
                            Toggle::make('wants_email')
                                ->label('Send an Email')
                                ->helperText('Send an email to the candidate or client this action is about.')
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(fn (Toggle $component, ?Action $record) => $component->state(filled($record?->email_template_id)))
                                ->afterStateUpdated(function (Set $set, bool $state): void {
                                    if (! $state) {
                                        $set('email_recipient', null);
                                        $set('email_template_id', null);
                                    }
                                })
                                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (! $value && ! $get('wants_todo')) {
                                        $fail('Enable at least a to-do or an email for this action to do something.');
                                    }
                                })
                                ->columnSpanFull(),

                            Select::make('email_recipient')
                                ->label('Send Email To')
                                ->options(ActionEmailRecipient::options())
                                ->visible(fn (Get $get): bool => $get('wants_email') && $get('model_type') === Booking::class)
                                ->required(fn (Get $get): bool => $get('wants_email') && $get('model_type') === Booking::class)
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('email_template_id', null)),

                            Select::make('email_template_id')
                                ->label('Email Template')
                                ->visible(fn (Get $get): bool => $get('wants_email'))
                                ->required(fn (Get $get): bool => $get('wants_email'))
                                ->options(fn (Get $get): array => static::emailTemplateOptions($get('model_type'), $get('email_recipient'))),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return array<string, string> */
    public static function modelTypeOptions(): array
    {
        $options = [
            Client::class => 'Client',
            Booking::class => 'Booking',
            Vacancy::class => 'Vacancy',
            CandidateReference::class => 'Candidate Reference',
        ];

        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($candidateModelClass) {
            $options[$candidateModelClass] = 'Candidate';
        }

        return $options;
    }

    /** @return array<string, array{label: string, type: string}> */
    public static function suggestionsFor(?string $modelType): array
    {
        if (! $modelType || ! method_exists($modelType, 'candidateFieldSuggestions')) {
            return [];
        }

        return $modelType::candidateFieldSuggestions();
    }

    /** @return array<string, string> */
    private static function emailTemplateOptions(?string $modelType, ?string $emailRecipient): array
    {
        $audience = static::audienceFor($modelType, $emailRecipient);

        if (! $audience) {
            return [];
        }

        return EmailTemplate::query()
            ->custom()
            ->forAudience($audience)
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->pluck('name', 'id')
            ->toArray();
    }

    private static function audienceFor(?string $modelType, ?string $emailRecipient): ?EmailTemplateAudience
    {
        if ($modelType === Client::class || $modelType === Vacancy::class) {
            return EmailTemplateAudience::Client;
        }

        if ($modelType === Booking::class) {
            return match ($emailRecipient) {
                ActionEmailRecipient::Client->value => EmailTemplateAudience::Client,
                ActionEmailRecipient::Candidate->value => EmailTemplateAudience::Candidate,
                default => null,
            };
        }

        if ($modelType && method_exists($modelType, 'candidateFieldSuggestions')) {
            return EmailTemplateAudience::Candidate;
        }

        return null;
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return Role::query()
            ->whereNotIn('name', ['candidate', 'client'])
            ->orderBy('name')
            ->pluck('name', 'name')
            ->map(fn (string $name): string => ucfirst(str_replace('_', ' ', $name)))
            ->all();
    }
}
