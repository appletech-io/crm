<?php

namespace App\Actions\Applications;

use App\Models\EducationApplication;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplicationCompleted
{
    use AsAction;

    public function handle(EducationApplication $application): void
    {
        //
    }
}
