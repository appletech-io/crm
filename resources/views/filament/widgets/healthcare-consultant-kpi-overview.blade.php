<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview'])
    "
>
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

    {{ $this->content }}
</x-filament-widgets::widget>
