<?php

namespace App\Observers;

use App\Actions\Clients\CreateClientContactPortalAccount;
use App\Models\ClientContact;
use App\Models\User;

class ClientContactObserver
{
    public function created(ClientContact $contact): void
    {
        if ($contact->wants_portal_access) {
            CreateClientContactPortalAccount::run($contact);
        }
    }

    /**
     * The portal login only ever existed to represent this contact, so it
     * has no reason to survive them being removed — deleted outright rather
     * than just unlinked, so the account can't still be logged into.
     *
     * Hooked on deleting(), not deleted(): users.client_contact_id is
     * nullOnDelete(), so by the time a deleted() observer ran the database
     * would have already nulled it out from under this query.
     */
    public function deleting(ClientContact $contact): void
    {
        User::withoutGlobalScope('company')
            ->where('client_contact_id', $contact->id)
            ->get()
            ->each->delete();
    }
}
