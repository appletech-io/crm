<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\PaginatesResults;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchClients implements Tool
{
    use PaginatesResults;

    protected int $perPage = 50;

    public function description(): Stringable|string
    {
        return 'Search the current user\'s clients by name, client type, and/or region. Returns at most 50 '.
            'matching clients per page (use "offset" to page through more), with their type and main contact.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Match clients whose name contains this text'),
            'type' => $schema->string()->description('Match clients whose client type contains this text'),
            'region' => $schema->string()->description('Match clients whose city, county, or postcode contains this text'),
            'offset' => $schema->integer()->description('Skip this many matching results, for pagination — omit or 0 for the first page'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $clients = Client::query()
            ->visibleToCurrentUser()
            ->where('industry_id', active_industry_id())
            ->with(['clientType', 'mainContact'])
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request['name'].'%'))
            ->when($request->filled('type'), fn ($query) => $query->whereHas(
                'clientType',
                fn ($q) => $q->where('name', 'like', '%'.$request['type'].'%')
            ))
            ->when($request->filled('region'), fn ($query) => $query->where(
                fn ($q) => $q->where('city', 'like', '%'.$request['region'].'%')
                    ->orWhere('county', 'like', '%'.$request['region'].'%')
                    ->orWhere('postcode', 'like', '%'.$request['region'].'%')
            ))
            ->orderBy('name');

        $offset = $this->offset($request);
        $total = $clients->count();
        $clients = $clients->skip($offset)->limit($this->perPage)->get();

        if ($clients->isEmpty()) {
            return $offset > 0 ? 'No more clients matched.' : 'No clients matched.';
        }

        return $clients
            ->map(function (Client $client): string {
                $link = TodoLinkedRecord::clientLink($client);
                $contact = $client->mainContact;
                $contactLabel = $contact ? trim("{$contact->first_name} {$contact->last_name}").($contact->email ? " ({$contact->email})" : '') : 'No main contact set';

                return "- [{$link['label']}]({$link['url']}) — {$client->clientType?->name}".' — Main contact: '.$contactLabel;
            })
            ->implode("\n").$this->paginationFooter($clients->count(), $offset, $total);
    }
}
