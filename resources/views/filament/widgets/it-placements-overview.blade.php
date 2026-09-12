<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview'])
    "
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

        {{ $this->content }}
    </div>
</x-filament-widgets::widget>
