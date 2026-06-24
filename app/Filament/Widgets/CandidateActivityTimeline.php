<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
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
            ->modalHeading('Log Activity')
            ->modalWidth('md')
            ->form([
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
}
