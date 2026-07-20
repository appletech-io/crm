<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany;

    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'key_stages' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function mainContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('main_contact', true);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ClientActivity::class, 'model')->latest();
    }

    public function chargeRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }
}
