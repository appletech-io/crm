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
use Filament\Support\Enums\Width;

class EditVacancy extends EditRecord
{
    protected static string $resource = VacancyResource::class;

    /**
     * Full width, same as the standalone Job Pipeline page — the
     * Applicants board (now this page's default tab) is a kanban that
     * wants the room, not the narrower width a plain settings form is
     * happy with.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runMatch')
                ->label('Run Match')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This re-scores every candidate in your pool against this vacancy in the background. Existing matches are cleared and replaced with the new results.')
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
