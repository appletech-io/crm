<?php

namespace App\Services\Concerns;

use Illuminate\Filesystem\Filesystem;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

trait ResolvesAiAttachment
{
    /**
     * Build the correct attachment type for the given file, since OpenAI
     * expects images to be sent as input_image, not input_file.
     */
    protected function attachmentFor(string $filePath): File
    {
        $mimeType = (new Filesystem)->mimeType($filePath);

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? Image::fromPath($filePath)
            : Document::fromPath($filePath);
    }
}
