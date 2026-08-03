<?php

namespace App\Observers;

use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\Vacancy;

class VacancyObserver
{
    public function updated(Vacancy $vacancy): void
    {
        CheckJobStatusAutomations::run($vacancy);
    }
}
