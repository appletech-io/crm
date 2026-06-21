<?php

use App\Models\EducationApplication;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public string $token = '';
    public ?EducationApplication $application = null;

    public string $full_name = '';
    public string $date_of_birth = '';
    public string $address_line_1 = '';
    public string $address_line_2 = '';
    public string $city = '';
    public string $postcode = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = EducationApplication::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $this->application) {
            abort(404);
        }

        if ($this->application->status === 'expired' || $this->application->expires_on < today()) {
            abort(403, 'This application link has expired.');
        }

        if (! $this->application->email_verified) {
            $this->redirect(route('application.verify', ['token' => $token]));
        }
    }

    public function saveStep(): void
    {
        $this->validate([
            'full_name'      => ['required', 'string', 'max:255'],
            'date_of_birth'  => ['required', 'date', 'before:today'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:255'],
            'postcode'       => ['required', 'string', 'max:10'],
        ]);

        $this->application->educationCandidate->update([
            'full_name'      => $this->full_name,
            'date_of_birth'  => $this->date_of_birth,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city'           => $this->city,
            'postcode'       => $this->postcode,
        ]);
    }
};

?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Your Application')"
        :description="__('Please fill in your personal details below.')"
    />

    <form wire:submit="saveStep" class="flex flex-col gap-6">

        <flux:input
            wire:model="full_name"
            :label="__('Full Name')"
            placeholder="John Smith"
            required
        />

        <flux:input
            wire:model="date_of_birth"
            type="date"
            :label="__('Date of Birth')"
            required
        />

        <flux:input
            wire:model="address_line_1"
            :label="__('Address Line 1')"
            placeholder="123 Example Street"
            required
        />

        <flux:input
            wire:model="address_line_2"
            :label="__('Address Line 2')"
            placeholder="Apartment, suite, etc. (optional)"
        />

        <flux:input
            wire:model="city"
            :label="__('City')"
            placeholder="London"
            required
        />

        <flux:input
            wire:model="postcode"
            :label="__('Postcode')"
            placeholder="SW1A 1AA"
            required
        />

        @foreach(['full_name', 'date_of_birth', 'address_line_1', 'city', 'postcode'] as $field)
            @error($field)
            <flux:error>{{ $message }}</flux:error>
            @enderror
        @endforeach

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Save & Continue') }}
        </flux:button>

    </form>
</div>
