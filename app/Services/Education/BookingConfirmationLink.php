<?php

namespace App\Services\Education;

use App\Models\EducationBooking;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class BookingConfirmationLink
{
    public static function encode(EducationBooking $booking): string
    {
        return Crypt::encryptString((string) $booking->id);
    }

    public static function decode(string $crypt): ?EducationBooking
    {
        try {
            $id = Crypt::decryptString($crypt);
        } catch (DecryptException) {
            return null;
        }

        return EducationBooking::withTrashed()->find($id);
    }

    public static function url(EducationBooking $booking): string
    {
        return route('booking-confirmation.show', ['crypt' => self::encode($booking)]);
    }
}
