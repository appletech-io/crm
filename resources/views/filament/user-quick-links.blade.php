@auth
    @php
        $quickLinks = auth()->user()->quickLinks;
        $manageUrl = \App\Filament\Resources\UserQuickLinks\UserQuickLinkResource::getUrl('index');
    @endphp

    @if (\App\Filament\Resources\UserQuickLinks\UserQuickLinkResource::canViewAny())
        @foreach ($quickLinks as $quickLink)
            <x-filament::icon-button
                tag="a"
                :href="$quickLink->url"
                :icon="$quickLink->icon"
                color="gray"
                :label="$quickLink->label"
                :tooltip="$quickLink->label"
            />
        @endforeach

        <x-filament::icon-button
            tag="a"
            :href="$manageUrl"
            icon="heroicon-o-cog-6-tooth"
            color="gray"
            label="Manage my quick links"
            tooltip="Manage my quick links"
        />
    @endif
@endauth
