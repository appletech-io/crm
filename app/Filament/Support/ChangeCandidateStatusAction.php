<?php

namespace App\Filament\Support;

use App\Enums\ActivityType;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Lets a consultant manually change a candidate's status from the edit page,
 * replacing whatever status is currently assigned (a candidate only ever
 * holds one status at a time) and logging the change as an activity.
 */
class ChangeCandidateStatusAction
{
    public static function header(): Action
    {
        return Action::make('changeStatus')
            ->label('Change Status')
            ->icon(Heroicon::OutlinedFlag)
            ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false) || (auth()->user()?->hasRole('compliance') ?? false))
            ->modalSubmitActionLabel('Change Status')
            ->schema([
                Select::make('candidate_status_id')
                    ->label('Status')
                    ->options(fn (): array => CandidateStatus::query()
                        ->where('industry_id', active_industry_id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->default(fn (EducationCandidate|HealthcareCandidate $record): ?int => $record->statuses()->first()?->candidate_status_id)
                    ->native(false)
                    ->required(),
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->rows(3),
            ])
            ->action(function (array $data, EducationCandidate|HealthcareCandidate $record): void {
                $fromStatus = $record->statuses()->first()?->status;
                $toStatus = CandidateStatus::find($data['candidate_status_id']);

                $record->statuses()->delete();
                $record->statuses()->create(['candidate_status_id' => $data['candidate_status_id']]);

                $note = $fromStatus
                    ? "Status changed from \"{$fromStatus->name}\" to \"{$toStatus?->name}\""
                    : "Status set to \"{$toStatus?->name}\"";

                if (filled($data['note'] ?? null)) {
                    $note .= ": {$data['note']}";
                }

                $record->activities()->create([
                    'user_id' => auth()->id(),
                    'type' => ActivityType::StatusChange->value,
                    'note' => $note,
                    'body' => json_encode([
                        'from' => $fromStatus?->name,
                        'to' => $toStatus?->name,
                    ]),
                    'contacted' => false,
                ]);

                $record->unsetRelation('statuses');

                Notification::make()
                    ->success()
                    ->title('Status updated')
                    ->send();
            });
    }
}
