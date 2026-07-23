<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Models\Booking;

class BookingObserver
{
    public function saved(Booking $booking): void
    {
        CheckActions::run($booking);
    }
}
