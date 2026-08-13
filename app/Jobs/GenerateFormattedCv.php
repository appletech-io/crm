<?php

namespace App\Jobs;

use App\Models\CandidateDocument;
use App\Services\Candidates\FormattedCvGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFormattedCv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Model $candidate,
        public readonly CandidateDocument $cvDocument,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(FormattedCvGenerator $generator): void
    {
        try {
            $generator->generate($this->candidate, $this->cvDocument);
        } catch (Throwable $e) {
            Log::error("Failed to generate formatted CV for {$this->candidate->getMorphClass()} {$this->candidate->id}: {$e->getMessage()}");

            throw $e;
        }
    }
}
