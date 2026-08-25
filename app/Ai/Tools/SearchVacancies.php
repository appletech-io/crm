<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\PaginatesResults;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Vacancy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchVacancies implements Tool
{
    use PaginatesResults;

    protected int $perPage = 50;

    public function description(): Stringable|string
    {
        return 'Search the current user\'s job vacancies by client name, job title, status, and/or region '.
            '(matches the client\'s city, county, or postcode). Returns at most 50 matching vacancies per page '.
            '(use "offset" to page through more).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_name' => $schema->string()->description('Match vacancies for a client whose name contains this text'),
            'job_title' => $schema->string()->description('Match vacancies whose job title contains this text'),
            'status' => $schema->string()->description('Match vacancies whose status contains this text, e.g. "Open"'),
            'region' => $schema->string()->description('Match vacancies whose client city, county, or postcode contains this text'),
            'offset' => $schema->integer()->description('Skip this many matching results, for pagination — omit or 0 for the first page'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $vacancies = Vacancy::query()
            ->visibleToCurrentUser()
            ->with(['client', 'jobTitle', 'jobStatus', 'consultant'])
            ->when($request->filled('client_name'), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->where('name', 'like', '%'.$request['client_name'].'%')
            ))
            ->when($request->filled('job_title'), fn ($query) => $query->whereHas(
                'jobTitle',
                fn ($q) => $q->where('name', 'like', '%'.$request['job_title'].'%')
            ))
            ->when($request->filled('status'), fn ($query) => $query->whereHas(
                'jobStatus',
                fn ($q) => $q->where('name', 'like', '%'.$request['status'].'%')
            ))
            ->when($request->filled('region'), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->where(
                    fn ($qq) => $qq->where('city', 'like', '%'.$request['region'].'%')
                        ->orWhere('county', 'like', '%'.$request['region'].'%')
                        ->orWhere('postcode', 'like', '%'.$request['region'].'%')
                )
            ))
            ->orderBy('title');

        $offset = $this->offset($request);
        $total = $vacancies->count();
        $vacancies = $vacancies->skip($offset)->limit($this->perPage)->get();

        if ($vacancies->isEmpty()) {
            return $offset > 0 ? 'No more vacancies matched.' : 'No vacancies matched.';
        }

        return $vacancies
            ->map(function (Vacancy $vacancy): string {
                $pay = $vacancy->pay_range_label ?? ($vacancy->isTemp() ? 'No day rate set' : 'No salary set');

                $availability = $vacancy->open_for_applications ? 'Open for applications' : 'Closed for applications';

                $vacancyLink = TodoLinkedRecord::vacancyLink($vacancy);
                $clientLink = $vacancy->client ? TodoLinkedRecord::clientLink($vacancy->client) : null;
                $clientLabel = $clientLink ? "[{$clientLink['label']}]({$clientLink['url']})" : 'Unknown client';

                return "- [{$vacancyLink['label']}]({$vacancyLink['url']}) — {$clientLabel} — {$vacancy->jobTitle?->name} — ".
                    "{$vacancy->jobStatus?->name} — {$vacancy->positions_available} position(s) — {$pay} — {$availability}";
            })
            ->implode("\n").$this->paginationFooter($vacancies->count(), $offset, $total);
    }
}
