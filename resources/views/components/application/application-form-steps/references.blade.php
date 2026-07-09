<x-auth-header
    :title="__('References')"
    :description="__('Add references covering the last 3 years of your work or education history, with no gaps.')"
/>

<form wire:submit="submitApplication" class="mt-3 flex flex-col gap-6">

    @unless ($this->referenceCoverage['is_complete'])
        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
            {{ $this->referenceCoverage['summary'] }}
        </div>
    @endunless

    @foreach ($references as $index => $reference)
        <div wire:key="reference-{{ $index }}" class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                    {{ __('Reference :number', ['number' => $index + 1]) }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button type="button" size="sm" variant="ghost" wire:click="toggleReferenceCollapsed({{ $index }})">
                        {{ ($reference['collapsed'] ?? false) ? __('Expand') : __('Collapse') }}
                    </flux:button>

                    <flux:button type="button" size="sm" variant="danger" wire:click="removeReference({{ $index }})">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </div>

            @if ($reference['collapsed'] ?? false)
                <button
                    type="button"
                    wire:click="toggleReferenceCollapsed({{ $index }})"
                    class="flex flex-col items-start gap-0.5 text-left"
                >
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ trim($reference['first_name'].' '.$reference['last_name']) ?: __('Untitled reference') }}
                    </span>

                    @if ($period = $this->workPeriodLabel($reference))
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $period }}</span>
                    @endif
                </button>
            @else
                <flux:select wire:model="references.{{ $index }}.type" :label="__('Reference Type')" placeholder="{{ __('Select…') }}">
                    @foreach (\App\Enums\ReferenceType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-3 gap-4">
                    <flux:input wire:model="references.{{ $index }}.title" :label="__('Title')" placeholder="Mr" />
                    <flux:input wire:model="references.{{ $index }}.first_name" :label="__('First Name')" />
                    <flux:input wire:model="references.{{ $index }}.last_name" :label="__('Last Name')" />
                </div>

                <flux:input wire:model="references.{{ $index }}.job_title" :label="__('Job Title')" placeholder="{{ __('Head Teacher') }}" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        type="date"
                        wire:model.live="references.{{ $index }}.worked_from"
                        :label="__('Worked From')"
                        max="{{ now()->format('Y-m-d') }}"
                    />

                    <flux:input
                        type="date"
                        wire:model.live="references.{{ $index }}.worked_to"
                        :label="__('Worked To')"
                        max="{{ now()->format('Y-m-d') }}"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="email" wire:model="references.{{ $index }}.email" :label="__('Email')" />
                    <flux:input wire:model="references.{{ $index }}.mobile" :label="__('Mobile')" />
                </div>

                <flux:input
                    wire:model="references.{{ $index }}.address"
                    :label="__('Address')"
                    placeholder="123 Example Street"
                />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="references.{{ $index }}.city"
                        :label="__('City / Town')"
                        placeholder="London"
                    />

                    <flux:input
                        wire:model="references.{{ $index }}.postcode"
                        :label="__('Postcode')"
                        placeholder="SW1A 1AA"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="references.{{ $index }}.county"
                        :label="__('County')"
                        placeholder="Greater London"
                    />

                    <flux:input
                        wire:model="references.{{ $index }}.country"
                        :label="__('Country')"
                        placeholder="United Kingdom"
                    />
                </div>

                <flux:checkbox
                    wire:model="references.{{ $index }}.consent_to_contact"
                    :label="__('I hereby authorise Applebough Education to contact the referees named in my application and to disclose relevant information about my employment, experience, and suitability for work in education for the purpose of obtaining references and completing safeguarding and compliance checks.')"
                    :description="__('I acknowledge that this consent is required to progress my application and understand that my information will be processed securely and in accordance with applicable data protection laws. (*)')"
                />

                <flux:checkbox
                    wire:model="references.{{ $index }}.contact_now"
                    :label="__('OK to contact this referee now')"
                    :description="__('Leave unchecked if you haven\'t yet told this referee you\'re applying — we\'ll hold off contacting them until you switch this on.')"
                />

                <flux:button type="button" variant="primary" wire:click="saveReference({{ $index }})" class="self-start">
                    {{ __('Add reference') }}
                </flux:button>
            @endif
        </div>
    @endforeach

    <flux:button type="button" variant="ghost" wire:click="addReference" class="self-start">
        {{ __('Add another reference') }}
    </flux:button>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
