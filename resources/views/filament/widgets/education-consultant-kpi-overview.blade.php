<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview'])
    "
>
    {{ $this->content }}

    <x-filament-actions::modals />
</x-filament-widgets::widget>
