<?php

namespace App\Services\Mail;

use App\Enums\EmailTemplateSender;
use App\Models\EmailTemplate;
use App\Models\User;

class EmailSenderResolver
{
    /**
     * Which user an email should appear to come from.
     *
     * $sentBy — the logged-in user actively sending this email right now (a
     * "Send Email" click), as opposed to a system-triggered/automated send
     * with no one at the keyboard — always wins when present: you're
     * sending it, so it comes from you, not the candidate/client's assigned
     * consultant. Only automated sends (no $sentBy) fall through to the
     * template's configured sender: the compliance officer when set (or the
     * consultant if none is assigned), otherwise the consultant.
     */
    public static function resolve(?EmailTemplate $template, ?User $consultant, ?User $sentBy = null): ?User
    {
        if ($sentBy) {
            return $sentBy;
        }

        if ($template?->sender === EmailTemplateSender::ComplianceOfficer) {
            return $consultant?->complianceOfficer ?? $consultant;
        }

        return $consultant;
    }
}
