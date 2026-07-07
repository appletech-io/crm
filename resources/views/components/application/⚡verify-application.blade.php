<?php

use App\Models\EducationApplication;
use App\Services\ApplicationAccessSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.application')] class extends Component
{
    public string $token = '';
    public string $email = '';
    public ?EducationApplication $application = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = EducationApplication::where('token', $token)
            ->first();

        if (! $this->application) {
            abort(404);
        }

        if ($this->application->status === 'expired' || $this->application->expires_on < today()) {
            abort(403, 'This application link has expired.');
        }

        if ($this->application->status === 'completed') {
            session()->flash('toast', ['text' => __('Application Completed'), 'variant' => 'success']);
            $this->redirect(route('login'));

            return;
        }

        if (ApplicationAccessSession::hasVerified($token)) {
            $this->redirect(route('application.form', ['token' => $token]));
        }
    }

    public function verify(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        if (strtolower($this->email) !== strtolower($this->application->email)) {
            $this->addError('email', 'This email address does not match our records.');
            return;
        }

        $this->application->email_verified = true;
        $this->application->save();

        ApplicationAccessSession::markVerified($this->token);

        $this->redirect(route('application.form', ['token' => $this->token]));
    }
};

?>

<div class="mx-auto flex w-full max-w-sm flex-col gap-6">
    <x-auth-header
        :title="__('Verify Your Identity')"
        :description="__('Please enter the email address you were contacted on to access your application.')"
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
