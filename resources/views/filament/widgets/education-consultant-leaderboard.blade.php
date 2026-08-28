<x-filament-widgets::widget>
    <x-filament::section heading="Consultant Leaderboard">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; opacity: 0.6;"><span style="font-weight: 700; opacity: 1;">This week</span> &nbsp;&middot;&nbsp; Before/next week shown for the current week only</span>

            <div style="width: 100%; max-width: 220px;">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="selectedMonth">
                        @foreach ($this->monthOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @php($weeks = $this->weeks())
        @php($rows = $this->leaderboard())
        @php($medals = ['🥇', '🥈', '🥉'])

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: center; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">#</th>
                        <th style="text-align: left; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">Consultant</th>
                        @foreach ($weeks as $week)
                            <th
                                style="text-align: left; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25); {{ $this->isCurrentWeek($week) ? 'background-color: rgba(22, 163, 74, 0.12); border-radius: 0.375rem 0.375rem 0 0;' : '' }}"
                            >
                                <span style="{{ $this->isCurrentWeek($week) ? 'font-weight: 700;' : '' }}">{{ $week->format('d M') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr style="border-bottom: 1px solid rgba(120, 120, 120, 0.12);">
                            <td style="padding: 0.5rem; text-align: center; font-weight: 700; font-size: 1rem;">
                                {{ $medals[$index] ?? $index + 1 }}
                            </td>
                            <td style="padding: 0.5rem; white-space: nowrap; font-weight: 500;">
                                {{ $row['consultant']->name }}
                            </td>
                            @foreach ($weeks as $week)
                                @php($cell = $row['weeks']->get($week->toDateString()))
                                <td
                                    style="padding: 0.5rem; white-space: nowrap; {{ $this->isCurrentWeek($week) ? 'background-color: rgba(22, 163, 74, 0.06);' : '' }}"
                                >
                                    <div style="display: flex; align-items: baseline; gap: 0.625rem;">
                                        @if ($this->isCurrentWeek($week))
                                            <span style="font-size: 0.75rem; opacity: 0.6;" title="This week's booking days that were booked in advance, before the week started">{{ $cell['start'] }}</span>
                                        @endif
                                        <span style="font-size: 1.25rem; font-weight: 700;" title="Booking days on for this week">{{ $cell['current'] }}</span>
                                        @if ($this->isCurrentWeek($week))
                                            <span style="font-size: 0.75rem; opacity: 0.6;" title="Booking days already on for next week">&rarr; {{ $cell['nextWeek'] }}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $weeks->count() + 2 }}" style="padding: 1rem; text-align: center;">
                                No consultants found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
