<?php

namespace App\Jobs;

use App\Services\Candidates\CandidateProfileGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCandidateProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Model $candidate,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CandidateProfileGenerator $generator): void
    {
        try {
            $generator->generate($this->candidate);
        } catch (Throwable $e) {
            Log::error("Failed to generate candidate profile for {$this->candidate->getMorphClass()} {$this->candidate->id}: {$e->getMessage()}");

            throw $e;
        }
    }
}
