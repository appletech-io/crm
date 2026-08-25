<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview'])
    "
>
    <x-filament::section>
        @if ($this->isAdmin())
            <div style="display: flex; justify-content: flex-end; margin-bottom: 0.75rem;">
                <div style="width: 100%; max-width: 220px;">
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

        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $this->summaryText() }}
        </p>
    </x-filament::section>

    {{ $this->content }}

    <div style="text-align: right;">
        <x-filament::link :href="$this->moreInfoUrl()">
            View full breakdown
        </x-filament::link>
    </div>
</x-filament-widgets::widget>
