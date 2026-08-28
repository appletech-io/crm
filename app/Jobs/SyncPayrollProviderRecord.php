<?php

namespace App\Jobs;

use App\Enums\Integration;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\ProviderError;
use App\Models\User;
use App\Services\Payroll\Exceptions\PayrollProviderException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps a Client, Candidate, or consultant (User) in sync with the
 * company's payroll provider (e.g. Evertime) independently of any booking —
 * dispatched from ClientObserver/EducationCandidateObserver/
 * HealthcareCandidateObserver/CandidateObserver/UserObserver on every save,
 * so a record doesn't have to wait for a booking to be approved before its
 * details reach the provider.
 *
 * This deliberately duplicates SendTimesheetToPayrollProvider's error
 * classification (RequestException/PayrollProviderException/Throwable)
 * rather than sharing a trait with it — that job is an already-tested,
 * production-critical path, and this small, stable block of logic isn't
 * worth the risk of refactoring it to share code.
 */
class SyncPayrollProviderRecord implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Client|EducationCandidate|HealthcareCandidate|Candidate|User $record,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // A candidate/client can exist without a company in a handful of
        // edge cases (e.g. test fixtures, or a record mid-import) — nothing
        // to sync against without one.
        if (! $this->record->company) {
            return;
        }

        $provider = $this->record->company->payrollProvider();

        if (! $provider) {
            return;
        }

        /** @var Integration $providerEnum */
        $providerEnum = $this->record->company->payroll_provider;

        try {
            if ($this->record instanceof Client) {
                $provider->upsertClient($this->record);
            } elseif ($this->record instanceof User) {
                $provider->upsertConsultant($this->record);
            } else {
                if ($this->record->payment_provider_id && $this->record->paymentProvider) {
                    $provider->upsertPaymentProvider($this->record->paymentProvider);
                }

                $provider->upsertCandidate($this->record);
            }

            $this->providerErrorQuery($providerEnum)->delete();
        } catch (RequestException $e) {
            $this->recordFailure($providerEnum, $this->errorsFrom($e));

            Log::error("Failed to sync {$this->recordLabel()} to payroll provider: {$e->getMessage()}\nFull response: {$e->response->body()}");

            // A 4xx here is the provider rejecting the data we sent — retrying
            // the identical payload three times won't fix it.
            if ($e->response->clientError()) {
                $this->fail($e);

                return;
            }

            throw $e;
        } catch (PayrollProviderException $e) {
            $this->recordFailure($providerEnum, $e->errors);

            Log::error("Failed to sync {$this->recordLabel()} to payroll provider: {$e->getMessage()}");

            $this->fail($e);
        } catch (Throwable $e) {
            $this->recordFailure($providerEnum, [$e->getMessage()]);

            Log::error("Failed to sync {$this->recordLabel()} to payroll provider: {$e->getMessage()}");

            throw $e;
        }
    }

    /** @return array<int, string> */
    private function errorsFrom(RequestException $e): array
    {
        $errors = collect($e->response->json('Errors', []))
            ->pluck('ErrorMessage')
            ->filter()
            ->values()
            ->all();

        return $errors ?: [$e->getMessage()];
    }

    /** @param  array<int, string>  $errors */
    private function recordFailure(Integration $provider, array $errors): void
    {
        ProviderError::updateOrCreate(
            [...$this->subjectAttributes(), 'provider' => $provider->value],
            ['company_id' => $this->record->company_id, 'errors' => $errors],
        );
    }

    private function providerErrorQuery(Integration $provider): Builder
    {
        return ProviderError::where($this->subjectAttributes())->where('provider', $provider->value);
    }

    /** @return array<string, mixed> */
    private function subjectAttributes(): array
    {
        return match (true) {
            $this->record instanceof Client => ['client_id' => $this->record->id],
            $this->record instanceof User => ['user_id' => $this->record->id],
            default => ['candidate_type' => $this->record::class, 'candidate_id' => $this->record->id],
        };
    }

    private function recordLabel(): string
    {
        return match (true) {
            $this->record instanceof Client => "client {$this->record->id}",
            $this->record instanceof User => "consultant {$this->record->id}",
            default => 'candidate '.$this->record->id,
        };
    }
}
