<?php

namespace App\Services\Education;

use App\Models\EducationCandidate;
use App\Models\Industry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Document
{
    public static function upload(UploadedFile $file, EducationCandidate $candidate, ?string $prefix = null): string
    {
        return $file->storeAs(
            self::directoryFor($candidate),
            self::prefixedFilename($file->getClientOriginalName(), $prefix),
        );
    }

    public static function move(string $existingPath, EducationCandidate $candidate, ?string $prefix = null, string $disk = 'local'): string
    {
        $newPath = self::directoryFor($candidate).'/'.self::prefixedFilename(basename($existingPath), $prefix);

        Storage::disk($disk)->move($existingPath, $newPath);

        return $newPath;
    }

    public static function putGenerated(string $contents, EducationCandidate $candidate, string $filename, string $subdirectory): string
    {
        $path = self::directoryFor($candidate)."/{$subdirectory}/{$filename}";

        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private static function directoryFor(EducationCandidate $candidate): string
    {
        $companyName = Str::slug($candidate->company?->name) ?: $candidate->company_id;
        $industryName = Str::slug(self::industryNameFor($candidate)) ?: 'unknown-industry';

        return "{$companyName}/{$industryName}/{$candidate->id}";
    }

    private static function prefixedFilename(string $filename, ?string $prefix): string
    {
        return $prefix ? "{$prefix}-{$filename}" : $filename;
    }

    private static function industryNameFor(EducationCandidate $candidate): ?string
    {
        $slug = Industry::slugForCandidateModel($candidate::class);

        return $slug ? Industry::where('slug', $slug)->value('name') : null;
    }
}
