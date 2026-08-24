<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasProviderExternalId;
use Database\Factories\PaymentProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<PaymentProviderFactory> */
    use HasFactory;

    use HasProviderExternalId;

    protected $guarded = [];
}
