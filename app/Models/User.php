<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $mobile
 * @property string|null $job_title
 * @property int|null $compliance_officer_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_changed_at
 * @property bool $requires_account_setup
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'mobile', 'job_title', 'password', 'password_changed_at', 'requires_account_setup', 'company_id', 'candidate_id', 'candidate_type', 'client_contact_id', 'compliance_officer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToCompany;

    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'requires_account_setup' => 'boolean',
        ];
    }

    /**
     * True once the user has changed the password an admin set for them.
     * Only meaningful for accounts a site admin created or reset — everyone
     * else is exempt regardless of this underlying timestamp.
     */
    public function mustResetPassword(): bool
    {
        return $this->requires_account_setup && $this->password_changed_at === null;
    }

    public function mustCompleteAccountSetup(): bool
    {
        return $this->mustResetPassword();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'user_industry');
    }

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function complianceOfficer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compliance_officer_id');
    }

    public function client(): ?Client
    {
        return $this->clientContact?->client;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'site_admin']);
    }

    /**
     * True when the compliance role is the user's only role. Compliance-only users
     * get a cut-down dashboard and lose access to modules (Bookings, Clients) that
     * fall outside their remit. A user with an additional role sees the standard
     * experience instead.
     */
    public function isComplianceOnly(): bool
    {
        return $this->hasRole('compliance') && $this->roles->count() === 1;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'candidate' => $this->hasRole('candidate'),
            'client' => $this->hasRole('client'),
            default => ! $this->hasRole('candidate') && ! $this->hasRole('client'),
        };
    }

    /**
     * A logged-in staff user (admin/consultant/resourcer/compliance/etc.) as
     * opposed to a candidate or client contact logging into their own panel.
     */
    public function isCrmUser(): bool
    {
        return ! $this->hasRole('candidate') && ! $this->hasRole('client');
    }
}
