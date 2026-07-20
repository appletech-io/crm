<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CandidatePanelProvider;
use App\Providers\Filament\ClientPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CandidatePanelProvider::class,
    ClientPanelProvider::class,
    FortifyServiceProvider::class,
];
