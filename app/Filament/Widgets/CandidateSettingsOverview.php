<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Filament\Resources\CandidateSkills\CandidateSkillResource;
use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Filament\Resources\JobTitles\JobTitleResource;
use App\Filament\Resources\QualificationJobTitles\QualificationJobTitleResource;
use App\Filament\Resources\Qualifications\QualificationResource;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\QualificationJobTitle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CandidateSettingsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $skillsCount = CandidateSkill::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $statusesCount = CandidateStatus::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $poolsCount = CandidatePool::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->where(fn ($q) => $q
                ->where('user_id', Auth::id())
                ->orWhere(fn ($q) => $q->where('company_pool', true)->whereNull('user_id'))
            )
            ->count();

        $jobTitlesCount = JobTitle::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $qualificationsCount = Qualification::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        $qualificationJobTitlesCount = QualificationJobTitle::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->count();

        return [
            Stat::make('Skills', $skillsCount)
                ->description('Candidate skills configured')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->url(CandidateSkillResource::getUrl('index')),

            Stat::make('Candidate Statuses', $statusesCount)
                ->description('Statuses and automations configured')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary')
                ->url(CandidateStatusResource::getUrl('index')),

            Stat::make('Candidate Pools', $poolsCount)
                ->description('Your pools and company pools')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary')
                ->url(CandidatePoolResource::getUrl('index')),

            Stat::make('Job Titles', $jobTitlesCount)
                ->description('Job titles configured')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->url(JobTitleResource::getUrl('index')),

            Stat::make('Qualifications', $qualificationsCount)
                ->description('Qualifications configured')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->url(QualificationResource::getUrl('index')),

            Stat::make('Allowed Job Titles', $qualificationJobTitlesCount)
                ->description('Job titles allowed per qualification')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->url(QualificationJobTitleResource::getUrl('index')),
        ];
    }
}
