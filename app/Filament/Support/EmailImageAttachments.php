<?php

namespace App\Filament\Support;

use App\Http\Controllers\EmailImageController;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Wires a RichEditor field up to store pasted/dropped images in the app's
 * default (private) disk under a dedicated directory, and to reference them
 * via a signed, unauthenticated route rather than a raw storage URL —
 * needed because the default disk (S3) correctly rejects public reads, but
 * an email's embedded images must still render for an external recipient
 * with no session. See EmailImageController for the other half of this.
 */
class EmailImageAttachments
{
    public static function configure(RichEditor $richEditor): RichEditor
    {
        return $richEditor
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file): string => $file->store(
                EmailImageController::DIRECTORY,
                config('filesystems.default'),
            ))
            ->getFileAttachmentUrlUsing(fn (string $file): string => URL::signedRoute(
                'email-images.show',
                ['path' => $file],
            ));
    }
}
