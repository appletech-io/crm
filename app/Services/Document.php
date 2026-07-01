<?php

namespace App\Services;

use App\Models\EducationApplication;
use App\Models\Industry;
use Illuminate\Http\UploadedFile;

class Document
{
    public static function upload(UploadedFile $file, EducationApplication $application): string
    {
        $candidate = $application->educationCandidate;
        $industryId = Industry::where('slug', 'education')->value('id');

        return $file->storeAs(
            "{$candidate->company_id}/{$industryId}/{$candidate->id}",
            $file->getClientOriginalName(),
        );
    }
}
