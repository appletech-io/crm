<x-filament::section heading="Skills" description="Optional. Added to every candidate created from this upload.">
    <div
        x-data="{
            selected: @entangle('skillIds'),
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
        <div class="grid grid-cols-2 gap-4">
            <div class="flex min-h-0 flex-col rounded-lg border border-gray-200 dark:border-white/10">
                <p class="border-b border-gray-200 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:border-white/10 dark:text-gray-500">
                    Available
                </p>

                <div class="max-h-72 min-h-0 overflow-y-auto">
                    <template x-for="option in available" :key="option.id">
                        <button
                            type="button"
                            @click="select(option.id)"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            <span x-text="(option.parentId ? '↳ ' : '') + option.name"></span>
                        </button>
                    </template>

                    <p x-show="available.length === 0" class="px-3 py-2 text-sm text-gray-400">
                        No more skills to add.
                    </p>
                </div>
            </div>

            <div class="flex min-h-0 flex-col rounded-lg border border-gray-200 dark:border-white/10">
                <p class="border-b border-gray-200 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:border-white/10 dark:text-gray-500">
                    Selected
                </p>

                <div class="max-h-72 min-h-0 overflow-y-auto">
                    <template x-for="option in chosen" :key="option.id">
                        <button
                            type="button"
                            @click="deselect(option.id)"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            <span x-text="(option.parentId ? '↳ ' : '') + option.name"></span>
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4 shrink-0 text-gray-400" />
                        </button>
                    </template>

                    <p x-show="chosen.length === 0" class="px-3 py-2 text-sm text-gray-400">
                        No skills selected yet.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament::section>
