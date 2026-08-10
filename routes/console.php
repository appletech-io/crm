<?php

use App\Console\Commands\CheckTimeBasedActions;
use App\Console\Commands\CheckTimeBasedJobStatusAutomations;
use App\Console\Commands\CheckTimeBasedStatusAutomations;
use App\Console\Commands\PruneAdhocEmailAttachments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CheckTimeBasedStatusAutomations::class)->daily();
Schedule::command(CheckTimeBasedActions::class)->daily();
Schedule::command(CheckTimeBasedJobStatusAutomations::class)->daily();
Schedule::command(PruneAdhocEmailAttachments::class)->daily();
