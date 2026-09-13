<?php

namespace App\Models;

use App\Casts\Money;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_rate' => Money::class,
            'amount' => Money::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
