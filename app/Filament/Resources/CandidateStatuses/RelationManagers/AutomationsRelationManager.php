<?php

namespace App\Filament\Resources\CandidateStatuses\RelationManagers;

use App\Models\CandidateStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AutomationsRelationManager extends RelationManager
{
    protected static string $relationship = 'automations';

    protected static ?string $title = 'Automation rules';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('to_candidate_status_id')
                ->label('Move candidate to')
                ->options(fn (): array => CandidateStatus::query()
                    ->where('company_id', Auth::user()->company_id)
                    ->where('industry_id', active_industry_id())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray()
                )
                ->placeholder('End of flow — no automatic move')
                ->searchable()
                ->columnSpanFull(),

            TagsInput::make('completed_fields')
                ->label('Required fields')
                ->suggestions(fn (): array => $this->getOwnerRecord()->industry->candidateFieldSuggestions())
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('toStatus.name')
                    ->label('Moves to')
                    ->placeholder('End of flow')
                    ->badge()
                    ->color('success'),

                TextColumn::make('completed_fields')
                    ->label('Required fields')
                    ->formatStateUsing(fn (mixed $state): string => implode(', ', is_array($state) ? $state : (json_decode($state, true) ?? [])))
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
