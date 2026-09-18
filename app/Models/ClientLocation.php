<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasProviderExternalId;
use Database\Factories\ClientLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLocation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ClientLocationFactory> */
    use HasFactory;

    use HasProviderExternalId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ClientLocation $location): void {
            if ($location->is_default) {
                static::where('client_id', $location->client_id)
                    ->when($location->exists, fn ($query) => $query->whereKeyNot($location->getKey()))
                    ->update(['is_default' => false]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
