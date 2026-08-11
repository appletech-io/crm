<?php

namespace App\Filament\Resources\Vacancies\Pages;

use App\Filament\Resources\Vacancies\VacancyResource;
use App\Jobs\MatchCandidatesToVacancy;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVacancy extends EditRecord
{
    protected static string $resource = VacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runMatch')
                ->label('Run Match')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This re-scores every candidate in your pool against this vacancy in the background. Existing matches are refreshed, not duplicated.')
                ->action(function (): void {
                    MatchCandidatesToVacancy::dispatch($this->record->id)->afterCommit();

                    Notification::make()
                        ->title('Matching started')
                        ->body('We\'re matching this vacancy against your candidate pool in the background — check the Matches tab shortly.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
