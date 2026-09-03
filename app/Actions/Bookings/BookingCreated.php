<?php

namespace App\Actions\Bookings;

use App\Actions\Clients\EnsureClientCandidatePool;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\ClientContact;
use Lorisleiva\Actions\Concerns\AsAction;

class BookingCreated
{
    use AsAction;

    public function handle(Booking $booking): void
    {
        GenerateBookingConfirmationPdf::dispatch($booking);

        SendBookingConfirmationEmail::dispatch($booking);

        $bookingContacts = $booking->client?->bookingContacts() ?? collect();

        if ($bookingContacts->isEmpty()) {
            SendClientBookingConfirmationEmail::dispatch($booking);
        } else {
            $bookingContacts->each(
                fn (ClientContact $contact) => SendClientBookingConfirmationEmail::dispatch($booking, $contact)
            );
        }

        $this->addCandidateToClientPool($booking);
    }

    private function addCandidateToClientPool(Booking $booking): void
    {
        $client = $booking->client;

        if (! $client || ! $booking->candidate_type || ! $booking->candidate_id) {
            return;
        }

        $pool = EnsureClientCandidatePool::run($client);

        $pool->candidatesOfType($booking->candidate_type)->syncWithoutDetaching([$booking->candidate_id]);
    }
}
