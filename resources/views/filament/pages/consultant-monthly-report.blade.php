<x-filament-panels::page>
    <div class="flex flex-col gap-6" wire:init="loadSummary">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Period:</span>

            @foreach ([1, 3] as $option)
                <button
                    type="button"
                    wire:click="setMonths({{ $option }})"
                    @class([
                        'rounded-full px-3 py-1 text-xs font-medium transition',
                        'bg-primary-600 text-white' => $months === $option,
                        'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10' => $months !== $option,
                    ])
                >
                    Last {{ $option }} month{{ $option > 1 ? 's' : '' }}
                </button>
            @endforeach
        </div>

        <div class="flex items-start gap-3 rounded-lg border border-green-500/30 bg-green-500/10 p-4 dark:border-green-400/30 dark:bg-green-400/10">
            <x-filament::icon
                icon="heroicon-o-sparkles"
                class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400 {{ $summary === null ? 'animate-pulse' : '' }}"
            />

            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-wide text-green-700 uppercase dark:text-green-400">
                    AI Summary
                </span>

                @if ($summary === null)
                    <p class="animate-pulse text-sm text-gray-500 dark:text-gray-400">
                        Generating this consultant's report…
                    </p>
                @else
                    <p class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">
                        {{ $summary }}
                    </p>
                @endif
            </div>
        </div>

        @php($stats = $this->periodStats())

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-filament::section>
                <span class="text-xs text-gray-500 dark:text-gray-400">Gross Profit</span>
                <p class="text-lg font-semibold">£{{ number_format($stats['gp'], 2) }}</p>
            </x-filament::section>

            <x-filament::section>
                <span class="text-xs text-gray-500 dark:text-gray-400">Candidate Days Out</span>
                <p class="text-lg font-semibold">{{ $stats['daysPlaced'] }}</p>
            </x-filament::section>

            <x-filament::section>
                <span class="text-xs text-gray-500 dark:text-gray-400">Working Candidates</span>
                <p class="text-lg font-semibold">{{ $stats['candidates'] }}</p>
            </x-filament::section>

            <x-filament::section>
                <span class="text-xs text-gray-500 dark:text-gray-400">Clients Booked</span>
                <p class="text-lg font-semibold">{{ $stats['clients'] }}</p>
            </x-filament::section>
        </div>

        <x-filament::section heading="Week by week">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase dark:text-gray-400">
                            <th class="py-1 pr-4">Week starting</th>
                            <th class="py-1 pr-4">Days out</th>
                            <th class="py-1 pr-4">Gross profit</th>
                            <th class="py-1 pr-4">Candidates</th>
                            <th class="py-1 pr-4">Clients</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->weeklyBreakdown() as $week)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-1.5 pr-4">{{ \Illuminate\Support\Carbon::parse($week['weekStart'])->format('jS M Y') }}</td>
                                <td class="py-1.5 pr-4">{{ $week['daysPlaced'] }}</td>
                                <td class="py-1.5 pr-4">£{{ number_format($week['gp'], 2) }}</td>
                                <td class="py-1.5 pr-4">{{ $week['candidates'] }}</td>
                                <td class="py-1.5 pr-4">{{ $week['clients'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Activity log">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase dark:text-gray-400">
                            <th class="py-1 pr-4">Date</th>
                            <th class="py-1 pr-4">Type</th>
                            <th class="py-1 pr-4">Relates to</th>
                            <th class="py-1 pr-4">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->activities() as $activity)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-1.5 pr-4 whitespace-nowrap">{{ $activity['created_at']->format('d M Y, H:i') }}</td>
                                <td class="py-1.5 pr-4">
                                    <x-filament::badge :color="$activity['type']->color()">
                                        {{ $activity['type']->label() }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-1.5 pr-4 whitespace-nowrap">{{ $activity['kind'] }}: {{ $activity['subject'] }}</td>
                                <td class="py-1.5 pr-4">{{ $activity['note'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-3 text-sm text-gray-500 dark:text-gray-400">
                                    No activity logged in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="flex justify-end">
            <x-filament::link :href="$this->moreInfoUrl()">
                View bookings
            </x-filament::link>
        </div>
    </div>
</x-filament-panels::page>
