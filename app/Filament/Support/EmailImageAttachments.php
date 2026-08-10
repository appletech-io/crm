<?php

namespace App\Filament\Support;

use App\Http\Controllers\EmailImageController;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
    /**
     * $directory defaults to the permanent, reused-forever template
     * directory. Pass EmailImageController::ADHOC_DIRECTORY for a one-off
     * compose body whose images should be cleaned up after sending instead.
     */
    public static function configure(RichEditor $richEditor, string $directory = EmailImageController::DIRECTORY): RichEditor
    {
        return $richEditor
            ->saveUploadedFileAttachmentUsing(function (TemporaryUploadedFile $file) use ($directory): string {
                $path = $directory.'/'.Str::random(40).'.'.$file->getClientOriginalExtension();

                // A plain content write rather than $file->store()/storeAs() —
                // those move/copy from the temporary upload disk, and since
                // that's the same S3 disk as the target here, Laravel's move()
                // issues a server-side CopyObject that requires s3:GetObjectAcl,
                // a permission this app's IAM user isn't granted (silently
                // failing under this disk's 'throw' => false, leaving the file
                // stranded in livewire-tmp/ until its 24h cleanup). See
                // App\Services\Candidates\Document::upload() for the same fix.
                Storage::disk(config('filesystems.default'))->writeStream($path, $file->readStream());

                $file->delete();

                return $path;
            })
            ->getFileAttachmentUrlUsing(function (string $file): ?string {
                if (! Storage::disk(config('filesystems.default'))->exists($file)) {
                    return null;
                }

                return URL::signedRoute('email-images.show', ['path' => $file]);
            });
    }
}
