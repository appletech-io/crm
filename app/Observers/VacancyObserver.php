<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\Vacancy;

class VacancyObserver
{
    public function saved(Vacancy $vacancy): void
    {
        CheckActions::run($vacancy);
    }

    public function updated(Vacancy $vacancy): void
    {
        CheckJobStatusAutomations::run($vacancy);
    }

    public function deleted(Vacancy $vacancy): void
    {
        CheckActions::run($vacancy);
        CheckJobStatusAutomations::run($vacancy);
    }
}
