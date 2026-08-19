<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reports the same weekly bookings/revenue/cost/margin figures a consultant
 * can already see about themselves via the Bookings resource's week-stats
 * widget — this tool doesn't expose anything new, just a chat-based path to
 * it. Non-admins can only ever see their own figures.
 */
class ConsultantPerformance implements Tool
{
    public function description(): Stringable|string
    {
        return 'Reports this week\'s bookings count, revenue, cost, and margin for the current consultant. '.
            'Admins may pass a consultant_name to view another consultant\'s figures; non-admins can only see their own.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'consultant_name' => $schema->string()->description('Admin only: view another consultant\'s figures by name. Leave blank to see your own.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();

        if ($request->filled('consultant_name') && ! $user->isAdmin()) {
            return 'You can only see your own performance figures.';
        }

        if ($request->filled('consultant_name')) {
            $consultant = User::role('consultant')
                ->where('name', 'like', '%'.$request['consultant_name'].'%')
                ->first();

            if (! $consultant) {
                return "No consultant matching \"{$request['consultant_name']}\" was found.";
            }
        } else {
            $consultant = $user;
        }

        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $totals = BookingRevenuePeriodCalculator::totals($start, $end, $consultant->id);

        return "{$consultant->name} — this week ({$start->toDateString()} to {$end->toDateString()}): ".
            "{$totals['bookings']} bookings, revenue £".number_format($totals['revenue'], 2).
            ', cost £'.number_format($totals['cost'], 2).
            ', margin £'.number_format($totals['margin'], 2).
            ' ('.number_format($totals['avgMargin'] * 100, 1).'% avg margin).';
    }
}
