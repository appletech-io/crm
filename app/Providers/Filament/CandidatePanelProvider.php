<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectVettingCandidateToDocuments;
use App\Http\Middleware\SetActiveIndustry;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CandidatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('candidate')
            ->path('candidate')
            ->login(false)
            ->authGuard('web')
            ->colors([
                'primary' => Color::Green,
            ])
            ->brandLogo(asset('favicon.svg'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('favicon.svg'))
            ->discoverResources(in: app_path('Filament/Candidate/Resources'), for: 'App\Filament\Candidate\Resources')
            ->discoverPages(in: app_path('Filament/Candidate/Pages'), for: 'App\Filament\Candidate\Pages')
            ->discoverWidgets(in: app_path('Filament/Candidate/Widgets'), for: 'App\Filament\Candidate\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SetActiveIndustry::class,
                RedirectVettingCandidateToDocuments::class,
            ]);
    }
}
