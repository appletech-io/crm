<?php

namespace App\Models;

use Database\Factories\UserQuickLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $icon
 * @property string $url
 * @property int $position
 */
class UserQuickLink extends Model
{
    /** @use HasFactory<UserQuickLinkFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (UserQuickLink $quickLink) {
            if (! $quickLink->user_id && auth()->hasUser()) {
                $quickLink->user_id = auth()->user()->id;
            }

            if (! isset($quickLink->attributes['position'])) {
                $highestPosition = static::query()
                    ->where('user_id', $quickLink->user_id)
                    ->max('position');

                $quickLink->position = ($highestPosition ?? -1) + 1;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
