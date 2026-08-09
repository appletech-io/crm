<?php

namespace App\Services\Mail;

use App\Enums\EmailTemplateSender;
use App\Models\EmailTemplate;
use App\Models\User;

class EmailSenderResolver
{
    /**
     * Which user an email should appear to come from. Templates set to send
     * as the compliance officer fall back to the consultant when the
     * consultant has none assigned, and an ad-hoc email (no template) always
     * uses the consultant.
     */
    public static function resolve(?EmailTemplate $template, ?User $consultant): ?User
    {
        if ($template?->sender === EmailTemplateSender::ComplianceOfficer) {
            return $consultant?->complianceOfficer ?? $consultant;
        }

        return $consultant;
    }
}
