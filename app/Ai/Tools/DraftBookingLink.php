<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\MatchesCandidateName;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Client;
use App\Models\Industry;
use App\Models\JobTitle;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Never creates a booking — resolves a candidate (required), and optionally
 * a client and job title, by name, then returns a link to the real "Create
 * Booking" page with those and the given dates already filled in via the
 * same query-string prefill CreateBooking::queryStringPrefillData() already
 * supports (the same mechanism the candidate Search tab's own "Book" button
 * uses). The user still has to open that link and submit the form
 * themselves — nothing is persisted by this tool.
 */
class DraftBookingLink implements Tool
{
    use MatchesCandidateName;

    public function description(): Stringable|string
    {
        return 'Opens a pre-filled "Create Booking" page for the user to review and submit themselves — this NEVER '.
            'creates, saves, or confirms a booking on its own, it only returns a link with the candidate, client, '.
            'job title, and dates already filled in on the form. Always present the link and tell the user it\'s a '.
            'draft they still need to open and submit — never say or imply a booking has actually been made.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'candidate_name' => $schema->string()->description('The candidate to pre-fill, matched by name')->required(),
            'client_name' => $schema->string()->description('The client to pre-fill, matched by name — leave blank to let the user pick one on the form'),
            'job_title' => $schema->string()->description('The job title to pre-fill, matched by name — leave blank to let the user pick one on the form'),
            'start_date' => $schema->string()->description('First day of the booking, YYYY-MM-DD')->required(),
            'end_date' => $schema->string()->description('Last day of the booking, YYYY-MM-DD — omit for a single day. Weekends within the range are skipped automatically, matching how this app books term-time work.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModel) {
            return 'No active sector is selected, so a booking can\'t be drafted right now.';
        }

        // Matches the create form's own candidate options — any candidate in
        // the active sector, not just the current user's own, since booking
        // shares the whole company's candidate pool rather than being
        // siloed per consultant the way clients are.
        $candidates = $candidateModel::query()
            ->where(fn ($q) => $this->whereNameContains($q, $request['candidate_name']))
            ->get();

        if ($candidates->isEmpty()) {
            return "No candidate matching \"{$request['candidate_name']}\" was found.";
        }

        if ($candidates->count() > 1) {
            return "Multiple candidates match \"{$request['candidate_name']}\": ".
                $this->namesOf($candidates).'. Ask again with a more specific name.';
        }

        $candidate = $candidates->first();

        $client = null;

        if ($request->filled('client_name')) {
            // Matches the create form's own client options — the current
            // user's own clients only, same as Client::visibleToCurrentUser()
            // everywhere else, so a resolved client_id is guaranteed to
            // actually be selectable when the page loads.
            $clients = Client::query()
                ->visibleToCurrentUser()
                ->where('name', 'like', '%'.$request['client_name'].'%')
                ->get();

            if ($clients->isEmpty()) {
                return "No client matching \"{$request['client_name']}\" was found.";
            }

            if ($clients->count() > 1) {
                return "Multiple clients match \"{$request['client_name']}\": ".
                    $clients->pluck('name')->implode(', ').'. Ask again with a more specific name.';
            }

            $client = $clients->first();
        }

        $jobTitle = null;

        if ($request->filled('job_title')) {
            $jobTitles = JobTitle::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('industry_id', active_industry_id())
                ->where('name', 'like', '%'.$request['job_title'].'%')
                ->get();

            if ($jobTitles->isEmpty()) {
                return "No job title matching \"{$request['job_title']}\" was found.";
            }

            if ($jobTitles->count() > 1) {
                return "Multiple job titles match \"{$request['job_title']}\": ".
                    $jobTitles->pluck('name')->implode(', ').'. Ask again with a more specific name.';
            }

            $jobTitle = $jobTitles->first();
        }

        $start = Carbon::parse($request['start_date']);
        $end = $request->filled('end_date') ? Carbon::parse($request['end_date']) : $start;

        $dates = collect(CarbonPeriod::create($start, $end))
            ->reject(fn (Carbon $date): bool => $date->isWeekend())
            ->map(fn (Carbon $date): string => $date->toDateString())
            ->values();

        if ($dates->isEmpty()) {
            return 'That date range has no weekdays in it.';
        }

        $url = BookingResource::getUrl('create', array_filter([
            'candidate_id' => $candidate->id,
            'client_id' => $client?->id,
            'job_title_id' => $jobTitle?->id,
            'dates' => $dates->all(),
        ]));

        $candidateName = trim("{$candidate->first_name} {$candidate->last_name}");

        return "[Open a pre-filled booking for {$candidateName}]({$url}) — nothing has been created yet; ".
            'review it and submit the form yourself to actually book it.';
    }

    /** @param  Collection<int, Model>  $candidates */
    private function namesOf($candidates): string
    {
        return $candidates
            ->map(fn ($candidate): string => trim("{$candidate->first_name} {$candidate->last_name}"))
            ->implode(', ');
    }
}
