<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    /**
     * Eloquent doesn't reload column defaults into the in-memory model after
     * an insert, so a contact created without explicitly passing
     * wants_portal_access would otherwise look "off" to code running in the
     * same request (e.g. ClientContactObserver::created()) even though the
     * database default is true. Declaring it here keeps every creation path
     * — the Filament form, direct create(), imports — defaulted the same way.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'wants_portal_access' => true,
    ];

    protected function casts(): array
    {
        return [
            'main_contact' => 'boolean',
            'timesheet_contact' => 'boolean',
            'invoice_contact' => 'boolean',
            'booking_contact' => 'boolean',
            'wants_portal_access' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ClientContact $contact): void {
            if ($contact->main_contact) {
                static::where('client_id', $contact->client_id)
                    ->when($contact->exists, fn ($query) => $query->whereKeyNot($contact->getKey()))
                    ->update(['main_contact' => false]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }
}
