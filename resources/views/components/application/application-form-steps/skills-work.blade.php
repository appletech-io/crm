<x-auth-header
    :title="__('Skills & Work Preferences')"
    :description="__('Tell us about your qualifications, skills, and availability.')"
/>

<form wire:submit="saveWorkPreferences" class="mt-6 flex flex-col gap-8">

    {{-- Qualification & Availability --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Qualification & Availability') }}</p>

        <flux:select wire:model="qualification_id" :label="__('Qualification')" placeholder="{{ __('Select…') }}">
            @foreach($this->qualificationOptions as $id => $name)
                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:checkbox.group wire:model="availability" :label="__('Availability')">
            <div class="grid grid-cols-2 gap-3">
                @foreach(\App\Enums\Education\Availability::cases() as $option)
                    <flux:checkbox value="{{ $option->value }}" :label="$option->label()" />
                @endforeach
            </div>
        </flux:checkbox.group>

        <div
            x-data="{
                fp: null,
                init() {
                    this.fp = flatpickr(this.$refs.availableFromInput, {
                        dateFormat: 'M j, Y',
                        disableMobile: true,
                        minDate: 'today',
                        allowInput: true,
                        defaultDate: this.$refs.availableFromInput.value || null,
                        onChange: (dates, dateStr) => {
                            this.$refs.availableFromInput.value = dateStr;
                            this.$refs.availableFromInput.dispatchEvent(new Event('input', { bubbles: true }));
                        },
                    });
                    this.$watch('$wire.available_from', (value) => {
                        if (this.fp) this.fp.setDate(value || null, false);
                    });
                },
                destroy() {
                    if (this.fp) this.fp.destroy();
                },
            }"
        >
            <flux:input
                input:x-ref="availableFromInput"
                wire:model="available_from"
                :label="__('When can you start working with us?')"
                placeholder="Jul 13, 1995"
            />
        </div>
    </div>

    {{-- Key Stages --}}
    <flux:checkbox.group wire:model="key_stages" :label="__('Key Stages')">
        <div class="grid grid-cols-2 gap-3">
            @foreach(\App\Enums\Education\KeyStage::cases() as $stage)
                <flux:checkbox value="{{ $stage->value }}" :label="$stage->label()" />
            @endforeach
        </div>
    </flux:checkbox.group>

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

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Continue') }}
    </flux:button>

</form>
