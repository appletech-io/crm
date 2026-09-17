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
     * Toggled by the header button below to swap between the full-screen
     * Applicants board (the default landing view — see VacancyForm) and the
     * Details/Matches/Activity tabs. Page-level UI state rather than form
     * data, so it lives here rather than as a field in the schema.
     */
    public bool $viewingDetails = false;

    /**
     * Full width, same as the standalone Job Pipeline page — the
     * Applicants board is a kanban that wants the room, not the narrower
     * width a plain settings form is happy with. Applies to both views.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleDetailsView')
                ->label(fn (): string => $this->viewingDetails ? 'Back to Board' : 'Details / Matches / Activity')
                ->icon(fn (): string => $this->viewingDetails ? 'heroicon-o-arrow-left' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->action(fn () => $this->viewingDetails = ! $this->viewingDetails),
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
