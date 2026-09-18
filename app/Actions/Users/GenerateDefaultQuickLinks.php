<?php

namespace App\Actions\Users;

use App\Filament\Pages\JobPipeline;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A starting set of up to 6 quick links, tailored to the current user's
 * active industry and its Bookings/Perm feature flags, for them to then
 * edit freely via My Quick Links. Only ever runs once — for a user with no
 * quick links of their own yet — so later edits are never overwritten.
 */
class GenerateDefaultQuickLinks
{
    use AsAction;

    /**
     * @return array<int, array{label: string, icon: string, url: string}>
     */
    public function handle(): array
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
        }

        $candidates->push(['label' => 'Clients', 'icon' => 'heroicon-o-building-library', 'url' => fn () => ClientResource::getUrl('index'), 'canAccess' => fn () => ClientResource::canViewAny()]);
        $candidates->push(['label' => 'Reports', 'icon' => 'heroicon-o-chart-bar', 'url' => fn () => Reports::getUrl(), 'canAccess' => fn () => Reports::canAccess()]);
        $candidates->push(['label' => 'My To-Dos', 'icon' => 'heroicon-o-check-circle', 'url' => fn () => TodoItemResource::getUrl('index'), 'canAccess' => fn () => TodoItemResource::canViewAny()]);

        return $candidates
            ->filter(fn (array $link): bool => $link['canAccess']())
            ->map(fn (array $link): array => ['label' => $link['label'], 'icon' => $link['icon'], 'url' => $link['url']()])
            ->take(6)
            ->values()
            ->all();
    }
}
