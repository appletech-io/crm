<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\ActivityType;
use App\Filament\Resources\TodoItems\TodoItemResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

trait HasActivityTimeline
{
    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    /**
     * The subset of ActivityType a "Log Activity" modal offers, since not
     * every type makes sense on every record (e.g. a BDM Call belongs on a
     * client, not a candidate). Override per-widget to add to this default.
     *
     * @return array<int, ActivityType>
     */
    protected static function loggableTypes(): array
    {
        return [
            ActivityType::Call,
            ActivityType::Note,
            ActivityType::Meeting,
            ActivityType::Other,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->record->activities())
            ->recordAction('view')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ActivityType $state): string => $state->label())
                    ->color(fn (ActivityType $state): string => $state->color()),
                TextColumn::make('note')
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Logged by')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(ActivityType::cases())
                        ->mapWithKeys(fn (ActivityType $type): array => [$type->value => $type->label()])
                        ->toArray()
                    ),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Activity Details')
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->fillForm(fn (Model $record): array => [
                        'type' => $record->type->label(),
                        'note' => $record->note,
                        'body' => $record->body,
                        'logged_by' => $record->user?->name ?? 'System',
                        'logged_at' => $record->created_at->format('d M Y, H:i'),
                    ])
                    ->schema([
                        TextInput::make('type')
                            ->label('Type')
                            ->disabled(),
                        TextInput::make('logged_by')
                            ->label('Logged by')
                            ->disabled(),
                        TextInput::make('logged_at')
                            ->label('Date & time')
                            ->disabled(),
                        TextInput::make('note')
                            ->label('Summary')
                            ->disabled(),
                        Textarea::make('body')
                            ->label('Additional details')
                            ->rows(4)
                            ->disabled(),
                    ]),
            ])
            ->headerActions([
                Action::make('logActivity')
                    ->label('Log Activity')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->modalHeading('Log Activity')
                    ->modalWidth('md')
                    ->schema([
                        ...static::activitySchema(),
                        // Which footer button was clicked, set client-side
                        // just before the form submits (see the "Create &
                        // Todo" button below). Both buttons are genuine
                        // type="submit" elements triggering the SAME
                        // wire:submit.prevent="callMountedAction" — Filament
                        // can't reliably resolve a second action mounted via
                        // wire:click nested under this one (confirmed by
                        // inspecting the actual rendered attribute, which
                        // carries no context at all), so there's only ever
                        // one action here, branching on this flag instead.
                        Hidden::make('__intent')->default('save'),
                    ])
                    ->modalFooterActions([
                        Action::make('submit')
                            ->label('Log Activity')
                            ->submit('callMountedAction')
                            ->color('primary'),
                        Action::make('createAndTodo')
                            ->label('Log & Todo')
                            ->submit('callMountedAction')
                            ->color('gray')
                            ->alpineClickHandler("\$wire.set('mountedActions.0.data.__intent', 'todo')"),
                        Action::make('cancel')
                            ->label('Cancel')
                            ->close()
                            ->color('gray'),
                    ])
                    ->action(function (array $data): void {
                        $this->logActivity($data);

                        if (($data['__intent'] ?? 'save') === 'todo') {
                            $this->redirect(TodoItemResource::getUrl('create', [
                                'model_type' => $this->record::class,
                                'model_id' => $this->record->id,
                                'name' => $data['note'],
                            ]));
                        }
                    }),
            ]);
    }

    /** @return array<int, Component> */
    private static function activitySchema(): array
    {
        return [
            Select::make('type')
                ->options(collect(static::loggableTypes())
                    ->mapWithKeys(fn (ActivityType $type): array => [$type->value => $type->label()])
                    ->toArray()
                )
                ->required(),
            TextInput::make('note')
                ->required()
                ->maxLength(1000),
            Textarea::make('body')
                ->label('Additional details')
                ->rows(3)
                ->belowContent(static::dictationAction()),
        ];
    }

    private function logActivity(array $data): void
    {
        $this->record->activities()->create([
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'note' => $data['note'],
            'body' => filled($data['body']) ? $data['body'] : null,
        ]);
    }

    /**
     * A pure client-side mic button using the browser's Web Speech API to
     * dictate into the "Additional details" field, toggled on/off by
     * re-clicking it. Runs entirely in the browser via actionJs() — no
     * server round trip, so it works from any schema this trait is used on.
     */
    protected static function dictationAction(): Action
    {
        return Action::make('dictateBody')
            ->label('Dictate')
            ->icon('heroicon-o-microphone')
            ->color('gray')
            ->actionJs(<<<'JS'
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

                if (! SpeechRecognition) {
                    alert('Speech to text is not supported in this browser. Try Chrome or Edge.');
                    return;
                }

                const button = $event.currentTarget;

                // $set()/$get() resolve relative to whatever schema container this
                // button happens to be nested in, which isn't reliable from a
                // belowContent() slot — writing straight to the sibling textarea's
                // DOM node and dispatching a native input event is what
                // wire:model itself listens for, so it works regardless.
                const textarea = button.closest('[data-field-wrapper]')?.querySelector('textarea');

                if (! textarea) {
                    alert('Could not find the field to dictate into.');
                    return;
                }

                if (window.__activityDictationRecognition) {
                    window.__activityDictationRecognition.stop();
                    return;
                }

                const recognition = new SpeechRecognition();
                recognition.lang = 'en-GB';
                recognition.interimResults = true;
                recognition.continuous = true;

                // Base is whatever was already in the field before this
                // session started, plus everything confirmed final so far.
                // Interim (not-yet-final) words are re-rendered on top of
                // that each time, so partial words update live as spoken
                // instead of only appearing once a sentence is finalised.
                let base = textarea.value ? textarea.value.trim() + ' ' : '';

                recognition.onresult = (event) => {
                    let interim = '';

                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const chunk = event.results[i][0].transcript;

                        if (event.results[i].isFinal) {
                            base += chunk.trim() + ' ';
                        } else {
                            interim += chunk;
                        }
                    }

                    textarea.value = (base + interim).trim();
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                };

                recognition.onend = () => {
                    window.__activityDictationRecognition = null;
                    button.classList.remove('animate-pulse', 'fi-color-danger');
                };

                recognition.onerror = (event) => {
                    window.__activityDictationRecognition = null;
                    button.classList.remove('animate-pulse', 'fi-color-danger');

                    if (event.error === 'no-speech') {
                        alert('No speech was detected. Check your microphone is working and try again.');
                    } else if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                        alert('Microphone access was blocked. Check your browser is allowed to use the microphone on this site and try again.');
                    } else if (event.error !== 'aborted') {
                        alert('Speech recognition error: ' + event.error);
                    }
                };

                window.__activityDictationRecognition = recognition;
                button.classList.add('animate-pulse', 'fi-color-danger');

                try {
                    recognition.start();
                } catch (e) {
                    window.__activityDictationRecognition = null;
                    button.classList.remove('animate-pulse', 'fi-color-danger');
                    alert('Could not start speech recognition: ' + e.message);
                }
                JS
            )
            ->tooltip('Dictate using speech to text (click again to stop)');
    }
}
