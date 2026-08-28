<?php

namespace App\Models;

use App\Enums\TimesheetFrequency;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use App\Models\Traits\HasProviderExternalId;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany;

    use HasFactory;
    use HasFieldSuggestions;
    use HasProviderExternalId;
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

    /** @return array<string, array{0: class-string<Model>, 1: array<int, string>}> */
    protected static function relationSuggestions(): array
    {
        return [
            'clientType' => [ClientType::class, []],
        ];
    }

    /** @return array<int, string> */
    protected static function toManyRelationSuggestions(): array
    {
        return ['contacts'];
    }

    /**
     * Payroll for a client is run by the agency (Company), so the timesheet
     * frequency lives there and is simply read through from here.
     */
    protected function timesheetFrequency(): Attribute
    {
        return Attribute::make(
            get: fn (): ?TimesheetFrequency => $this->company?->timesheet_frequency,
        );
    }

    protected function timesheetDayOfMonth(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->company?->timesheet_day_of_month,
        );
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    /**
     * Session key an admin's "show all clients" preference is stored under.
     * Consultants are always scoped to their own clients; admins default to
     * the same "my clients" scope but can flip this on to see every client —
     * the toggle is shared everywhere this scope is applied (clients list,
     * booking creation, etc), not just wherever it was switched on.
     */
    public const string ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY = 'admin_viewing_all_clients';

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->isAdmin() && session(self::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY, false)) {
            return $query;
        }

        return $query->where('consultant_id', $user->id);
    }

    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function mainContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('main_contact', true);
    }

    public function bookingContact(): ?ClientContact
    {
        return $this->contacts()->where('booking_contact', true)->first() ?? $this->mainContact;
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ClientActivity::class, 'model')->latest();
    }

    public function chargeRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }

    public function candidatePool(): HasOne
    {
        return $this->hasOne(CandidatePool::class);
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(ClientPool::class, 'client_pool_clients');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(MarketingCampaign::class, 'marketing_campaign_clients');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function providerErrors(): HasMany
    {
        return $this->hasMany(ProviderError::class);
    }
}
