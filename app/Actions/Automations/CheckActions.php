<?php

namespace App\Actions\Automations;

use App\Enums\ActionAssigneeType;
use App\Enums\ActionEmailRecipient;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Booking;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\TodoItem;
use App\Models\User;
use App\Models\Vacancy;
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
                    $this->fireAction($action, $record);

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
     * Vacancies carry neither either, so their industry is inferred from the
     * client they belong to. References carry neither either, so their
     * industry is inferred from the candidate they're a reference for.
     */
    private function matchesIndustry(Action $action, Model $record): bool
    {
        if ($record instanceof Booking || $record instanceof CandidateReference) {
            return $action->industry?->candidateModel() === $record->candidate_type;
        }

        if ($record instanceof Vacancy) {
            return $action->industry_id === $record->client?->industry_id;
        }

        if ($record->industry_id) {
            return $action->industry_id === $record->industry_id;
        }

        return true;
    }

    /**
     * An action creates a to-do, sends an email, or both — whichever it's
     * configured to do. If it's configured to create a to-do but nobody is
     * assignable yet, and it has no email to send either, it never fires —
     * no trigger, no todos — so it's free to fire again once someone becomes
     * assignable later.
     */
    private function fireAction(Action $action, Model $record): void
    {
        $wantsTodo = filled($action->todo_name);
        $wantsEmail = (bool) $action->email_template_id;

        if (! $wantsTodo && ! $wantsEmail) {
            return;
        }

        $assigneeIds = $wantsTodo ? $this->resolveAssigneeIds($action, $record) : collect();

        if ($wantsTodo && $assigneeIds->isEmpty() && ! $wantsEmail) {
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

        if ($wantsEmail) {
            $this->sendActionEmail($action, $record);
        }
    }

    /**
     * Emails the candidate/client this record is about.
     */
    private function sendActionEmail(Action $action, Model $record): void
    {
        $recipient = $this->resolveEmailRecipient($action, $record);

        if (! $recipient) {
            return;
        }

        SendCustomTemplateEmail::dispatch($action->emailTemplate, $recipient);
    }

    private function resolveEmailRecipient(Action $action, Model $record): EducationCandidate|HealthcareCandidate|Client|null
    {
        if ($record instanceof Booking) {
            return $action->email_recipient === ActionEmailRecipient::Client
                ? $record->client
                : $record->candidate;
        }

        if ($record instanceof Vacancy) {
            return $record->client;
        }

        // A reference itself isn't emailable (no company/consultant of its
        // own) — the candidate it belongs to is who gets notified, e.g. to
        // chase them for an alternative referee.
        if ($record instanceof CandidateReference) {
            return $record->candidate;
        }

        return ($record instanceof Client || $record instanceof EducationCandidate || $record instanceof HealthcareCandidate)
            ? $record
            : null;
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

        // A reference has no consultant of its own — fall back to the
        // consultant of the candidate it belongs to.
        $consultantId = $record->consultant_id
            ?? ($record instanceof CandidateReference ? $record->candidate?->consultant_id : null);

        return $consultantId ? collect([$consultantId]) : collect();
    }
}
