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

    protected function casts(): array
    {
        return [
            'main_contact' => 'boolean',
            'timesheet_contact' => 'boolean',
            'invoice_contact' => 'boolean',
            'booking_contact' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ClientContact $contact): void {
            if ($contact->main_contact) {
                static::where('education_client_id', $contact->education_client_id)
                    ->when($contact->exists, fn ($query) => $query->whereKeyNot($contact->getKey()))
                    ->update(['main_contact' => false]);
            }
        });
    }

    public function educationClient(): BelongsTo
    {
        return $this->belongsTo(EducationClient::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }
}
