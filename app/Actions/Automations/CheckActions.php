<?php

namespace App\Actions\Automations;

use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Booking;
use App\Models\TodoItem;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckActions
{
    use AsAction;

    public function handle(Model $record): void
    {
        if (! $record->consultant_id) {
            return;
        }

        Action::query()
            ->where('model_type', $record->getMorphClass())
            ->where('company_id', $record->company_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Action $action): bool => $this->matchesIndustry($action, $record))
            ->each(function (Action $action) use ($record): void {
                $openTrigger = $action->openTriggerFor($record);
                $isSatisfied = $action->isSatisfiedBy($record);

                if ($isSatisfied && ! $openTrigger) {
                    $this->createTodo($action, $record);

                    return;
                }

                if ((! $isSatisfied) && $openTrigger) {
                    $this->resolveTrigger($openTrigger);
                }
            });
    }

    /**
     * Resolving a trigger means whatever it flagged is no longer true, so the
     * todo it created is done too — without touching one a consultant already
     * completed themselves.
     */
    private function resolveTrigger(ActionTrigger $trigger): void
    {
        $trigger->update(['resolved_at' => now()]);

        $todoItem = $trigger->todoItem;

        if ($todoItem && ! $todoItem->isComplete()) {
            $todoItem->update(['completed_at' => now()]);
        }
    }

    /**
     * Client rows carry their own industry_id; candidate models are already
     * pinned to an industry by model_type alone. Bookings carry neither, so
     * their industry is inferred from the candidate model they're booked for.
     */
    private function matchesIndustry(Action $action, Model $record): bool
    {
        if ($record instanceof Booking) {
            return $action->industry?->candidateModel() === $record->candidate_type;
        }

        if ($record->industry_id) {
            return $action->industry_id === $record->industry_id;
        }

        return true;
    }

    private function createTodo(Action $action, Model $record): void
    {
        $todoItem = TodoItem::create([
            'user_id' => $record->consultant_id,
            'name' => $action->todo_name,
            'description' => $action->todo_description,
            'priority' => $action->todo_priority,
            'model_type' => $record->getMorphClass(),
            'model_id' => $record->getKey(),
        ]);

        ActionTrigger::create([
            'action_id' => $action->id,
            'model_type' => $record->getMorphClass(),
            'model_id' => $record->getKey(),
            'todo_item_id' => $todoItem->id,
        ]);
    }
}
