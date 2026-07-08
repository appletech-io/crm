<x-auth-header
    :title="__('Your Details')"
    :description="__('Review and complete your personal information below.')"
/>

<form
    x-data="{
        attempted: false,
        get isValid() {
            return !!($wire.title && $wire.first_name && $wire.last_name && $wire.date_of_birth && $wire.gender && $wire.nationality && $wire.address && $wire.city && $wire.postcode);
        },
    }"
    x-on:submit.prevent="
        attempted = true;

        if (isValid) {
            $wire.nextStep();
        }
    "
    class="mt-6 flex flex-col gap-8"
>

    {{-- Personal Information --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Personal Information') }}</p>

        <div class="grid grid-cols-2 gap-4">
            <flux:select
                wire:model="title"
                :label="__('Title')"
                placeholder="{{ __('Please select…') }}"
                required
                x-bind:class="attempted && !$wire.title ? '!border-red-500' : ''"
            >
                @foreach(['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'] as $t)
                    <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="first_name"
                :label="__('First Name')"
                placeholder="John"
                required
                x-bind:class="attempted && !$wire.first_name ? '!border-red-500' : ''"
            />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:input
                wire:model="middle_name"
                :label="__('Middle Name')"
                placeholder="William"
            />

            <flux:input
                wire:model="last_name"
                :label="__('Last Name')"
                placeholder="Smith"
                required
                x-bind:class="attempted && !$wire.last_name ? '!border-red-500' : ''"
            />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:input
                wire:model="previous_surname"
                :label="__('Previous Name')"
                placeholder="Jones"
            />

            <div
                x-data="{
                    fp: null,
                    init() {
                        this.fp = flatpickr(this.$refs.dobInput, {
                            dateFormat: 'M j, Y',
                            disableMobile: true,
                            maxDate: 'today',
                            allowInput: true,
                            defaultDate: this.$refs.dobInput.value || null,
                            onChange: (dates, dateStr) => {
                                this.$refs.dobInput.value = dateStr;
                                this.$refs.dobInput.dispatchEvent(new Event('input', { bubbles: true }));
                            },
                        });
                        this.$watch('$wire.date_of_birth', (value) => {
                            if (this.fp) this.fp.setDate(value || null, false);
                        });
                    },
                    destroy() {
                        if (this.fp) this.fp.destroy();
                    },
                }"
            >
                <flux:input
                    input:x-ref="dobInput"
                    wire:model="date_of_birth"
                    :label="__('Date of Birth')"
                    placeholder="Jul 13, 1995"
                    required
                    x-bind:class="attempted && !$wire.date_of_birth ? '!border-red-500' : ''"
                />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:select
                wire:model="gender"
                :label="__('Gender')"
                placeholder="{{ __('Please select…') }}"
                required
                x-bind:class="attempted && !$wire.gender ? '!border-red-500' : ''"
            >
                <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                <flux:select.option value="non_binary">{{ __('Non-binary') }}</flux:select.option>
                <flux:select.option value="prefer_not_to_say">{{ __('Prefer not to say') }}</flux:select.option>
            </flux:select>

            <div
                x-data="{
                    open: false,
                    search: '',
                    selected: @entangle('nationality'),
                    options: @js(\App\Enums\Nationality::options()),

                    trigger: null,

                    get isDark() {
                        return document.documentElement.classList.contains('dark');
                    },

                    get filtered() {
                        if (! this.search) return this.options;

                        return Object.fromEntries(
                            Object.entries(this.options).filter(([value, label]) =>
                                label.toLowerCase().includes(this.search.toLowerCase())
                            )
                        );
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

                    select(value) {
                        this.selected = value;
                        this.$wire.set('nationality', value);
                        this.open = false;
                        this.search = '';
                    }
                }"
                class="relative"
            >
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Nationality
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
                    x-bind:class="attempted && !selected ? '!border-red-500' : ''"
                >
                    <span
                        x-text="options[selected] ?? 'Select nationality'"
                        :class="selected ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-400'"
                    ></span>
                </button>

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
                            placeholder="Search..."
                            :style="isDark
                                ? 'color: #e4e4e7; border-bottom-color: rgba(255,255,255,0.1);'
                                : 'color: #18181b; border-bottom-color: #e4e4e7;'"
                            class="w-full border-b bg-transparent px-3 py-2 text-sm outline-none placeholder:text-zinc-400"
                            x-init="$watch('open', value => value && $nextTick(() => $el.focus()))"
                        />

                        <div class="max-h-60 overflow-y-auto">
                            <template
                                x-for="[value, label] in Object.entries(filtered)"
                                :key="value"
                            >
                                <button
                                    type="button"
                                    @click="select(value)"
                                    :class="isDark
                                        ? 'text-zinc-100 hover:bg-zinc-700'
                                        : 'text-zinc-900 hover:bg-zinc-100'"
                                    class="block w-full px-3 py-2 text-left text-sm"
                                    x-text="label"
                                ></button>
                            </template>

                            <div
                                x-show="Object.keys(filtered).length === 0"
                                :class="isDark ? 'text-zinc-400' : 'text-zinc-500'"
                                class="px-3 py-2 text-sm"
                            >
                                No results found.
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Address --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Address') }}</p>

        <flux:input
            wire:model="address"
            :label="__('Address')"
            placeholder="123 Example Street"
            required
            x-bind:class="attempted && !$wire.address ? '!border-red-500' : ''"
        />

        <div class="grid grid-cols-2 gap-4">
            <flux:input
                wire:model="city"
                :label="__('City / Town')"
                placeholder="London"
                required
                x-bind:class="attempted && !$wire.city ? '!border-red-500' : ''"
            />

            <flux:input
                wire:model="postcode"
                :label="__('Postcode')"
                placeholder="SW1A 1AA"
                required
                x-bind:class="attempted && !$wire.postcode ? '!border-red-500' : ''"
            />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:input
                wire:model="county"
                :label="__('County')"
                placeholder="Greater London"
            />

            <flux:input
                wire:model="country"
                :label="__('Country')"
                placeholder="United Kingdom"
            />
        </div>
    </div>

    {{-- Contact Details --}}
    <div class="flex flex-col gap-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Contact Details') }}</p>

        <div class="grid grid-cols-2 gap-4">
            <flux:input
                wire:model="phone"
                type="tel"
                :label="__('Phone')"
                placeholder="+44 20 7946 0000"
            />

            <flux:input
                wire:model="mobile"
                type="tel"
                :label="__('Mobile')"
                placeholder="+44 7700 900000"
            />
        </div>
    </div>

    @foreach(['first_name', 'last_name', 'date_of_birth', 'address', 'city', 'postcode'] as $field)
        @error($field)
            <flux:error>{{ $message }}</flux:error>
        @enderror
    @endforeach

    <flux:button type="submit" variant="primary" class="w-full">
        {{ __('Next') }}
    </flux:button>

</form>
