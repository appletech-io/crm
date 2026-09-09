{{--
    The referee-facing question markup, shared by the live reference form
    (⚡reference-form) and the staff-facing preview (⚡reference-form-preview)
    so the preview can never drift from what a referee actually sees.

    Both hosts are Livewire components with a public array $answers — this
    binds straight to it, which is also what makes the show_when conditionals
    ($wire.answers.*) work in the preview without a second implementation.
--}}
@props([
    'sections',
    'needsPositionAndOrganisation' => true,
    'disabled' => false,
])

@foreach ($sections as $section)
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
                            :disabled="$disabled"
                        />
                        @break

                    @case('text')
                        <flux:input wire:model="answers.{{ $key }}" :label="$field['label']" :disabled="$disabled" />
                        @break

                    @case('textarea')
                        <flux:textarea wire:model="answers.{{ $key }}" :label="$field['label']" rows="4" :disabled="$disabled" />
                        @break

                    @case('radio')
                        <flux:radio.group wire:model="answers.{{ $key }}" variant="segmented" :label="$field['label']" :disabled="$disabled">
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
        <flux:input wire:model="answers.confirm_name" :label="__('Name')" :disabled="$disabled" />

        @error('answers.confirm_name')
            <flux:error>{{ $message }}</flux:error>
        @enderror
    </div>

    @if ($needsPositionAndOrganisation)
        <div class="flex flex-col gap-2">
            <flux:input wire:model="answers.confirm_position" :label="__('Position')" :disabled="$disabled" />

            @error('answers.confirm_position')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <flux:input wire:model="answers.confirm_organisation" :label="__('School / Organisation Name')" :disabled="$disabled" />

            @error('answers.confirm_organisation')
                <flux:error>{{ $message }}</flux:error>
            @enderror
        </div>
    @endif
</div>
