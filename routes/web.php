<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/crm')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/crm')->name('dashboard');

    Route::livewire('/select-sector', 'sector-selector')->name('sector.select');
});

Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonate.stop');

// Exposed to public routes for application verification
Route::livewire('/application/{token}', 'application.verify-application')->name('application.verify');
Route::livewire('/application/{token}/form', 'application.application-form')->name('application.form');

Route::livewire('/application/healthcare/{token}', 'application.healthcare-verify-application')->name('application.healthcare.verify');
Route::livewire('/application/healthcare/{token}/form', 'application.healthcare-application-form')->name('application.healthcare.form');

// Exposed to public routes for referees completing a candidate reference
Route::livewire('/reference/{token}', 'reference.verify-reference')->name('reference.verify');
Route::livewire('/reference/{token}/form', 'reference.reference-form')->name('reference.form');

Route::get('/booking-confirmation', [BookingConfirmationController::class, 'show'])->name('booking-confirmation.show');

// Exposed to public routes for candidates applying directly to a vacancy
Route::livewire('/vacancy/{vacancy:slug}', 'vacancy.apply-form')->name('vacancy.apply');

require __DIR__.'/settings.php';
