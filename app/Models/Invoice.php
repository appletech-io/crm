<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\InvoiceType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'subtotal' => Money::class,
            'vat_amount' => Money::class,
            'total' => Money::class,
            'generated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Who this invoice is addressed to — a Client for a client invoice, or a
     * PaymentProvider (umbrella company) for a self-bill.
     */
    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * A simple, non-gapless reference derived from the row's own id — the
     * same PREFIX-{id} convention already used throughout this app for
     * Evertime's own external IDs (see EvertimeProvider). Not a true
     * sequential/gapless invoice sequence; revisit once this goes beyond
     * a proof of concept.
     */
    public static function nextNumberFor(int $id): string
    {
        return 'INV-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
