<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Analytics\CandidatesReport;
use App\Filament\Pages\Analytics\ClientsReport;
use App\Filament\Pages\Analytics\RevenueMarginReport;
use App\Filament\Pages\Analytics\VacanciesReport;
use App\Filament\Pages\CandidateSettings;
use App\Filament\Pages\ClientSettings;
use App\Filament\Pages\ComplianceDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\JobSettings;
use App\Filament\Pages\Reports;
use App\Filament\Pages\RunPayroll;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Http\Middleware\EnsureAccountSetupIsComplete;
use App\Http\Middleware\SetActiveIndustry;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
                Action::make('account_security')
                    ->label('Password & 2FA')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn () => route('security.edit')),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_AFTER,
                fn () => view('filament.impersonation-banner'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.high-priority-todo-notifications-topbar'),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE,
                fn () => view('filament.candidate-header-photo'),
                scopes: [EditEducationCandidate::class, EditHealthcareCandidate::class],
            )
            ->navigationGroups([
                NavigationGroup::make('Analytics'),
                NavigationGroup::make('Settings')->collapsed(),
                NavigationGroup::make('Admin')->collapsed(),
                NavigationGroup::make('Marketing')->collapsed(),
                NavigationGroup::make('Site Settings'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                ComplianceDashboard::class,
                CandidateSettings::class,
                ClientSettings::class,
                JobSettings::class,
                RunPayroll::class,
                Reports::class,
                RevenueMarginReport::class,
                VacanciesReport::class,
                ClientsReport::class,
                CandidatesReport::class,
            ])
            ->navigationItems([
                NavigationItem::make('Ask Assistant')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->url(fn () => route('ask-assistant'))
                    ->sort(2),
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
                EnsureAccountSetupIsComplete::class,
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
