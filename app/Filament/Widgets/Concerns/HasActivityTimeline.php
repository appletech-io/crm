<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\ActivityType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

trait HasActivityTimeline
{
    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->record->activities())
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
                        Select::make('type')
                            ->options([
                                ActivityType::Call->value => ActivityType::Call->label(),
                                ActivityType::Note->value => ActivityType::Note->label(),
                                ActivityType::Meeting->value => ActivityType::Meeting->label(),
                                ActivityType::Other->value => ActivityType::Other->label(),
                            ])
                            ->required(),
                        TextInput::make('note')
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('body')
                            ->label('Additional details')
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        $this->record->activities()->create([
                            'user_id' => auth()->id(),
                            'type' => $data['type'],
                            'note' => $data['note'],
                            'body' => filled($data['body']) ? $data['body'] : null,
                        ]);
                    }),
            ]);
    }
}
