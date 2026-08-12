<?php

namespace App\Actions\Clients;

use App\Jobs\SendClientPortalWelcomeEmail;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class CreateClientContactPortalAccount
{
    use AsAction;

    /**
     * The wants_portal_access toggle is checked by the caller, not here —
     * ClientContactObserver::created() gates the automatic path on it, while
     * the "Create User Account" button on an already-saved contact (which
     * the toggle no longer does anything for, since it only fires on
     * creation) calls this directly regardless of that flag.
     *
     * Wrapped in a try/catch so a duplicate email or any other failure here
     * never rolls back the client contact itself — this runs inline from an
     * observer inside the same save as the rest of the client form.
     */
    public function handle(ClientContact $contact): ?User
    {
        if (blank($contact->email)) {
            return null;
        }

        try {
            $alreadyHasAccount = User::withoutGlobalScope('company')
                ->where('client_contact_id', $contact->id)
                ->orWhere('email', $contact->email)
                ->exists();

            if ($alreadyHasAccount) {
                return null;
            }

            $password = Str::password(16);

            $user = User::create([
                'name' => trim("{$contact->first_name} {$contact->last_name}"),
                'email' => $contact->email,
                'password' => $password,
                'company_id' => $contact->company_id,
                'client_contact_id' => $contact->id,
                'requires_account_setup' => true,
            ]);

            $user->assignRole('client');

            SendClientPortalWelcomeEmail::dispatch($contact, $password);

            return $user;
        } catch (Throwable $e) {
            Log::error("Failed to create a portal account for client contact {$contact->id}: {$e->getMessage()}");

            return null;
        }
    }
}
