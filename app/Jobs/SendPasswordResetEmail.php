<?php

namespace App\Jobs;

use App\Exceptions\Mail\MicrosoftGraphThrottledException;
use App\Jobs\Concerns\ThrottlesMicrosoftGraphMail;
use App\Models\Company;
use App\Models\User;
use App\Services\Mail\EmailFooter;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The "forgot password" email, sent through the agency's own email provider
 * and from the agency's own address rather than the platform's default
 * mailer — a reset link that arrives from a stranger's domain reads as
 * phishing to the recipient, and wouldn't pass the agency's SPF/DKIM
 * alignment either. Dispatched from {@see User::sendPasswordResetNotification()},
 * which falls back to Laravel's built-in notification for any user whose
 * company has no sending address configured.
 */
class SendPasswordResetEmail implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use ThrottlesMicrosoftGraphMail;

    public int $backoff = 60;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    /**
     * A reset token expires within the hour (auth.passwords.users.expire),
     * so there's no point still retrying a send beyond that — the link
     * would be dead by the time it landed.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('auth.passwords.users.expire', 60));
    }

    public function graphMailCompany(): ?Company
    {
        return $this->user->company;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $company = $this->user->company;

        if (! $company || blank($company->defaultFromEmail())) {
            return;
        }

        try {
            $body = view('emails.password-reset', [
                'user' => $this->user,
                'company' => $company,
                'resetUrl' => $this->resetUrl(),
                'expiresInMinutes' => (int) config('auth.passwords.users.expire', 60),
            ])->render();

            $company->mailer()->send(
                to: $this->user->email,
                subject: "Reset your {$company->name} password",
                body: $body.EmailFooter::render($company, null),
                from: $company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment($company)],
            );
        } catch (MicrosoftGraphThrottledException $e) {
            $this->release($e->retryAfterSeconds);
        } catch (Throwable $e) {
            Log::error("Failed to send password reset email to {$this->user->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Mirrors the link Laravel's own ResetPassword notification builds — the
     * email query string matters beyond pre-filling the form: the reset page
     * resolves the user's company from it to show their agency's logo.
     */
    private function resetUrl(): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->getEmailForPasswordReset(),
        ]);
    }
}
