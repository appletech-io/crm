<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class(['fi-wi-stats-overview', 'fi-wi-compliance-kpi-overview'])
    "
>
    <style>
        .fi-wi-compliance-kpi-overview .fi-wi-stats-overview-stat-value {
            order: -1;
        }
    </style>

    {{ $this->content }}
</x-filament-widgets::widget>
