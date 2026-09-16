<?php

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Support\Carbon;

/**
 * The single source of truth for the Clients list's status colour, kept
 * here rather than duplicated in the table column and anywhere else a
 * client's status might be shown — computed live from booking data on every
 * read rather than stored/cached, so it can never drift out of sync with
 * the bookings that actually determine it.
 *
 * Precedence (first match wins):
 * - Green: has a non-cancelled booked day today — currently working with
 *   the client.
 * - Yellow: has booked before, but their last booking ended 60+ days ago.
 * - Orange: has never booked at all, and has been a client for 14+ days —
 *   a lapsed potential client rather than a lapsed working one.
 * - No colour: everything else, including a client with booking history
 *   who's gone quiet for somewhere between the Orange and Yellow
 *   thresholds (14-59 days) — Orange is reserved for clients who've never
 *   booked, so that gap is deliberately left uncoloured rather than guessed
 *   at with either rule.
 */
class ClientStatusColour
{
    private const ORANGE_LAPSED_DAYS = 14;

    private const YELLOW_LAPSED_DAYS = 60;

    public static function for(Client $client): ?string
    {
        if ($client->hasActiveBookingToday()) {
            return 'success';
        }

        $lastBookingDate = $client->last_booking_date;

        if ($lastBookingDate !== null) {
            return Carbon::parse($lastBookingDate)->lt(now()->subDays(self::YELLOW_LAPSED_DAYS))
                ? 'yellow'
                : null;
        }

        return $client->created_at !== null && $client->created_at->lt(now()->subDays(self::ORANGE_LAPSED_DAYS))
            ? 'orange'
            : null;
    }
}
