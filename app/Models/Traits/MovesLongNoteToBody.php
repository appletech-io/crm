<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Keeps an activity's one-line summary inside the `note` column's
 * VARCHAR(255), moving anything longer down into `body` instead of letting
 * MySQL reject the write outright — in strict mode an over-long value is a
 * 1406 error, not a silent trim, so the activity is lost entirely and any
 * job logging it fails with it.
 *
 * The split is the one the schema already implies: `note` is the label
 * rendered in activity timelines and tables, so it has to stay short, while
 * `body` is TEXT and exists for the detail. Nothing is discarded — the full
 * summary is kept at the top of `body`, above whatever detail was already
 * there.
 *
 * Applied at the model rather than at each call site, so no writer — form,
 * queued job, status automation or AI action — can overflow the column,
 * including ones added later.
 */
trait MovesLongNoteToBody
{
    /** Matches the `note` column width on every activity table. */
    public const int NOTE_MAX_LENGTH = 255;

    public static function bootMovesLongNoteToBody(): void
    {
        static::saving(function (self $activity): void {
            $note = (string) $activity->note;

            if (mb_strlen($note) <= self::NOTE_MAX_LENGTH) {
                return;
            }

            $activity->body = collect([$note, $activity->body])
                ->filter(fn (?string $part): bool => filled($part))
                ->implode(PHP_EOL.PHP_EOL);

            // Room for the ellipsis Str::limit() appends, so the result is
            // never itself over the column width.
            $activity->note = Str::limit($note, self::NOTE_MAX_LENGTH - 3);
        });
    }
}
