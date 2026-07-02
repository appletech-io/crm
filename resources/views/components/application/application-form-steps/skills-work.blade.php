<x-auth-header
    :title="__('Skills & Work Preferences')"
    :description="__('Tell us about your qualifications, skills, and availability.')"
/>

<form wire:submit="submitApplication" class="mt-6 flex flex-col gap-8">

    {{-- Qualification & Availability --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Qualification & Availability') }}</p>

        <flux:select wire:model="qualification_id" :label="__('Qualification')" placeholder="{{ __('Select…') }}">
            @foreach($this->qualificationOptions as $id => $name)
                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="grid grid-cols-2 gap-4">
            <flux:select wire:model="employment_type" :label="__('What kind of employment are you seeking?')" placeholder="{{ __('Select…') }}">
                @foreach(\App\Enums\Education\EmploymentType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div
                x-data="{
                    fp: null,
                    init() {
                        this.fp = flatpickr(this.$refs.availableFromInput, {
                            dateFormat: 'Y-m-d',
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
                    placeholder="YYYY-MM-DD"
                />
            </div>
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
            open: false,
            search: '',
            selected: @entangle('skills'),
            options: @js($this->skillOptions->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'parentId' => $skill->parent_id,
            ])->values()),

            trigger: null,

            get isDark() {
                return document.documentElement.classList.contains('dark');
            },

            get filtered() {
                if (! this.search) return this.options;

                return this.options.filter((option) => option.name.toLowerCase().includes(this.search.toLowerCase()));
            },

            positionDropdown() {
                if (!this.trigger || !this.$refs.dropdown) return;

                const rect = this.trigger.getBoundingClientRect();

                this.$refs.dropdown.style.top = `${rect.bottom + 4}px`;
                this.$refs.dropdown.style.left = `${rect.left}px`;
                this.$refs.dropdown.style.width = `${rect.width}px`;

                if (this.open) {
                    requestAnimationFrame(() => this.positionDropdown());
                }
            },

            isSelected(id) {
                return this.selected.includes(id);
            },

            labelFor(id) {
                return this.options.find((option) => option.id === id)?.name ?? '';
            },

            toggle(id) {
                if (this.isSelected(id)) {
                    this.selected = this.selected.filter((value) => value !== id);

                    return;
                }

                this.selected = [...this.selected, id];

                const option = this.options.find((option) => option.id === id);

                if (option?.parentId && ! this.isSelected(option.parentId)) {
                    this.selected = [...this.selected, option.parentId];
                }
            },

            remove(id) {
                this.selected = this.selected.filter((value) => value !== id);
            },
        }"
        class="relative"
    >
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Skills') }}
        </label>

        <button
            type="button"
            x-ref="trigger"
            x-init="trigger = $refs.trigger"
            @click="
                open = !open;

                if (open) {
                    positionDropdown();
                }
            "
            class="mt-1 flex h-10 w-full items-center rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-left text-sm shadow-xs dark:border-white/10 dark:bg-white/10"
        >
            <span x-show="selected.length === 0" class="text-zinc-400">{{ __('Search and select skills…') }}</span>
            <span x-show="selected.length > 0" class="text-zinc-700 dark:text-zinc-300" x-text="selected.length + ' skill' + (selected.length === 1 ? '' : 's') + ' selected'"></span>
        </button>

        <div x-show="selected.length > 0" class="mt-2 flex flex-wrap gap-2">
            <template x-for="id in selected" :key="id">
                <flux:badge size="sm">
                    <span x-text="labelFor(id)"></span>
                    <flux:badge.close @click="remove(id)" />
                </flux:badge>
            </template>
        </div>

        <template x-teleport="body">
            <div
                x-ref="dropdown"
                x-show="open"
                x-transition
                @click.outside="open = false"
                :style="isDark
                    ? 'background-color: #27272a; border-color: rgba(255,255,255,0.1);'
                    : 'background-color: #ffffff; border-color: #e4e4e7;'"
                class="fixed z-9999 rounded-lg border shadow-xl"
                style="display:none;"
            >
                <input
                    x-model="search"
                    type="text"
                    placeholder="Search…"
                    :style="isDark
                        ? 'color: #e4e4e7; border-bottom-color: rgba(255,255,255,0.1);'
                        : 'color: #18181b; border-bottom-color: #e4e4e7;'"
                    class="w-full border-b bg-transparent px-3 py-2 text-sm outline-none placeholder:text-zinc-400"
                    x-init="$watch('open', value => value && $nextTick(() => $el.focus()))"
                />

                <div class="max-h-60 overflow-y-auto">
                    <template x-for="option in filtered" :key="option.id">
                        <button
                            type="button"
                            @click="toggle(option.id)"
                            :class="isDark
                                ? 'text-zinc-100 hover:bg-zinc-700'
                                : 'text-zinc-900 hover:bg-zinc-100'"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm"
                        >
                            <span x-text="(option.parentId ? '↳ ' : '') + option.name"></span>
                            <flux:icon.check x-show="isSelected(option.id)" variant="mini" class="size-4 shrink-0 text-[var(--color-accent)]" />
                        </button>
                    </template>

                    <div
                        x-show="filtered.length === 0"
                        :class="isDark ? 'text-zinc-400' : 'text-zinc-500'"
                        class="px-3 py-2 text-sm"
                    >
                        No results found.
                    </div>
                </div>
            </div>
        </template>
    </div>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Complete Application') }}
    </flux:button>

</form>
