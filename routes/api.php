<?php

use App\Http\Controllers\VacancyFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/vacancies', VacancyFeedController::class)->name('api.vacancies.index');
