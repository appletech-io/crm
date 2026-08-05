<?php

namespace App\Services\Candidates;

use App\Http\Controllers\CandidateDocumentController;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Document
{
    /**
     * Builds a "view" link that resolves the actual signed storage URL at click-time via
     * {@see CandidateDocumentController}, rather than baking a temporary URL into the
     * page's HTML where it can expire before it's clicked.
     */
    public static function viewUrl(string $path): string
    {
        return route('documents.view', ['path' => Crypt::encryptString($path)]);
    }

    /**
     * Streams the file's contents into place with a plain write rather than
     * using Storage::move()/copy(), which for the S3 driver issues a
     * server-side CopyObject that first reads the source object's ACL —
     * requiring s3:GetObjectAcl, a permission this app's IAM user isn't
     * granted. A content-based write only needs PutObject/DeleteObject.
     */
    public static function upload(TemporaryUploadedFile $file, Model $candidate, ?string $prefix = null): string
    {
        $path = self::directoryFor($candidate).'/'.self::prefixedFilename($file->getClientOriginalName(), $prefix);

        Storage::disk(config('filesystems.default'))->writeStream($path, $file->readStream());

        $file->delete();

        return $path;
    }

    public static function move(string $existingPath, Model $candidate, ?string $prefix = null, string $disk = 'local'): string
    {
        $newPath = self::directoryFor($candidate).'/'.self::prefixedFilename(basename($existingPath), $prefix);

        $storage = Storage::disk($disk);
        $storage->writeStream($newPath, $storage->readStream($existingPath));
        $storage->delete($existingPath);

        return $newPath;
    }

    public static function putGenerated(string $contents, Model $candidate, string $filename, string $subdirectory): string
    {
        $path = self::directoryFor($candidate)."/{$subdirectory}/{$filename}";

        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private static function directoryFor(Model $candidate): string
    {
        $companyName = Str::slug($candidate->company?->name) ?: $candidate->company_id;
        $industryName = Str::slug(self::industryNameFor($candidate)) ?: 'unknown-industry';

        return "{$companyName}/{$industryName}/{$candidate->id}";
    }

    private static function prefixedFilename(string $filename, ?string $prefix): string
    {
        return $prefix ? "{$prefix}-{$filename}" : $filename;
    }

    private static function industryNameFor(Model $candidate): ?string
    {
        $slug = Industry::slugForCandidateModel($candidate::class);

        return $slug ? Industry::where('slug', $slug)->value('name') : null;
    }
}
