<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CandidateSettings;
use App\Filament\Pages\ClientSettings;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetActiveIndustry;
use Filament\Actions\Action;
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
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('crm')
            ->login(false)
            ->authGuard('web')
            ->colors([
                'primary' => Color::Green,
            ])
            ->userMenuItems([
                Action::make('switch_sector')
                    ->label('Switch Sector')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn () => route('sector.select')),
            ])
            ->navigationGroups([
                'Settings',
                'Admin',
                'Site Settings',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                CandidateSettings::class,
                ClientSettings::class,
            ])
            ->widgets([
                //
            ])
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
            ])
            ->brandLogo(asset('images/applebough.png'))
            // ->brandLogoDarkMode(asset('images/logo-dark.svg'))
            ->brandLogoHeight('3rem')
            // ->favicon(asset('images/logo-icon.svg'))
            ->favicon(asset('favicon.svg'))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString("
                    <script>
                        const originalSetItem = localStorage.setItem.bind(localStorage);
                        localStorage.setItem = function(key, value) {
                            originalSetItem(key, value);
                            if (key === 'theme') originalSetItem('flux.appearance', value);
                            if (key === 'flux.appearance') originalSetItem('theme', value);
                        };
                    </script>
                ")
            );
    }
}
