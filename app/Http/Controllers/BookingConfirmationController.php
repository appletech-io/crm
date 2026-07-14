<?php

namespace App\Http\Controllers;

use App\Services\Education\BookingConfirmationLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingConfirmationController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        $booking = BookingConfirmationLink::decode((string) $request->query('crypt'));

        abort_if(! $booking || ! $booking->confirmation_pdf_path, 404);

        abort_unless(Storage::disk('local')->exists($booking->confirmation_pdf_path), 404);

        return Storage::disk('local')->response(
            $booking->confirmation_pdf_path,
            "booking-{$booking->id}-confirmation.pdf"
        );
    }
}
