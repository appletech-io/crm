<?php

namespace App\Models;

use App\Enums\EmailProvider;
use App\Enums\Integration;
use App\Enums\TimesheetFrequency;
use App\Http\Controllers\CompanyLogoController;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use App\Services\Payroll\Contracts\PayrollTimesheetProvider;
use App\Services\Payroll\EvertimeProvider;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    /**
     * The browser-facing URL for this company's logo — served through
     * {@see CompanyLogoController} rather than a
     * direct disk URL, since the uploaded file lives on whatever
     * filesystems.default disk is configured (S3 in production), which
     * isn't necessarily publicly readable. Falls back to the platform's own
     * default logo for a company that hasn't uploaded one of their own, or
     * whose recorded logo path doesn't actually exist on disk (e.g. an
     * upload that failed partway) — checked here rather than trusting the
     * `logo` column alone, since a stale/bad path must never crash whatever
     * is trying to display it.
     */
    public function logoUrl(): string
    {
        return $this->hasStoredLogo() ? route('company.logo', $this) : asset('images/appletech.png');
    }

    /**
     * Raw image bytes, for embedding directly in emails and generated PDFs
     * (which can't rely on a request-time HTTP fetch of logoUrl()).
     */
    public function logoContents(): string
    {
        return $this->hasStoredLogo()
            ? Storage::disk(config('filesystems.default'))->get($this->logo)
            : file_get_contents(public_path('images/appletech.png'));
    }

    public function logoMimeType(): string
    {
        if (! $this->hasStoredLogo()) {
            return 'image/png';
        }

        return Storage::disk(config('filesystems.default'))->mimeType($this->logo) ?: 'image/png';
    }

    private function hasStoredLogo(): bool
    {
        return filled($this->logo) && Storage::disk(config('filesystems.default'))->exists($this->logo);
    }
}
