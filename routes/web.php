<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/crm')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('/select-sector', 'sector-selector')->name('sector.select');
});

Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonate.stop');

// Exposed to public routes for application verification
Route::livewire('/application/{token}', 'application.verify-application')->name('application.verify');
Route::livewire('/application/{token}/form', 'application.application-form')->name('application.form');

Route::get('/booking-confirmation', [BookingConfirmationController::class, 'show'])->name('booking-confirmation.show');

require __DIR__.'/settings.php';
