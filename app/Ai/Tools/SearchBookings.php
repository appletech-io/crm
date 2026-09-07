<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\MatchesCandidateName;
use App\Ai\Tools\Concerns\PaginatesResults;
use App\Enums\BookingStatus;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Booking;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchBookings implements Tool
{
    use MatchesCandidateName, PaginatesResults;

    protected int $perPage = 50;

    public function description(): Stringable|string
    {
        return 'Search the current user\'s bookings by client name, candidate name, status, region, consultant, '.
            'and/or date range. Returns at most 50 matching bookings per page (use "offset" to page through more).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_name' => $schema->string()->description('Match bookings for a client whose name contains this text'),
            'candidate_name' => $schema->string()->description('Match bookings for a candidate whose name contains this text'),
            'consultant_name' => $schema->string()->description('Admin only: only show bookings belonging to the consultant whose name contains this text. Leave blank to search every consultant\'s bookings (admins) or your own (non-admins, who only ever see their own regardless).'),
            'status' => $schema->string()->description('One of: requested, upcoming, awaiting_approval, approved, completed'),
            'region' => $schema->string()->description('Match bookings for a client whose city, county, or postcode contains this text'),
            'from' => $schema->string()->description('Only bookings with a scheduled, non-cancelled day on or after this date, YYYY-MM-DD (a booking that starts before this date but is still ongoing still counts)'),
            'to' => $schema->string()->description('Only bookings with a scheduled, non-cancelled day on or before this date, YYYY-MM-DD (a booking that started by this date but ends later still counts)'),
            'offset' => $schema->integer()->description('Skip this many matching results, for pagination — omit or 0 for the first page'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookings = Booking::query()
            ->visibleToCurrentUser()
            ->with(['client', 'jobTitle', 'candidate'])
            ->when($request->filled('client_name'), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->where('name', 'like', '%'.$request['client_name'].'%')
            ))
            ->when($request->filled('region'), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->where(
                    fn ($qq) => $qq->where('city', 'like', '%'.$request['region'].'%')
                        ->orWhere('county', 'like', '%'.$request['region'].'%')
                        ->orWhere('postcode', 'like', '%'.$request['region'].'%')
                )
            ))
            ->when($request->filled('candidate_name'), fn ($query) => $query->whereHasMorph(
                'candidate',
                [EducationCandidate::class, HealthcareCandidate::class],
                fn ($q) => $this->whereNameContains($q, $request['candidate_name'])
            ))
            ->when($request->filled('consultant_name'), function ($query) use ($request) {
                if (! auth()->user()?->isAdmin()) {
                    return $query;
                }

                $consultant = User::role('consultant')
                    ->where('name', 'like', '%'.$request['consultant_name'].'%')
                    ->first();

                return $consultant ? $query->where('consultant_id', $consultant->id) : $query->whereRaw('1 = 0');
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = BookingStatus::tryFrom(Str::snake((string) $request['status']));

                return $status ? $query->where('status', $status) : $query;
            })
            // A booking's start_date/end_date is just its overall bounding
            // range — whether it's actually scheduled (and not cancelled) on
            // any given day within that range lives on its BookingDay rows,
            // so "occurring in this window" must be checked there rather
            // than against the coarse range, or e.g. a "today" query would
            // wrongly count bookings whose day today was cancelled, or miss
            // nothing at all but count days that were never scheduled.
            ->when($request->filled('from') || $request->filled('to'), fn ($query) => $query->whereHas(
                'dayPeriods',
                function ($q) use ($request) {
                    $q->whereNull('cancelled_at');

                    if ($request->filled('from')) {
                        $q->where('date', '>=', $request['from']);
                    }

                    if ($request->filled('to')) {
                        $q->where('date', '<=', $request['to']);
                    }
                }
            ))
            ->orderByDesc('start_date');

        $offset = $this->offset($request);
        $total = $bookings->count();
        $bookings = $bookings->skip($offset)->limit($this->perPage)->get();

        if ($bookings->isEmpty()) {
            return $offset > 0 ? 'No more bookings matched.' : 'No bookings matched.';
        }

        return $bookings
            ->map(function (Booking $booking): string {
                $candidateLink = TodoLinkedRecord::candidateLink($booking->candidate);
                $candidateName = $candidateLink ? "[{$candidateLink['label']}]({$candidateLink['url']})" : 'Unknown candidate';

                $clientLink = $booking->client ? TodoLinkedRecord::clientLink($booking->client) : null;
                $clientLabel = $clientLink ? "[{$clientLink['label']}]({$clientLink['url']})" : 'Unknown client';

                $bookingLink = TodoLinkedRecord::bookingLink($booking);
                $dates = $booking->start_date?->toDateString().($booking->end_date && ! $booking->end_date->equalTo($booking->start_date) ? ' to '.$booking->end_date->toDateString() : '');

                return "- {$dates} — {$booking->status->label()} — {$candidateName} as {$booking->jobTitle?->name} for ".
                    "{$clientLabel} — [{$bookingLink['label']}]({$bookingLink['url']})";
            })
            ->implode("\n").$this->paginationFooter($bookings->count(), $offset, $total);
    }
}
