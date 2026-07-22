<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Calendar;
use App\Filament\Pages\CandidateSettings;
use App\Filament\Pages\ClientSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\RunPayroll;
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
use Guava\Calendar\CalendarPlugin;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(false)
            ->authGuard('web')
            ->colors([
                'primary' => Color::Green,
                'red' => Color::Red,
                'orange' => Color::Orange,
                'amber' => Color::Amber,
                'yellow' => Color::Yellow,
                'lime' => Color::Lime,
                'green' => Color::Green,
                'emerald' => Color::Emerald,
                'teal' => Color::Teal,
                'cyan' => Color::Cyan,
                'sky' => Color::Sky,
                'blue' => Color::Blue,
                'indigo' => Color::Indigo,
                'violet' => Color::Violet,
                'purple' => Color::Purple,
                'fuchsia' => Color::Fuchsia,
                'pink' => Color::Pink,
                'rose' => Color::Rose,
            ])
            ->userMenuItems([
                Action::make('switch_sector')
                    ->label('Switch Sector')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn () => route('sector.select')),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_AFTER,
                fn () => view('filament.impersonation-banner'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.high-priority-todo-notifications-topbar'),
            )
            ->navigationGroups([
                'Settings',
                'Admin',
                'Site Settings',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->plugins([
                CalendarPlugin::make(),
            ])
            ->pages([
                Dashboard::class,
                CandidateSettings::class,
                ClientSettings::class,
                Calendar::class,
                RunPayroll::class,
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
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString("
                    <style>
                        .employment-timeline .fi-fo-repeater-items {
                            position: relative;
                        }

                        .employment-timeline .fi-fo-repeater-items::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            bottom: 0;
                            left: 50%;
                            width: 2px;
                            background-color: var(--gray-200);
                            transform: translateX(-50%);
                        }

                        .dark .employment-timeline .fi-fo-repeater-items::before {
                            background-color: var(--gray-700);
                        }

                        .employment-timeline .fi-fo-repeater-item {
                            position: relative;
                            width: calc(50% - 2.5rem);
                        }

                        .employment-timeline .fi-fo-repeater-item:nth-child(odd) {
                            margin-right: auto;
                        }

                        .employment-timeline .fi-fo-repeater-item:nth-child(even) {
                            margin-left: auto;
                        }

                        .employment-timeline .fi-fo-repeater-item::before {
                            content: '';
                            position: absolute;
                            top: 1.25rem;
                            width: 0.75rem;
                            height: 0.75rem;
                            border-radius: 9999px;
                            background-color: var(--primary-500);
                            box-shadow: 0 0 0 3px var(--gray-50);
                        }

                        .dark .employment-timeline .fi-fo-repeater-item::before {
                            box-shadow: 0 0 0 3px var(--gray-950);
                        }

                        .employment-timeline .fi-fo-repeater-item:nth-child(odd)::before {
                            right: -2.875rem;
                        }

                        .employment-timeline .fi-fo-repeater-item:nth-child(even)::before {
                            left: -2.875rem;
                        }

                        @media (max-width: 768px) {
                            .employment-timeline .fi-fo-repeater-items::before {
                                left: 0.375rem;
                            }

                            .employment-timeline .fi-fo-repeater-item {
                                width: calc(100% - 2rem) !important;
                                margin-left: 2rem !important;
                                margin-right: 0 !important;
                            }

                            .employment-timeline .fi-fo-repeater-item::before {
                                left: -1.625rem !important;
                                right: auto !important;
                            }
                        }
                    </style>
                ")
            );
    }
}
