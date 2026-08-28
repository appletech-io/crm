@php
    $disputedDays = collect($getState() ?? [])->filter(fn (array $day): bool => $day['disputed'] ?? false)->values();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            days: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            selected: [],

            get hoursDays() {
                return this.days.filter((day) => day.period === 'hours' && ! day.cancelled);
            },

            get calendarMonths() {
                if (this.days.length === 0) {
                    return [];
                }

                const dates = this.days.map((day) => day.date).sort();
                const first = new Date(`${dates[0]}T00:00:00`);
                const last = new Date(`${dates[dates.length - 1]}T00:00:00`);

                const months = [];
                const cursor = new Date(first.getFullYear(), first.getMonth(), 1);
                const end = new Date(last.getFullYear(), last.getMonth(), 1);

                while (cursor <= end) {
                    months.push(this.buildMonth(cursor.getFullYear(), cursor.getMonth()));
                    cursor.setMonth(cursor.getMonth() + 1);
                }

                return months;
            },

            buildMonth(year, month) {
                const firstOfMonth = new Date(year, month, 1);
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const firstIsoWeekday = ((firstOfMonth.getDay() + 6) % 7) + 1;

                const cells = [];

                for (let i = 1; i < firstIsoWeekday; i++) {
                    cells.push(null);
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    const entry = this.days.find((day) => day.date === dateStr);
                    cells.push(entry ?? { date: dateStr, blank: true });
                }

                while (cells.length % 7 !== 0) {
                    cells.push(null);
                }

                const weeks = [];

                for (let i = 0; i < cells.length; i += 7) {
                    weeks.push(cells.slice(i, i + 7));
                }

                return {
                    label: firstOfMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
                    weeks,
                };
            },

            toggleSelected(date) {
                this.selected = this.selected.includes(date)
                    ? this.selected.filter((d) => d !== date)
                    : [...this.selected, date];
            },

            selectAll() {
                this.selected = this.days.map((day) => day.date);
            },

            applyPeriod(period) {
                this.days = this.days.map((day) => this.selected.includes(day.date)
                    ? { ...day, period, cancelled: false }
                    : day);
            },

            applyCancelled(cancelled) {
                this.days = this.days.map((day) => this.selected.includes(day.date)
                    ? { ...day, cancelled }
                    : day);
            },

            periodLabel(day) {
                if (day.cancelled) {
                    return 'N/A';
                }

                return { full_day: 'Full', am: 'AM', pm: 'PM', hours: 'Hrs' }[day.period] ?? day.period;
            },

            cellClasses(day) {
                const selected = this.selected.includes(day.date);
                const ring = selected ? 'ring-2 ring-primary-500' : '';

                if (day.cancelled) {
                    return `${ring} bg-gray-200 text-gray-400 line-through dark:bg-white/10 dark:text-gray-500`;
                }

                const colors = {
                    full_day: 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300',
                    am: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
                    pm: 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300',
                    hours: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
                };

                return `${ring} ${colors[day.period] ?? 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300'}`;
            },
        }"
        class="flex flex-col gap-4"
    >
        <template x-if="days.length === 0">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Set a start date above to generate the schedule.
            </p>
        </template>

        <template x-if="days.length > 0">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap gap-6">
                    <template x-for="month in calendarMonths" :key="month.label">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="month.label"></p>

                            <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-400 dark:text-gray-500">
                                <span>Mo</span>
                                <span>Tu</span>
                                <span>We</span>
                                <span>Th</span>
                                <span>Fr</span>
                                <span>Sa</span>
                                <span>Su</span>
                            </div>

                            <template x-for="(week, weekIndex) in month.weeks" :key="weekIndex">
                                <div class="grid grid-cols-7 gap-1">
                                    <template x-for="(cell, cellIndex) in week" :key="cellIndex">
                                        <div>
                                            <template x-if="cell === null || cell.blank">
                                                <div class="h-12 w-12"></div>
                                            </template>

                                            <template x-if="cell && ! cell.blank">
                                                <button
                                                    type="button"
                                                    x-on:click="toggleSelected(cell.date)"
                                                    :class="cellClasses(cell)"
                                                    class="relative flex h-12 w-12 flex-col items-center justify-center gap-0.5 rounded-lg text-xs transition"
                                                >
                                                    <span x-text="cell.date.slice(-2)"></span>
                                                    <span class="text-[10px] font-medium" x-text="periodLabel(cell)"></span>
                                                    <span x-show="cell.disputed" class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full bg-red-500" title="Disputed"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3 text-xs dark:border-white/10">
                    <span class="flex items-center gap-1">
                        <span class="h-3 w-3 rounded bg-primary-100 dark:bg-primary-500/20"></span> Full Day
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="h-3 w-3 rounded bg-blue-100 dark:bg-blue-500/20"></span> AM
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="h-3 w-3 rounded bg-purple-100 dark:bg-purple-500/20"></span> PM
                    </span>
                    @if ($isHoursEnabled())
                        <span class="flex items-center gap-1">
                            <span class="h-3 w-3 rounded bg-orange-100 dark:bg-orange-500/20"></span> Hours
                        </span>
                    @endif
                    <span class="flex items-center gap-1">
                        <span class="h-3 w-3 rounded bg-gray-200 dark:bg-white/10"></span> N/A
                    </span>
                    @if ($disputedDays->isNotEmpty())
                        <span class="flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span> Disputed
                        </span>
                    @endif
                    <span class="ml-auto text-gray-500 dark:text-gray-400">
                        <span x-text="selected.length"></span> day(s) selected
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-on:click="selectAll()" class="rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                        Select all
                    </button>
                    <button type="button" x-on:click="selected = []" class="rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                        Clear selection
                    </button>

                    <span class="mx-1 h-4 w-px bg-gray-200 dark:bg-white/10"></span>

                    <button type="button" x-on:click="applyPeriod('full_day')" :disabled="selected.length === 0" class="rounded-full bg-primary-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                        Set Full Day
                    </button>
                    <button type="button" x-on:click="applyPeriod('am')" :disabled="selected.length === 0" class="rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                        Set AM
                    </button>
                    <button type="button" x-on:click="applyPeriod('pm')" :disabled="selected.length === 0" class="rounded-full bg-purple-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                        Set PM
                    </button>
                    @if ($isHoursEnabled())
                        <button type="button" x-on:click="applyPeriod('hours')" :disabled="selected.length === 0" class="rounded-full bg-orange-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                            Set Hours
                        </button>
                    @endif
                    <button type="button" x-on:click="applyCancelled(true)" :disabled="selected.length === 0" class="rounded-full bg-gray-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                        Set N/A
                    </button>
                    <button type="button" x-on:click="applyCancelled(false)" :disabled="selected.length === 0" class="rounded-full bg-green-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-40">
                        Include
                    </button>
                </div>

                @if ($isHoursEnabled())
                    <template x-if="hoursDays.length > 0">
                        <div class="flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Times for Hours days</p>

                            <template x-for="day in hoursDays" :key="day.date">
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="w-28 text-gray-600 dark:text-gray-300" x-text="day.date"></span>
                                    <input type="time" x-model="day.time_from" class="fi-input rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                                    <span class="text-gray-400">to</span>
                                    <input type="time" x-model="day.time_to" class="fi-input rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                                </div>
                            </template>
                        </div>
                    </template>
                @endif

                @if ($disputedDays->isNotEmpty())
                    <div class="flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Disputed days</p>

                        @foreach ($disputedDays as $day)
                            <div class="flex items-start gap-3 text-sm">
                                <span class="w-40 shrink-0 font-medium text-red-600 dark:text-red-400">
                                    {{ \Carbon\Carbon::parse($day['date'])->format('D j M Y') }} (Disputed)
                                </span>
                                <span class="text-gray-600 dark:text-gray-300">{{ $day['dispute_reason'] ?? 'No reason given.' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </template>
    </div>
</x-dynamic-component>
