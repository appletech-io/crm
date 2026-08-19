<?php

namespace App\Ai\Tools;

use App\Filament\Support\TodoLinkedRecord;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deliberately only ever selects/returns a candidate's name, status, and
 * qualification — never compliance or personal-identity fields (DBS number,
 * NI number, address, date of birth, right-to-work documents, etc), which
 * this tool has no business surfacing to an AI chat.
 */
class SearchCandidates implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search the current user\'s candidates (for the currently active sector) by status, skill, '.
            'qualification, and/or region. Returns at most 20 matching candidates with their name, status, and '.
            'qualification only — no compliance or personal details, and never their matched location.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Match candidates whose current status contains this text, e.g. "Live"'),
            'skill' => $schema->string()->description('Match candidates who have a skill containing this text'),
            'qualification' => $schema->string()->description('Match candidates whose qualification contains this text'),
            'region' => $schema->string()->description('Match candidates whose city, county, or postcode contains this text'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModel) {
            return 'No active sector is selected, so candidates cannot be searched right now.';
        }

        $candidates = $candidateModel::query()
            ->select(['id', 'first_name', 'last_name', 'qualification_id'])
            ->with(['latestStatus.status', 'qualification'])
            ->when($request->filled('status'), fn ($query) => $query->whereHas(
                'latestStatus.status',
                fn ($q) => $q->where('name', 'like', '%'.$request['status'].'%')
            ))
            ->when($request->filled('skill'), fn ($query) => $query->whereHas(
                'skills',
                fn ($q) => $q->where('name', 'like', '%'.$request['skill'].'%')
            ))
            ->when($request->filled('qualification'), fn ($query) => $query->whereHas(
                'qualification',
                fn ($q) => $q->where('name', 'like', '%'.$request['qualification'].'%')
            ))
            ->when($request->filled('region'), fn ($query) => $query->where(
                fn ($q) => $q->where('city', 'like', '%'.$request['region'].'%')
                    ->orWhere('county', 'like', '%'.$request['region'].'%')
                    ->orWhere('postcode', 'like', '%'.$request['region'].'%')
            ))
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        if ($candidates->isEmpty()) {
            return 'No candidates matched.';
        }

        return $candidates
            ->map(function (EducationCandidate|HealthcareCandidate $candidate): string {
                $link = TodoLinkedRecord::candidateLink($candidate);
                $status = $candidate->latestStatus?->status?->name ?? 'No status';
                $qualification = $candidate->qualification?->name ?? 'No qualification set';

                return "- [{$link['label']}]({$link['url']}) — {$status} — {$qualification}";
            })
            ->implode("\n");
    }
}
