<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property Carbon|null $time
 * @property string $title
 * @property string|null $notes
 */
class CompanionEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time' => 'datetime:H:i',
        ];
    }

    /**
     * Half-hour time slots (00:00–23:30) for the "add event" picker —
     * deliberately not free-text, so nothing more precise than the
     * half hour can be entered.
     *
     * @return array<string, string>
     */
    public static function timeSlotOptions(): array
    {
        $slots = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
            $time = Carbon::createFromTime(0, 0)->addMinutes($minutes);
            $slots[$time->format('H:i')] = $time->format('g:i A');
        }

        return $slots;
    }

    protected function displayTime(): Attribute
    {
        return Attribute::get(fn () => $this->time?->format('g:i A') ?? 'Anytime');
    }
}
