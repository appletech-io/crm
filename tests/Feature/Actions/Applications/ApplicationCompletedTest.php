<?php

use App\Actions\Applications\ApplicationCompleted;
use App\Models\EducationApplication;

test('it can be run with a completed application', function () {
    $application = EducationApplication::factory()->create(['status' => 'completed']);

    ApplicationCompleted::run($application);
})->throwsNoExceptions();
