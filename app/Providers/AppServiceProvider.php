<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use App\Observers\BookingObserver;
use App\Observers\ClientObserver;
use App\Observers\EducationCandidateObserver;
use App\Observers\HealthcareCandidateObserver;
use App\Observers\UserObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

        EducationCandidate::observe(EducationCandidateObserver::class);
        HealthcareCandidate::observe(HealthcareCandidateObserver::class);
        Client::observe(ClientObserver::class);
        Booking::observe(BookingObserver::class);
        User::observe(UserObserver::class);
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
}
