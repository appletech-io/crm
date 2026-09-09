<?php

namespace App\Services\References;

use App\Models\ReferenceForm;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Carries the reference form builder's unsaved state across to the preview
 * pane rendered beside it.
 *
 * The pane is an iframe of the real referee-facing page — the only way to
 * show a staff member what a referee actually sees, since the Flux
 * components that page is built from don't have their styles available
 * inside the Filament panel. That puts the preview in a separate document
 * with no access to the builder's Livewire state, so the builder parks each
 * render's state here and the preview reads it back out.
 *
 * Scoped to one user and one form, and deliberately short-lived: this is a
 * transient view of something being typed, never a recoverable draft. If it
 * expires the preview simply falls back to the saved form.
 */
class ReferenceFormDraft
{
    private const int TTL_MINUTES = 30;

    /** @param array<string, mixed> $state */
    public static function store(User $user, ReferenceForm $form, array $state): void
    {
        Cache::put(self::key($user, $form), $state, now()->addMinutes(self::TTL_MINUTES));
    }

    /** @return array<string, mixed>|null */
    public static function retrieve(User $user, ReferenceForm $form): ?array
    {
        $state = Cache::get(self::key($user, $form));

        return is_array($state) ? $state : null;
    }

    private static function key(User $user, ReferenceForm $form): string
    {
        return "reference-form-draft.{$user->getKey()}.{$form->getKey()}";
    }
}
