<?php

namespace App\Jobs;

use App\Models\EducationApplication;
use App\Services\CvParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseCvJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(public EducationApplication $application) {}

    public function handle(CvParserService $service): void
    {
        try {
            $extracted = $service->parse(
                Storage::path($this->application->cv_temp_path)
            );

            $this->application->update([
                'cv_parsed_data' => (array) $extracted,
            ]);
        } catch (Throwable $e) {

            throw $e;
        } finally {
            Storage::delete($this->application->cv_temp_path);
        }
    }
}
