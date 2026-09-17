<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Filament\Resources\CandidateSkills\CandidateSkillResource;
use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Filament\Resources\JobTitles\JobTitleResource;
use App\Filament\Resources\QualificationJobTitles\QualificationJobTitleResource;
use App\Filament\Resources\Qualifications\QualificationResource;
use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use App\Filament\Resources\SampleProfiles\SampleProfileResource;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\QualificationJobTitle;
use App\Models\ReferenceForm;
use App\Models\SampleProfile;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CandidateSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    /**
     * Most of these are company-wide config resources (admin/site_admin
     * only, Reference Forms also compliance — see each resource's own
     * canViewAny()); Candidate Pools is always the viewer's own data, and
     * CandidatePoolResource itself has no role restriction. A consultant or
     * compliance user reaching this page (see CandidateSettings::canAccess())
     * only sees the stats they can actually open, rather than a card that
     * 403s when clicked.
     */
    protected function getStats(): array
    {
        $poolsCount = CandidatePool::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->where(fn ($q) => $q
                ->where('user_id', Auth::id())
                ->orWhere(fn ($q) => $q->where('company_pool', true)->whereNull('user_id'))
            )
            ->count();

        $poolStat = Stat::make('Candidate Pools', $poolsCount)
            ->description('Your pools and company pools')
            ->descriptionIcon('heroicon-m-rectangle-stack')
            ->color('primary')
            ->url(CandidatePoolResource::getUrl('index'));

        $isAdmin = Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false;

        $stats = [];

        if ($isAdmin) {
            $skillsCount = CandidateSkill::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Skills', $skillsCount)
                ->description('Candidate skills configured')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->url(CandidateSkillResource::getUrl('index'));

            $statusesCount = CandidateStatus::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Candidate Statuses', $statusesCount)
                ->description('Statuses and automations configured')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary')
                ->url(CandidateStatusResource::getUrl('index'));
        }

        $stats[] = $poolStat;

        if ($isAdmin) {
            $jobTitlesCount = JobTitle::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Job Titles', $jobTitlesCount)
                ->description('Job titles configured')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->url(JobTitleResource::getUrl('index'));

            $qualificationsCount = Qualification::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Qualifications', $qualificationsCount)
                ->description('Qualifications configured')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->url(QualificationResource::getUrl('index'));

            $qualificationJobTitlesCount = QualificationJobTitle::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Allowed Job Titles', $qualificationJobTitlesCount)
                ->description('Job titles allowed per qualification')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->url(QualificationJobTitleResource::getUrl('index'));
        }

        if ($isAdmin || (Auth::user()?->hasRole('compliance') ?? false)) {
            $referenceFormsCount = ReferenceForm::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Reference Forms', $referenceFormsCount)
                ->description('Reference form builders configured')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->url(ReferenceFormResource::getUrl('index'));
        }

        if ($isAdmin) {
            $sampleProfilesCount = SampleProfile::query()
                ->where('company_id', Auth::user()->company_id)
                ->where('industry_id', active_industry_id())
                ->count();

            $stats[] = Stat::make('Sample Profiles', $sampleProfilesCount.' / '.SampleProfileResource::MAX_PER_SECTOR)
                ->description('Style references for AI-generated candidate profiles')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->url(SampleProfileResource::getUrl('index'));
        }

        return $stats;
    }
}
