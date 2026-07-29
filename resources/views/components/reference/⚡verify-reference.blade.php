<?php

use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Services\ReferenceAccessSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.application')] class extends Component
{
    public string $token = '';

    public string $email = '';

    public ?CandidateReference $reference = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->reference = CandidateReference::where('token', $token)->first();

        if (! $this->reference) {
            abort(404);
        }

        // A logged-in staff user is viewing this to see the referee's answers,
        // not proving they are the referee — skip the email-match challenge
        // (and the expiry check, which only protects the referee's own window
        // to respond) entirely.
        if (auth()->user()?->isCrmUser()) {
            ReferenceAccessSession::markVerified($token);
            $this->redirect(route('reference.form', ['token' => $token]));

            return;
        }

        $isSubmitted = $this->reference->status === ReferenceStatus::Submitted;

        if (! $isSubmitted && ($this->reference->expires_on === null || $this->reference->expires_on->isPast())) {
            abort(403, 'This reference link has expired.');
        }

        if (ReferenceAccessSession::hasVerified($token)) {
            $this->redirect(route('reference.form', ['token' => $token]));
        }
    }

    public function verify(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        if (blank($this->reference->email) || strtolower($this->email) !== strtolower($this->reference->email)) {
            $this->addError('email', 'This email address does not match our records.');

            return;
        }

        ReferenceAccessSession::markVerified($this->token);

        $this->redirect(route('reference.form', ['token' => $this->token]));
    }
};

?>

<div class="mx-auto flex w-full max-w-sm flex-col gap-6">
    <x-auth-header
        :title="__('Verify Your Identity')"
        :description="__('Please enter the email address this reference request was sent to.')"
    />

    <form wire:submit="verify" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            type="email"
            :label="__('Email Address')"
            placeholder="email@example.com"
            required
            autofocus
        />

        @error('email')
        <flux:error>{{ $message }}</flux:error>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Continue') }}
        </flux:button>
    </form>
</div>
