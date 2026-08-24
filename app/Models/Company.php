<?php

namespace App\Models;

use App\Enums\EmailProvider;
use App\Enums\Integration;
use App\Enums\TimesheetFrequency;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use App\Services\Payroll\Contracts\PayrollTimesheetProvider;
use App\Services\Payroll\EvertimeProvider;
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
            'payroll_provider' => Integration::class,
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

    public function mailer(): MailgunMailer|MicrosoftGraphMailer
    {
        return match ($this->email_provider) {
            EmailProvider::Mailgun => new MailgunMailer,
            default => new MicrosoftGraphMailer($this),
        };
    }

    public function payrollProvider(): ?PayrollTimesheetProvider
    {
        return match ($this->payroll_provider) {
            Integration::Evertime => new EvertimeProvider($this),
            default => null,
        };
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function integrationSettings(): HasMany
    {
        return $this->hasMany(IntegrationSetting::class);
    }

    /**
     * Generic key/value config storage per provider (e.g. Evertime's
     * 'api_url'/'api_key', or a future provider's own set of keys) — each
     * provider implementation knows which keys it needs and reads them via
     * this accessor, rather than the company having fixed columns per
     * provider.
     */
    public function integrationSetting(Integration $provider, string $key): ?string
    {
        return $this->integrationSettings()
            ->where('provider', $provider->value)
            ->where('key', $key)
            ->first()
            ?->value;
    }

    public function setIntegrationSetting(Integration $provider, string $key, ?string $value): void
    {
        $this->integrationSettings()->updateOrCreate(
            ['provider' => $provider->value, 'key' => $key],
            ['value' => $value],
        );
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
