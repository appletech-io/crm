<?php

namespace App\Filament\Support;

use App\Actions\Users\GenerateDefaultQuickLinks;
use App\Filament\Pages\CareLogsOverview;
use App\Filament\Pages\JobPipeline;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Filament\Resources\Vacancies\VacancyResource;

/**
 * The known set of internal CRM pages a user might want a quick link to —
 * filtered to what the current user can actually reach, given their active
 * industry and its Bookings/Perm flags. Backs both the default quick links
 * a first-time visitor is seeded with ({@see GenerateDefaultQuickLinks})
 * and the "pick from a list" option on the quick link form, so the two stay
 * in sync automatically.
 */
class QuickLinkCatalog
{
    /** @return array<int, array{label: string, icon: string, url: string}> */
    public static function availableForCurrentUser(): array
    {
        $industry = active_industry();

        $candidates = collect();

        if ($industry === 'education') {
            $candidates->push(['label' => 'Job Pipeline', 'icon' => 'heroicon-o-briefcase', 'url' => fn () => JobPipeline::getUrl(), 'canAccess' => fn () => JobPipeline::canAccess()]);
            $candidates->push(['label' => 'Vacancies', 'icon' => 'heroicon-o-building-office-2', 'url' => fn () => VacancyResource::getUrl('index'), 'canAccess' => fn () => VacancyResource::canViewAny()]);
            $candidates->push(['label' => 'Candidates', 'icon' => 'heroicon-o-academic-cap', 'url' => fn () => EducationCandidateResource::getUrl('index'), 'canAccess' => fn () => EducationCandidateResource::canViewAny()]);
        } elseif ($industry === 'healthcare') {
            $candidates->push(['label' => 'Bookings', 'icon' => 'heroicon-o-calendar-days', 'url' => fn () => BookingResource::getUrl('index'), 'canAccess' => fn () => BookingResource::canViewAny()]);
            $candidates->push(['label' => 'Vacancies', 'icon' => 'heroicon-o-building-office-2', 'url' => fn () => VacancyResource::getUrl('index'), 'canAccess' => fn () => VacancyResource::canViewAny()]);
            $candidates->push(['label' => 'Candidates', 'icon' => 'heroicon-o-heart', 'url' => fn () => HealthcareCandidateResource::getUrl('index'), 'canAccess' => fn () => HealthcareCandidateResource::canViewAny()]);
            $candidates->push(['label' => 'Care Logs', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => fn () => CareLogsOverview::getUrl(), 'canAccess' => fn () => CareLogsOverview::canAccess()]);
        }

        $candidates->push(['label' => 'Clients', 'icon' => 'heroicon-o-building-library', 'url' => fn () => ClientResource::getUrl('index'), 'canAccess' => fn () => ClientResource::canViewAny()]);
        $candidates->push(['label' => 'Reports', 'icon' => 'heroicon-o-chart-bar', 'url' => fn () => Reports::getUrl(), 'canAccess' => fn () => Reports::canAccess()]);
        $candidates->push(['label' => 'My To-Dos', 'icon' => 'heroicon-o-check-circle', 'url' => fn () => TodoItemResource::getUrl('index'), 'canAccess' => fn () => TodoItemResource::canViewAny()]);

        return $candidates
            ->filter(fn (array $link): bool => $link['canAccess']())
            ->map(fn (array $link): array => ['label' => $link['label'], 'icon' => $link['icon'], 'url' => $link['url']()])
            ->values()
            ->all();
    }
}
