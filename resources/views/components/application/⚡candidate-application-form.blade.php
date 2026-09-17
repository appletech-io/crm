<?php

use App\Models\CandidateApplication;
use App\Models\User;
use App\Services\ApplicationAccessSession;
use App\Services\Candidates\CandidateDocumentRequirements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Unlike Education/Healthcare, a generic Candidate is already fully profiled
 * by staff at creation time (see CandidateForm) — there's no vetting data to
 * re-collect here. This step only exists to let the candidate prove their
 * identity and choose their own password before landing in the portal,
 * where the Documents/Compliance/Availability pages (already resolved from
 * their job title — see CandidateDocumentRequirements, ComplianceRequirements)
 * take over. The preview list below is read-only, purely so they know what
 * to expect before signing in.
 */
new #[Layout('layouts.application')] class extends Component
{
    public string $token = '';

    public ?CandidateApplication $application = null;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->application = CandidateApplication::where('token', $token)->first();

        if (! $this->application) {
            abort(404);
        }

        if (! ApplicationAccessSession::hasVerified($token)) {
            $this->redirect(route('application.candidate.verify', ['token' => $token]));

            return;
        }
    }

    /** @return array<int, array{label: string, description: string}> */
    public function getRequirementsProperty(): array
    {
        return collect(CandidateDocumentRequirements::for($this->application->candidate, includeGetDbsAction: false))
            ->map(fn (array $row): array => ['label' => $row['label'], 'description' => $row['description']])
            ->values()
            ->all();
    }

    public function completeApplication(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $candidate = $this->application->candidate;

        $this->application->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $user = User::updateOrCreate(
            ['email' => $candidate->email],
            [
                'name' => trim("{$candidate->first_name} {$candidate->last_name}"),
                'password' => $this->password,
                'company_id' => $candidate->company_id,
                'candidate_id' => $candidate->id,
                'candidate_type' => $candidate::class,
            ]
        );

        if ($candidate->industry_id) {
            $user->industries()->syncWithoutDetaching([$candidate->industry_id]);
        }

        $user->assignRole('candidate');

        Auth::login($user);

        $this->redirect('/candidate');
    }
};

?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <x-auth-header
        :title="__('Welcome, :name', ['name' => $application->candidate->first_name])"
        :description="__('Choose a password to finish setting up your candidate portal.')"
    />

    @if (! empty($this->requirements))
        <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <span class="font-medium">{{ __("Once you're in, you'll be asked to provide:") }}</span>
            <ul class="list-disc space-y-1 pl-5 text-zinc-600 dark:text-zinc-400">
                @foreach ($this->requirements as $requirement)
                    <li>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $requirement['label'] }}</span>
                        @if ($requirement['description'])
                            — {{ $requirement['description'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="completeApplication" class="flex flex-col gap-4">
        <flux:input type="password" wire:model="password" label="Password" required />
        <flux:input type="password" wire:model="password_confirmation" label="Confirm Password" required />
        @error('password') <flux:error>{{ $message }}</flux:error> @enderror
        <flux:button type="submit" variant="primary" class="w-full">{{ __('Go to your portal') }}</flux:button>
    </form>
</div>
