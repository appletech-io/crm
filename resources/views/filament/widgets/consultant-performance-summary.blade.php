<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview'])
    "
    wire:init="loadSummary"
>
    <div class="flex flex-col gap-4">
        @if ($this->isAdmin())
            <div class="flex justify-end">
                <div class="w-full max-w-56">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="consultantId">
                            <option value="">All Consultants</option>
                            @foreach ($this->consultantOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        @endif

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
                        Generating this week's summary…
                    </p>
                @else
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $summary }}
                    </p>

                    @if ($this->showMonthlyReportLink())
                        <div class="mt-1">
                            <x-filament::link :href="$this->monthlyReportUrl()">
                                View full monthly report
                            </x-filament::link>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{ $this->content }}
    </div>
</x-filament-widgets::widget>
