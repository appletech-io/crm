<?php

namespace App\Filament\Resources\HealthcareCandidates\RelationManagers;

use App\Enums\ActivityType;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('type')
                    ->options([
                        ActivityType::Call->value => ActivityType::Call->label(),
                        ActivityType::Note->value => ActivityType::Note->label(),
                        ActivityType::Other->value => ActivityType::Other->label(),
                    ])
                    ->required(),
                TextInput::make('note')
                    ->required()
                    ->maxLength(1000),
                Textarea::make('body')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ActivityType $state): string => $state->label())
                    ->color(fn (ActivityType $state): string => match ($state) {
                        ActivityType::Call => 'success',
                        ActivityType::Note => 'info',
                        ActivityType::Other => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('note')
                    ->wrap(),
                TextColumn::make('body')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Logged by'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
