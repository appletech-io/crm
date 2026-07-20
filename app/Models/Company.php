<?php

namespace App\Models;

use App\Enums\EmailProvider;
use App\Enums\TimesheetFrequency;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'timesheet_frequency' => 'weekly',
    ];

    protected function casts(): array
    {
        return [
            'email_provider' => EmailProvider::class,
            'ms_client_secret' => 'encrypted',
            'timesheet_frequency' => TimesheetFrequency::class,
        ];
    }

    public function defaultFromEmail(): ?string
    {
        return match ($this->email_provider) {
            EmailProvider::Mailgun => $this->mailgun_from_email,
            default => $this->ms_sender_email,
        };
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'company_industry');
    }
}
