<?php

namespace App\Filament\Support;

use App\Http\Controllers\EmailImageController;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Wires a FileUpload field up to store attachments for a single one-off
 * email send in the app's default (private) disk, under the same ad-hoc
 * directory as EmailImageAttachments' ephemeral images — cleaned up once
 * the email that references them has sent (see SendCustomTemplateEmail and
 * PruneAdhocEmailAttachments). The original filename is preserved (nested
 * under a random directory rather than randomizing the filename itself)
 * since the recipient sees it as the attachment's name.
 */
class EmailAttachmentUpload
{
    public static function configure(FileUpload $fileUpload): FileUpload
    {
        return $fileUpload
            ->disk(config('filesystems.default'))
            ->directory(EmailImageController::ADHOC_DIRECTORY)
            ->visibility('private')
            ->multiple()
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                $path = EmailImageController::ADHOC_DIRECTORY.'/'.Str::random(40).'/'.$file->getClientOriginalName();

                // Same reasoning as EmailImageAttachments: a plain content
                // write rather than $file->store(), which would try to
                // move/copy across the same S3 disk and fail on the missing
                // s3:GetObjectAcl permission.
                Storage::disk(config('filesystems.default'))->writeStream($path, $file->readStream());

                $file->delete();

                return $path;
            });
    }
}
