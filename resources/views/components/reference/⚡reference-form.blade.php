<?php

use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Services\ReferenceAccessSession;
use App\Services\References\ReferenceFormSchema;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.application')] class extends Component
{
    public string $token = '';

    public ?CandidateReference $reference = null;

    /** @var array<string, mixed> */
    public array $answers = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->reference = CandidateReference::where('token', $token)->first();

        if (! $this->reference) {
            abort(404);
        }

        $isStaffViewer = auth()->user()?->isCrmUser() ?? false;
        $isSubmitted = $this->reference->status === ReferenceStatus::Submitted;

        if (! $isStaffViewer && ! $isSubmitted && ($this->reference->expires_on === null || $this->reference->expires_on->isPast())) {
            abort(403, 'This reference link has expired.');
        }

        if (! $isStaffViewer && ! ReferenceAccessSession::hasVerified($token)) {
            $this->redirect(route('reference.verify', ['token' => $token]));

            return;
        }

        $this->answers = $this->reference->answers ?? [];
    }

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    #[Computed]
    public function sections(): array
    {
        return ReferenceFormSchema::sectionsFor($this->reference->type);
    }

    #[Computed]
    public function needsPositionAndOrganisation(): bool
    {
        return ReferenceFormSchema::needsPositionAndOrganisation($this->reference->type);
    }

    #[Computed]
    public function isSubmitted(): bool
    {
        return $this->reference->status === ReferenceStatus::Submitted;
    }

    #[Computed]
    public function isStaffViewer(): bool
    {
        return auth()->user()?->isCrmUser() ?? false;
    }

    /**
     * Staff never fill this form in on the referee's behalf — they can only
     * look at it, whether or not the referee has submitted yet.
     */
    #[Computed]
    public function isReadOnly(): bool
    {
        return $this->isSubmitted || $this->isStaffViewer;
    }

    #[Computed]
    public function candidateName(): string
    {
        return trim("{$this->reference->candidate->first_name} {$this->reference->candidate->last_name}");
    }

    public function submit(): void
    {
        if ($this->isReadOnly) {
            return;
        }

        $rules = ReferenceFormSchema::rulesFor($this->reference->type);
        $attributes = ReferenceFormSchema::attributeNamesFor($this->reference->type);

        $rules['answers.confirm_name'] = ['required', 'string', 'max:255'];
        $attributes['answers.confirm_name'] = 'name';

        if ($this->needsPositionAndOrganisation) {
            $rules['answers.confirm_position'] = ['required', 'string', 'max:255'];
            $rules['answers.confirm_organisation'] = ['required', 'string', 'max:255'];
            $attributes['answers.confirm_position'] = 'position';
            $attributes['answers.confirm_organisation'] = 'school / organisation name';
        }

        $this->validate($rules, attributes: $attributes);

        $this->reference->update([
            'answers' => $this->answers,
            'status' => ReferenceStatus::Submitted,
            'submitted_at' => now(),
        ]);

        Notification::make()
            ->title('Reference submitted')
            ->body('Thank you — this reference has been submitted.')
            ->success()
            ->send();
    }
};

?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <x-auth-header
        :title="__(':type Reference', ['type' => $reference->type->label()])"
        :description="__('For :candidate', ['candidate' => $this->candidateName])"
    />

    @if ($this->isSubmitted)
        <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ __('This reference was submitted on :date. It can no longer be edited.', ['date' => $reference->submitted_at->format('d M Y')]) }}
        </div>
    @elseif ($this->isStaffViewer)
        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
            {{ __('This reference has not been submitted by the referee yet.') }}
        </div>
    @endif

    <form wire:submit="submit" class="flex flex-col gap-6" @if ($this->isReadOnly) inert @endif>
        @foreach ($this->sections as $section)
            <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                @if ($section['heading'])
                    <flux:heading size="lg">{{ $section['heading'] }}</flux:heading>
                @endif

                @foreach ($section['fields'] as $field)
                    @php($key = $field['key'])
                    <div
                        @if ($field['show_when'] ?? null)
                            x-show="$wire.answers.{{ $field['show_when'][0] }} === '{{ $field['show_when'][1] }}'"
                        @endif
                        class="flex flex-col gap-2"
                    >
                        @switch($field['type'])
                            @case('date')
                                <flux:input
                                    type="date"
                                    wire:model="answers.{{ $key }}"
                                    :label="$field['label']"
                                    max="{{ now()->format('Y-m-d') }}"
                                    :disabled="$this->isReadOnly"
                                />
                                @break

                            @case('text')
                                <flux:input wire:model="answers.{{ $key }}" :label="$field['label']" :disabled="$this->isReadOnly" />
                                @break

                            @case('textarea')
                                <flux:textarea wire:model="answers.{{ $key }}" :label="$field['label']" rows="4" :disabled="$this->isReadOnly" />
                                @break

                            @case('radio')
                                <flux:radio.group wire:model="answers.{{ $key }}" variant="segmented" :label="$field['label']" :disabled="$this->isReadOnly">
                                    @foreach ($field['options'] as $value => $optionLabel)
                                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                                    @endforeach
                                </flux:radio.group>
                                @break
                        @endswitch

                        @error("answers.{$key}")
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
            <flux:heading size="lg">{{ __('Please Confirm') }}</flux:heading>

            <div class="flex flex-col gap-2">
                <flux:input wire:model="answers.confirm_name" :label="__('Name')" :disabled="$this->isReadOnly" />

                @error('answers.confirm_name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            @if ($this->needsPositionAndOrganisation)
                <div class="flex flex-col gap-2">
                    <flux:input wire:model="answers.confirm_position" :label="__('Position')" :disabled="$this->isReadOnly" />

                    @error('answers.confirm_position')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <flux:input wire:model="answers.confirm_organisation" :label="__('School / Organisation Name')" :disabled="$this->isReadOnly" />

                    @error('answers.confirm_organisation')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @endif
        </div>

        @unless ($this->isReadOnly)
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Submit Reference') }}
            </flux:button>
        @endunless
    </form>
</div>
