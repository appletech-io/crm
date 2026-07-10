<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Actions\Candidates\CandidateCreated;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\EducationCandidate;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListEducationCandidates extends ListRecords
{
    protected static string $resource = EducationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Candidate')
                ->modalHeading('Add Candidate')
                ->createAnother(false)
                ->modalWidth('sm')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(EducationCandidate::class, 'email'),
                ])
                ->after(function (EducationCandidate $record) {
                    CandidateCreated::run($record);

                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
