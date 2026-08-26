<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\CandidateDocumentController;
use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\EmailImageController;
use App\Http\Controllers\ImpersonationController;
use App\Livewire\AskAssistant;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/crm')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/crm')->name('dashboard');

    Route::livewire('/select-sector', 'sector-selector')->name('sector.select');

    Route::livewire('/crm/ask-assistant', AskAssistant::class)->name('ask-assistant');

    Route::get('/documents/view', [CandidateDocumentController::class, 'show'])->name('documents.view');
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

// Deliberately unauthenticated so images embedded in outbound emails render
// for external recipients — see EmailImageController for how this stays safe.
Route::get('/email-images/{path}', [EmailImageController::class, 'show'])
    ->where('path', '.*')
    ->middleware('signed')
    ->name('email-images.show');

// Exposed to public routes for candidates applying directly to a vacancy
Route::livewire('/vacancy/{vacancy:slug}', 'vacancy.apply-form')->name('vacancy.apply');

// Deliberately unauthenticated (and unsigned, unlike email-images above) —
// a company's logo is stable, non-sensitive branding shown on every login
// screen and portal page, not one-off user content, so it needs a
// permanent, freely-cacheable URL rather than a short-lived signed one.
Route::get('/company-logo/{company}', [CompanyLogoController::class, 'show'])->name('company.logo');

require __DIR__.'/settings.php';
