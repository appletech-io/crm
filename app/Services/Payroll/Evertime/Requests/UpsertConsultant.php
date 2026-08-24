<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\User;
use App\Services\Payroll\Evertime\EvertimeClient;
use Illuminate\Support\Str;

/**
 * Registers the booking's consultant against the provider so a placement
 * can be attributed to them instead of falling back to whatever "default"
 * consultant Evertime assigns when none is set.
 *
 * POST /consultants
 */
class UpsertConsultant
{
    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(User $consultant, string $consultantId): void
    {
        // This app only stores a single 'name' field for a user — split it
        // on the first space since Forenames/Surname are supplied together
        // in Evertime's own minimum-payload example.
        $forenames = Str::before($consultant->name, ' ');
        $surname = Str::contains($consultant->name, ' ') ? Str::after($consultant->name, ' ') : $consultant->name;

        $this->client->post('/consultants', [
            'Consultants' => [[
                'ConsultantId' => $consultantId,
                'CommunicationMethod' => 'Email',
                // Docs mark this optional, but the live account rejects
                // consultant creation ("Branch not specified.") without it.
                // Confirmed via GET /agency that this account has exactly
                // one configured branch, DisplayBranchId "DEFAULT".
                'Branch' => 'DEFAULT',
                'Forenames' => $forenames,
                'Surname' => $surname,
                'EmailAddress' => $consultant->email,
            ]],
        ]);
    }
}
