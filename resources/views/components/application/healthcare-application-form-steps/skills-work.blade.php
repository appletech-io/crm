<x-auth-header
    :title="__('Right to Work & Skills')"
    :description="__('Tell us about your qualifications, skills, and right to work.')"
/>

<form wire:submit="saveSkillsAndRightToWork" class="mt-6 flex flex-col gap-8">

    {{-- Qualification & Availability --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Qualification & Availability') }}</p>

        <flux:select wire:model="qualification_id" :label="__('Qualification')" placeholder="{{ __('Select…') }}">
            @foreach ($this->qualificationOptions as $id => $name)
                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:checkbox.group wire:model="availability" :label="__('Availability')">
            <div class="grid grid-cols-2 gap-3">
                @foreach ($this->availabilityOptions as $value => $label)
                    <flux:checkbox value="{{ $value }}" :label="$label" />
                @endforeach
            </div>
        </flux:checkbox.group>

        <flux:checkbox.group wire:model="care_settings" :label="__('Care Settings')">
            <div class="grid grid-cols-2 gap-3">
                @foreach ($this->careSettingOptions as $value => $label)
                    <flux:checkbox value="{{ $value }}" :label="$label" />
                @endforeach
            </div>
        </flux:checkbox.group>
    </div>

    {{-- Skills --}}
    <div
        x-data="{
            selected: @entangle('skills'),
            options: @js($this->skillOptions->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'parentId' => $skill->parent_id,
            ])->values()),

            get available() {
                return this.options.filter((option) => ! this.selected.includes(option.id));
            },

            get chosen() {
                return this.options.filter((option) => this.selected.includes(option.id));
            },

            select(id) {
                if (this.selected.includes(id)) return;

                this.selected = [...this.selected, id];

                const option = this.options.find((option) => option.id === id);

                if (option?.parentId && ! this.selected.includes(option.parentId)) {
                    this.selected = [...this.selected, option.parentId];
                }
            },

            deselect(id) {
                this.selected = this.selected.filter((value) => value !== id);
            },
        }"
    >
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Skills') }}
        </label>

        <flux:error name="skills" />

        <div class="mt-1 grid grid-cols-2 gap-4">
            <div class="flex min-h-0 flex-col rounded-lg border border-zinc-200 dark:border-white/10">
                <p class="border-b border-zinc-200 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:border-white/10 dark:text-zinc-500">
                    {{ __('Available') }}
                </p>

                <div class="max-h-72 min-h-0 overflow-y-auto">
                    <template x-for="option in available" :key="option.id">
                        <button
                            type="button"
                            @click="select(option.id)"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            <span x-text="(option.parentId ? '↳ ' : '') + option.name"></span>
                        </button>
                    </template>

                    <p x-show="available.length === 0" class="px-3 py-2 text-sm text-zinc-400">
                        {{ __('No more skills to add.') }}
                    </p>
                </div>
            </div>

            <div class="flex min-h-0 flex-col rounded-lg border border-zinc-200 dark:border-white/10">
                <p class="border-b border-zinc-200 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:border-white/10 dark:text-zinc-500">
                    {{ __('Selected') }}
                </p>

                <div class="max-h-72 min-h-0 overflow-y-auto">
                    <template x-for="option in chosen" :key="option.id">
                        <button
                            type="button"
                            @click="deselect(option.id)"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            <span x-text="(option.parentId ? '↳ ' : '') + option.name"></span>
                            <flux:icon.x-mark variant="mini" class="size-4 shrink-0 text-zinc-400" />
                        </button>
                    </template>

                    <p x-show="chosen.length === 0" class="px-3 py-2 text-sm text-zinc-400">
                        {{ __('No skills selected yet.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Right to Work & DBS --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Right to Work & DBS') }}</p>

        <flux:select wire:model="right_to_work_type" :label="__('Right to Work')" placeholder="{{ __('Select…') }}" required>
            <flux:select.option value="passport">{{ __('UK Passport') }}</flux:select.option>
            <flux:select.option value="birth_certificate">{{ __('UK Birth Certificate') }}</flux:select.option>
            <flux:select.option value="visa">{{ __('Visa') }}</flux:select.option>
        </flux:select>
        @error('right_to_work_type') <flux:error>{{ $message }}</flux:error> @enderror

        <div x-show="$wire.right_to_work_type === 'visa' || $wire.right_to_work_type === 'passport'">
            <flux:input type="date" wire:model="right_to_work_expiry_date" :label="__('Right to Work Document Expiry Date (optional)')" />
            @error('right_to_work_expiry_date') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <flux:select wire:model="has_dbs" :label="__('Do you currently have a DBS?')" placeholder="{{ __('Select…') }}" required>
            <flux:select.option value="yes">{{ __('Yes') }}</flux:select.option>
            <flux:select.option value="no">{{ __('No') }}</flux:select.option>
        </flux:select>
        @error('has_dbs') <flux:error>{{ $message }}</flux:error> @enderror

        <div x-show="$wire.has_dbs === 'yes'">
            <flux:input type="date" wire:model="dbs_expiry_date" :label="__('DBS Expiry Date (optional)')" />
            @error('dbs_expiry_date') <flux:error>{{ $message }}</flux:error> @enderror
        </div>
    </div>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Continue') }}
    </flux:button>

</form>
