<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAccountSetupIsComplete;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->viteTheme('resources/css/filament/client/theme.css')
            ->login(false)
            ->authGuard('web')
            ->colors([
                'primary' => Color::Green,
            ])
            ->brandLogo(fn (): string => auth()->user()?->company?->logoUrl() ?? asset('images/appletech.png'))
            ->brandLogoHeight('3rem')
            ->favicon(fn (): string => auth()->user()?->company?->faviconUrl() ?? asset('images/appletech-favicon.png'))
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.client-portal-header'),
            )
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\Filament\Client\Pages')
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\Filament\Client\Widgets')
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
                EnsureAccountSetupIsComplete::class,
            ]);
    }
}
