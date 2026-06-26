<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
use App\Models\CandidateActivity;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class CandidateActivityTimeline extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.candidate-activity-timeline';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function logActivityAction(): Action
    {
        return Action::make('logActivity')
            ->label('Log Activity')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->modalHeading('Log Activity')
            ->modalWidth('md')
            ->schema([
                Select::make('type')
                    ->options(options: [
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
            });
    }

    public function viewActivityAction(): Action
    {
        return Action::make('viewActivity')
            ->modalHeading('Activity Details')
            ->modalWidth('md')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(function (array $arguments): array {
                $activity = CandidateActivity::with('user')->find($arguments['activity']);

                if (! $activity) {
                    return [];
                }

                return [
                    'type' => $activity->type->label(),
                    'note' => $activity->note,
                    'body' => $activity->body,
                    'logged_by' => $activity->user?->name ?? 'System',
                    'logged_at' => $activity->created_at->format('d M Y, H:i'),
                ];
            })
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
            ]);
    }
}
