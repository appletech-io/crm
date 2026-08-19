<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\MatchesCandidateName;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Services\Booking\BookingEligibility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Wraps the same static {@see BookingEligibility} checks the booking form
 * itself validates against, so the chat and the form can never disagree —
 * this never re-derives qualification or availability rules itself.
 */
class CheckBookingEligibility implements Tool
{
    use MatchesCandidateName;

    public function description(): Stringable|string
    {
        return 'Check whether a candidate can be booked — whether their qualification allows a given job title, '.
            'and/or whether they are available for a given date range. Provide a job title, a date range, or both.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'candidate_name' => $schema->string()->description('The candidate to check, by name')->required(),
            'job_title' => $schema->string()->description('Check whether the candidate\'s qualification allows working as this job title'),
            'from' => $schema->string()->description('Check availability from this date, YYYY-MM-DD'),
            'to' => $schema->string()->description('Check availability to this date, YYYY-MM-DD (defaults to the same as from)'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModelClass) {
            return 'No active sector is selected, so eligibility cannot be checked right now.';
        }

        $candidate = $this->whereNameContains($candidateModelClass::query(), $request['candidate_name'])->first();

        if (! $candidate) {
            return "No candidate matching \"{$request['candidate_name']}\" was found.";
        }

        if (! $request->filled('job_title') && ! $request->filled('from')) {
            return 'Tell me a job title, a date range, or both, to check eligibility for.';
        }

        $reasons = [];

        if ($request->filled('job_title')) {
            $jobTitle = JobTitle::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('industry_id', active_industry_id())
                ->where('name', 'like', '%'.$request['job_title'].'%')
                ->first();

            if (! $jobTitle) {
                return "No job title matching \"{$request['job_title']}\" was found.";
            }

            if ($reason = BookingEligibility::disallowedJobTitleReason($candidate, $jobTitle->id)) {
                $reasons[] = $reason;
            }
        }

        if ($request->filled('from')) {
            $dayPeriods = BookingForm::dayPeriodsForRange($request['from'], $request['to'] ?? $request['from']);
            $unavailable = BookingEligibility::unavailableDates($candidateModelClass, $candidate->id, $dayPeriods);

            if ($unavailable->isNotEmpty()) {
                $dates = $unavailable->map(fn (string $date): string => Carbon::parse($date)->format('jS M Y'))->implode(', ');
                $reasons[] = "This candidate is not available on: {$dates}.";
            }
        }

        $link = TodoLinkedRecord::candidateLink($candidate);
        $name = $link ? "[{$link['label']}]({$link['url']})" : trim("{$candidate->first_name} {$candidate->last_name}");

        if ($reasons === []) {
            $jobTitleSuffix = $request->filled('job_title') ? " as {$request['job_title']}" : '';

            return "Yes — {$name} can be booked{$jobTitleSuffix}.";
        }

        return "{$name} cannot be booked: ".implode(' ', $reasons);
    }
}
