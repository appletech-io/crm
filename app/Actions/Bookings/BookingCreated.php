<?php

namespace App\Actions\Bookings;

use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\EducationBooking;
use Lorisleiva\Actions\Concerns\AsAction;

class BookingCreated
{
    use AsAction;

    public function handle(EducationBooking $booking): void
    {
        GenerateBookingConfirmationPdf::dispatch($booking);

        SendBookingConfirmationEmail::dispatch($booking);
        SendClientBookingConfirmationEmail::dispatch($booking);
    }
}
