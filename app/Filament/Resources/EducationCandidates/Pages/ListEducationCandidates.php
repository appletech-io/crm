<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Actions\Candidates\CandidateCreated;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateSearchTabs;
use App\Models\EducationCandidate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListEducationCandidates extends ListRecords
{
    use HasCandidateSearchTabs;

    protected static string $resource = EducationCandidateResource::class;

    protected function candidateSearchActiveTab(): string
    {
        return 'index';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUploadCvs')
                ->label('Bulk Upload CVs')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('bulk-upload-cvs')),
            CreateAction::make()
                ->label('New Candidate')
                ->modalHeading('Add EducationCandidate')
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
                ->mutateFormDataUsing(function (array $data): array {
                    $data['consultant_id'] = auth()->id();

                    return $data;
                })
                ->after(function (EducationCandidate $record) {
                    CandidateCreated::run($record);

                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}
