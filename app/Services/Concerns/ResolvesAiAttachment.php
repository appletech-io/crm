<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

trait ResolvesAiAttachment
{
    /**
     * Build the correct attachment type for the given file, since OpenAI
     * expects images to be sent as input_image, not input_file. Reads
     * straight off the configured storage disk (S3 in production) rather
     * than a local path, since candidate documents don't live on local disk.
     */
    protected function attachmentFor(string $path): File
    {
        $disk = config('filesystems.default');
        $mimeType = Storage::disk($disk)->mimeType($path);

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? Image::fromStorage($path, $disk)
            : Document::fromStorage($path, $disk);
    }
}
