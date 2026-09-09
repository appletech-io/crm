<?php

namespace App\Filament\Resources\ReferenceForms\Schemas;

use App\Enums\ReferenceFieldType;
use App\Models\ReferenceForm;
use App\Services\References\ReferenceFormDraft;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReferenceFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    // Full width until the form exists, since the preview
                    // pane has nothing to point at on the create page.
                    ->columnSpan(fn (?ReferenceForm $record): int => $record === null ? 3 : 2)
                    ->schema(self::builderComponents()),

                self::previewPane(),
            ]);
    }

    /**
     * Every field the preview reflects is live, so the pane keeps up with
     * the form as it's built rather than only after a save. Text is live on
     * blur rather than per keystroke — a round trip per character would make
     * the builder itself feel sluggish.
     *
     * @return array<int, mixed>
     */
    private static function builderComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->maxLength(255),

            Textarea::make('description')
                ->label('Description')
                ->helperText('Shown to staff when choosing a reference form — not seen by the referee.')
                ->rows(2),

            Toggle::make('is_statement_only')
                ->label('Statement Only')
                ->helperText('No questions are sent to a referee — used for things like a candidate\'s own gap-in-employment statement.')
                ->live(),

            Toggle::make('needs_position_and_organisation')
                ->label('Ask Referee to Confirm Position & Organisation')
                ->default(true)
                ->live()
                ->visible(fn (Get $get): bool => ! $get('is_statement_only')),

            Repeater::make('fields')
                ->relationship()
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => ! $get('is_statement_only'))
                ->schema([
                    TextInput::make('label')
                        ->label('Question')
                        ->helperText('Use :company_name to insert the agency\'s name, e.g. "Please inform :company_name of any concerns."')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(255),

                    Select::make('field_type')
                        ->label('Answer Type')
                        ->options(ReferenceFieldType::options())
                        ->native(false)
                        ->required()
                        ->live(),

                    TagsInput::make('options')
                        ->label('Choices')
                        ->helperText('The choices a referee can pick from, e.g. Yes, No, N/A.')
                        ->live()
                        ->visible(fn (Get $get): bool => $get('field_type') === 'radio')
                        ->required(fn (Get $get): bool => $get('field_type') === 'radio')
                        ->columnSpanFull(),

                    Toggle::make('required')
                        ->default(true)
                        ->live(),

                    TextInput::make('section_heading')
                        ->label('Section Heading')
                        ->helperText('Consecutive questions sharing the same heading are grouped together. Leave blank for no heading.')
                        ->live(onBlur: true)
                        ->maxLength(255),

                    Select::make('show_when_field_key')
                        ->label('Only Show When...')
                        ->helperText('Leave blank to always show this question.')
                        ->native(false)
                        ->live()
                        ->options(function (Get $get): array {
                            $currentLabel = $get('label');

                            return collect($get('../../fields') ?? [])
                                ->filter(fn (array $sibling): bool => filled($sibling['label'] ?? null) && $sibling['label'] !== $currentLabel)
                                ->mapWithKeys(fn (array $sibling): array => [
                                    Str::slug($sibling['label'], '_') => $sibling['label'],
                                ])
                                ->all();
                        }),

                    TextInput::make('show_when_value')
                        ->label('...Is Answered')
                        ->helperText('The exact choice that reveals this question, e.g. "Yes".')
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => filled($get('show_when_field_key'))),
                ])
                ->columns(2)
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->collapsible()
                ->collapsed()
                ->reorderAction(fn (Action $action) => $action->tooltip('Drag to reorder'))
                ->orderColumn('sort_order')
                ->addActionLabel('Add Question')
                ->columnSpanFull()
                ->default([]),
        ];
    }

    /**
     * Rendering the pane is also what publishes the draft: this closure runs
     * on every render, which — because everything the preview reflects is
     * live — is exactly whenever there is something new to show. That keeps
     * the two in step without hanging an afterStateUpdated() on each field
     * and hoping none is ever missed.
     */
    private static function previewPane(): Html
    {
        return Html::make(function (Get $get, ?ReferenceForm $record): ?Htmlable {
            if ($record === null) {
                return null;
            }

            ReferenceFormDraft::store(Auth::user(), $record, [
                'name' => $get('name'),
                'is_statement_only' => (bool) $get('is_statement_only'),
                'needs_position_and_organisation' => (bool) $get('needs_position_and_organisation'),
                'fields' => array_values($get('fields') ?? []),
            ]);

            return view('filament.reference-form-preview-pane', ['referenceForm' => $record]);
        })
            ->visible(fn (?ReferenceForm $record): bool => $record !== null)
            ->columnSpan(1);
    }
}
