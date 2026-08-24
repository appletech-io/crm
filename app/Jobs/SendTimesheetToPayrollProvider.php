<?php

namespace App\Jobs;

use App\Enums\Integration;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\ProviderError;
use App\Services\Booking\TimesheetPeriod;
use App\Services\Payroll\Exceptions\PayrollProviderException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTimesheetToPayrollProvider implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $provider = $this->booking->company->payrollProvider();

        if (! $provider) {
            return;
        }

        $days = $this->booking->dayPeriods()
            ->whereNotNull('approved_at')
            ->whereNull('disputed_at')
            ->whereNull('sent_to_provider_at')
            ->get();

        if ($days->isEmpty()) {
            return;
        }

        /** @var Integration $providerEnum */
        $providerEnum = $this->booking->company->payroll_provider;

        try {
            $provider->upsertClient($this->booking->client);

            $candidate = $this->booking->candidate;

            if ($candidate?->payment_provider_id && $candidate->paymentProvider) {
                $provider->upsertPaymentProvider($candidate->paymentProvider);
            }

            $provider->upsertCandidate($candidate);

            if ($this->booking->consultant) {
                $provider->upsertConsultant($this->booking->consultant);
            }

            $provider->upsertPlacement($this->booking);

            // Payroll runs per period (weekly/biweekly/monthly, per the
            // company's TimesheetFrequency — the same boundaries RunPayroll
            // and the client confirmation emails already use), so a
            // multi-period booking must submit one timesheet per period,
            // each with that period's actual cutoff date. Grouping by
            // whatever happens to be unsent at the moment this job runs
            // would otherwise merge days from different periods into one
            // timesheet with an arbitrary "PeriodEndDate".
            $days->groupBy(fn (BookingDay $day) => TimesheetPeriod::containing($this->booking->company, $day->date)['end']->toDateString())
                ->each(function ($periodDays) use ($provider): void {
                    $provider->submitTimesheet($this->booking, $periodDays);

                    $periodDays->each->update(['sent_to_provider_at' => now()]);
                });

            ProviderError::where('booking_id', $this->booking->id)
                ->where('provider', $providerEnum->value)
                ->delete();
        } catch (RequestException $e) {
            $this->recordFailure($providerEnum, $this->errorsFrom($e));

            // RequestException::getMessage() truncates the response body to
            // 120 characters by default, which isn't enough to see which
            // field a validation error is actually about — log the full
            // body separately so failures are actually diagnosable.
            Log::error("Failed to send timesheet for booking {$this->booking->id} to payroll provider: {$e->getMessage()}\nFull response: {$e->response->body()}");

            // A 4xx here is Evertime rejecting the data we sent (bad/missing
            // field) — retrying the identical payload three times won't fix
            // it, it'll just repeat the same failure. Fail immediately so
            // it's surfaced once, not silently retried and buried in the log.
            if ($e->response->clientError()) {
                $this->fail($e);

                return;
            }

            throw $e;
        } catch (PayrollProviderException $e) {
            $this->recordFailure($providerEnum, $e->errors);

            Log::error("Failed to send timesheet for booking {$this->booking->id} to payroll provider: {$e->getMessage()}");

            // Same reasoning as the 4xx case above — this is Evertime
            // returning HasErrors:true with an HTTP 200, so it's still a
            // rejected payload rather than a transient failure.
            $this->fail($e);
        } catch (Throwable $e) {
            $this->recordFailure($providerEnum, [$e->getMessage()]);

            Log::error("Failed to send timesheet for booking {$this->booking->id} to payroll provider: {$e->getMessage()}");

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
            ['booking_id' => $this->booking->id, 'provider' => $provider->value],
            ['company_id' => $this->booking->company_id, 'errors' => $errors],
        );
    }
}
