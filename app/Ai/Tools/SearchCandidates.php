<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\PaginatesResults;
use App\Enums\PaymentMethod;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Industry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
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
    use PaginatesResults;

    protected int $perPage = 50;

    public function description(): Stringable|string
    {
        return 'Search the current user\'s candidates (for the currently active sector) by status, skill, '.
            'qualification, region, pool, and/or payment method. Returns at most 50 matching candidates per page '.
            '(use "offset" to page through more) with their name, status, and qualification only — no compliance '.
            'or personal details, and never their matched location.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Match candidates whose current status contains this text, e.g. "Live"'),
            'skill' => $schema->string()->description('Match candidates who have a skill containing this text'),
            'qualification' => $schema->string()->description('Match candidates whose qualification contains this text'),
            'region' => $schema->string()->description('Match candidates whose city, county, or postcode contains this text'),
            'pool' => $schema->string()->description('Match candidates in a pool whose name contains this text'),
            'payment_method' => $schema->string()->description('Match candidates by payment method: "paye" or "umbrella"'),
            'offset' => $schema->integer()->description('Skip this many matching results, for pagination — omit or 0 for the first page'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModel) {
            return 'No active sector is selected, so candidates cannot be searched right now.';
        }

        // "Qualification" and "payment method" are Education/Healthcare-only
        // concepts — the generic Candidate model has neither column nor
        // relation, so every reference to them here must be conditional on
        // the resolved model actually supporting them.
        $supportsQualification = method_exists($candidateModel, 'qualification');

        $candidates = $candidateModel::query()
            ->select(array_filter(['id', 'first_name', 'last_name', $supportsQualification ? 'qualification_id' : null]))
            ->with(array_filter(['latestStatus.status', $supportsQualification ? 'qualification' : null]))
            ->visibleToCurrentUser()
            ->when($request->filled('status'), fn ($query) => $query->whereHas(
                'latestStatus.status',
                fn ($q) => $q->where('name', 'like', '%'.$request['status'].'%')
            ))
            ->when($request->filled('skill'), fn ($query) => $query->whereHas(
                'skills',
                fn ($q) => $q->where('name', 'like', '%'.$request['skill'].'%')
            ))
            ->when($supportsQualification && $request->filled('qualification'), fn ($query) => $query->whereHas(
                'qualification',
                fn ($q) => $q->where('name', 'like', '%'.$request['qualification'].'%')
            ))
            ->when($request->filled('region'), fn ($query) => $query->where(
                fn ($q) => $q->where('city', 'like', '%'.$request['region'].'%')
                    ->orWhere('county', 'like', '%'.$request['region'].'%')
                    ->orWhere('postcode', 'like', '%'.$request['region'].'%')
            ))
            ->when($request->filled('pool'), fn ($query) => $query->whereHas(
                'candidatePools',
                fn ($q) => $q->where('name', 'like', '%'.$request['pool'].'%')
            ))
            ->when($supportsQualification && $request->filled('payment_method'), fn ($query) => $query->where(
                'payment_method', PaymentMethod::tryFrom(strtolower((string) $request['payment_method']))
            ))
            ->orderBy('first_name');

        $offset = $this->offset($request);
        $total = $candidates->count();
        $candidates = $candidates->skip($offset)->limit($this->perPage)->get();

        if ($candidates->isEmpty()) {
            return $offset > 0 ? 'No more candidates matched.' : 'No candidates matched.';
        }

        return $candidates
            ->map(function (Model $candidate) use ($supportsQualification): string {
                $link = TodoLinkedRecord::candidateLink($candidate);
                $status = $candidate->latestStatus?->status?->name ?? 'No status';
                $qualification = $supportsQualification ? ($candidate->qualification?->name ?? 'No qualification set') : null;

                $summary = "- [{$link['label']}]({$link['url']}) — {$status}";

                return $qualification ? "{$summary} — {$qualification}" : $summary;
            })
            ->implode("\n").$this->paginationFooter($candidates->count(), $offset, $total);
    }
}
