<?php

namespace App\Console\Commands;

use App\Http\Controllers\EmailImageController;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('email:prune-adhoc-attachments')]
#[Description('Delete one-off email images/attachments once they are old enough that any send referencing them must be finished, since those are never deleted immediately (a bulk/campaign send shares one upload across many queued jobs)')]
class PruneAdhocEmailAttachments extends Command
{
    private const int MAX_AGE_HOURS = 24;

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.default'));
        $cutoff = now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();

        collect($disk->allFiles(EmailImageController::ADHOC_DIRECTORY))
            ->filter(fn (string $path): bool => $disk->lastModified($path) < $cutoff)
            ->each(fn (string $path) => $disk->delete($path));

        return self::SUCCESS;
    }
}
