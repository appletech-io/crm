<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\MatchesCandidateName;
use App\Enums\BookingStatus;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Booking;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchBookings implements Tool
{
    use MatchesCandidateName;

    public function description(): Stringable|string
    {
        return 'Search the current user\'s bookings by client name, candidate name, status, region, and/or date '.
            'range. Returns at most 20 matching bookings.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_name' => $schema->string()->description('Match bookings for a client whose name contains this text'),
            'candidate_name' => $schema->string()->description('Match bookings for a candidate whose name contains this text'),
            'status' => $schema->string()->description('One of: requested, upcoming, awaiting_approval, approved, completed'),
            'region' => $schema->string()->description('Match bookings for a client whose city, county, or postcode contains this text'),
            'from' => $schema->string()->description('Only bookings starting on or after this date, YYYY-MM-DD'),
            'to' => $schema->string()->description('Only bookings ending on or before this date, YYYY-MM-DD'),
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
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = BookingStatus::tryFrom(Str::snake((string) $request['status']));

                return $status ? $query->where('status', $status) : $query;
            })
            ->when($request->filled('from'), fn ($query) => $query->where('start_date', '>=', $request['from']))
            ->when($request->filled('to'), fn ($query) => $query->where('end_date', '<=', $request['to']))
            ->orderByDesc('start_date')
            ->limit(20)
            ->get();

        if ($bookings->isEmpty()) {
            return 'No bookings matched.';
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
            ->implode("\n");
    }
}
