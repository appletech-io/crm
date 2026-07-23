<?php

namespace App\Console\Commands;

use App\Actions\Automations\CheckActions;
use App\Models\Action;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('actions:check-time-based')]
#[Description('Re-evaluate actions whose conditions depend on elapsed time, since nothing else triggers a re-check for them')]
class CheckTimeBasedActions extends Command
{
    public function handle(): int
    {
        $modelTypes = Action::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Action $action): bool => collect($action->conditions)
                ->contains(fn (array $condition): bool => ($condition['operator'] ?? null) === 'days_since_at_least')
            )
            ->pluck('model_type')
            ->unique();

        foreach ($modelTypes as $modelType) {
            $modelType::query()
                ->whereNotNull('consultant_id')
                ->chunkById(100, function ($records): void {
                    $records->each(fn (Model $record) => CheckActions::run($record));
                });
        }

        return self::SUCCESS;
    }
}
