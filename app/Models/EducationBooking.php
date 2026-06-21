<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\EducationBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationBooking extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<EducationBookingFactory> */
    use HasFactory;
    use SoftDeletes;

    public function education_client(): BelongsTo
    {
        return $this->belongsTo(EducationClient::class);
    }

    public function education_candidate(): BelongsTo
    {
        return $this->belongsTo(EducationCandidate::class);
    }
}
