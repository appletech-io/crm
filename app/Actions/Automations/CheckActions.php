<?php

namespace App\Actions\Automations;

use App\Enums\ActionAssigneeType;
use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Booking;
use App\Models\TodoItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckActions
{
    use AsAction;

    public function handle(Model $record): void
    {
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
                    $this->createTodos($action, $record);

                    return;
                }

                if ((! $isSatisfied) && $openTrigger) {
                    $this->resolveTrigger($openTrigger);
                }
            });
    }

    /**
     * Resolving a trigger means whatever it flagged is no longer true, so the
     * todos it created are done too — without touching one someone already
     * completed themselves.
     */
    private function resolveTrigger(ActionTrigger $trigger): void
    {
        $trigger->update(['resolved_at' => now()]);

        $trigger->todoItems
            ->reject(fn (TodoItem $todoItem): bool => $todoItem->isComplete())
            ->each(fn (TodoItem $todoItem) => $todoItem->update(['completed_at' => now()]));
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

    /**
     * Nothing to notify means the action never fires — no trigger, no todos —
     * so it's free to fire again once someone becomes assignable later.
     */
    private function createTodos(Action $action, Model $record): void
    {
        $assigneeIds = $this->resolveAssigneeIds($action, $record);

        if ($assigneeIds->isEmpty()) {
            return;
        }

        $trigger = ActionTrigger::create([
            'action_id' => $action->id,
            'model_type' => $record->getMorphClass(),
            'model_id' => $record->getKey(),
        ]);

        $assigneeIds->each(fn (int $userId) => TodoItem::create([
            'user_id' => $userId,
            'action_trigger_id' => $trigger->id,
            'name' => $action->todo_name,
            'description' => $action->todo_description,
            'priority' => $action->todo_priority,
            'model_type' => $record->getMorphClass(),
            'model_id' => $record->getKey(),
        ]));
    }

    /**
     * Consultant-targeted actions notify only the record's own consultant, if
     * it has one. Role-based actions notify every user with the configured
     * role, scoped to the action's own company and industry.
     *
     * @return Collection<int, int>
     */
    private function resolveAssigneeIds(Action $action, Model $record): Collection
    {
        if ($action->assignee_type === ActionAssigneeType::Role) {
            return User::role($action->assignee_role)
                ->where('company_id', $action->company_id)
                ->whereHas('industries', fn ($query) => $query->where('industries.id', $action->industry_id))
                ->pluck('id');
        }

        return $record->consultant_id ? collect([$record->consultant_id]) : collect();
    }
}
