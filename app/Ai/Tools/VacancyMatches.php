<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\MatchesCandidateName;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Industry;
use App\Models\Vacancy;
use App\Models\VacancyCandidateMatch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Surfaces existing {@see VacancyCandidateMatch} rows — never computes or
 * explains a score itself. Matching is a queued, on-demand job triggered
 * from a vacancy's edit page, so a vacancy or candidate may simply have no
 * matches yet, and no per-factor breakdown is stored to reason about.
 */
class VacancyMatches implements Tool
{
    use MatchesCandidateName;

    public function description(): Stringable|string
    {
        return 'Look up existing candidate-to-vacancy match scores, either for a vacancy (who matches it) or for '.
            'a candidate (which vacancies suit them). Matches are only available once someone has run matching for '.
            'that vacancy — this never computes a new score. Returns at most 10 matches.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'vacancy_title' => $schema->string()->description('Match a vacancy whose title contains this text, to see which candidates match it'),
            'candidate_name' => $schema->string()->description('Match a candidate whose name contains this text, to see which vacancies match them'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $request->filled('vacancy_title') && ! $request->filled('candidate_name')) {
            return 'Tell me either a vacancy title or a candidate name to look up matches for.';
        }

        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($request->filled('vacancy_title')) {
            return $this->matchesForVacancy($request['vacancy_title'], $candidateModelClass);
        }

        return $this->matchesForCandidate($request['candidate_name'], $candidateModelClass);
    }

    private function matchesForVacancy(string $vacancyTitle, ?string $candidateModelClass): string
    {
        $vacancy = Vacancy::query()
            ->visibleToCurrentUser()
            ->where('title', 'like', '%'.$vacancyTitle.'%')
            ->first();

        if (! $vacancy) {
            return "No vacancy matching \"{$vacancyTitle}\" was found.";
        }

        $matches = VacancyCandidateMatch::query()
            ->where('vacancy_id', $vacancy->id)
            ->when($candidateModelClass, fn ($q) => $q->where('candidate_type', $candidateModelClass))
            ->with('candidate')
            ->orderByDesc('score')
            ->limit(10)
            ->get();

        if ($matches->isEmpty()) {
            return "No matches have been run for \"{$vacancy->title}\" yet — run matching from the vacancy's edit page first.";
        }

        return $matches
            ->map(function (VacancyCandidateMatch $match): string {
                $link = TodoLinkedRecord::candidateLink($match->candidate);
                $name = $link ? "[{$link['label']}]({$link['url']})" : 'Unknown candidate';

                return "- {$name} — {$match->score}% match (matched {$match->created_at->diffForHumans()})";
            })
            ->implode("\n");
    }

    private function matchesForCandidate(string $candidateName, ?string $candidateModelClass): string
    {
        if (! $candidateModelClass) {
            return 'No active sector is selected, so candidates cannot be matched right now.';
        }

        $candidate = $this->whereNameContains($candidateModelClass::query(), $candidateName)->first();

        if (! $candidate) {
            return "No candidate matching \"{$candidateName}\" was found.";
        }

        $matches = VacancyCandidateMatch::query()
            ->whereHasMorph('candidate', [$candidateModelClass], fn ($q) => $q->where('id', $candidate->id))
            ->whereHas('vacancy', fn ($q) => $q->visibleToCurrentUser())
            ->with('vacancy.client')
            ->orderByDesc('score')
            ->limit(10)
            ->get();

        if ($matches->isEmpty()) {
            $name = trim("{$candidate->first_name} {$candidate->last_name}");

            return "No vacancy matches have been run against {$name} yet — run matching from a vacancy's edit page first.";
        }

        return $matches
            ->map(function (VacancyCandidateMatch $match): string {
                $vacancy = $match->vacancy;

                if (! $vacancy) {
                    return "- Unknown vacancy — {$match->score}% match (matched {$match->created_at->diffForHumans()})";
                }

                $vacancyLink = TodoLinkedRecord::vacancyLink($vacancy);
                $clientLink = $vacancy->client ? TodoLinkedRecord::clientLink($vacancy->client) : null;
                $clientLabel = $clientLink ? "[{$clientLink['label']}]({$clientLink['url']})" : 'an unknown client';

                return "- [{$vacancyLink['label']}]({$vacancyLink['url']}) at {$clientLabel} — ".
                    "{$match->score}% match (matched {$match->created_at->diffForHumans()})";
            })
            ->implode("\n");
    }
}
