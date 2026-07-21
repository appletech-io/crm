<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages;

use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\HealthcareCandidate;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListHealthcareCandidates extends ListRecords
{
    protected static string $resource = HealthcareCandidateResource::class;

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
                        ->unique(HealthcareCandidate::class, 'email'),
                ])
                ->after(function (HealthcareCandidate $record) {
                    HealthcareCandidateCreated::run($record);

                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
