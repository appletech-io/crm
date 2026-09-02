<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Candidate;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use App\Observers\BookingObserver;
use App\Observers\CandidateCandidateStatusObserver;
use App\Observers\CandidateObserver;
use App\Observers\CandidateReferenceObserver;
use App\Observers\ClientContactObserver;
use App\Observers\ClientObserver;
use App\Observers\EducationCandidateObserver;
use App\Observers\HealthcareCandidateObserver;
use App\Observers\UserObserver;
use App\Observers\VacancyObserver;
use App\Observers\VacancyPlacementObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        EducationCandidate::observe(EducationCandidateObserver::class);
        HealthcareCandidate::observe(HealthcareCandidateObserver::class);
        Candidate::observe(CandidateObserver::class);
        Client::observe(ClientObserver::class);
        ClientContact::observe(ClientContactObserver::class);
        Booking::observe(BookingObserver::class);
        User::observe(UserObserver::class);
        Vacancy::observe(VacancyObserver::class);
        CandidateReference::observe(CandidateReferenceObserver::class);
        CandidateCandidateStatus::observe(CandidateCandidateStatusObserver::class);
        VacancyPlacement::observe(VacancyPlacementObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Microsoft Graph throttles an app's total request payload bytes, not
     * just request count — a burst of queued emails (each carrying a base64
     * logo attachment) can trip it well before any per-minute request-count
     * limit would matter on its own. Scoped per company since each has its
     * own Graph app registration/credentials, so one company's burst can't
     * starve another's send capacity.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('microsoft-graph-mail', function ($job) {
            $company = method_exists($job, 'graphMailCompany') ? $job->graphMailCompany() : null;

            return $company
                ? Limit::perMinute(20)->by($company->id)
                : Limit::none();
        });
    }
}
