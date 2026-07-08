<x-filament-panels::page>
    <x-filament::tabs label="Sections">
        <x-filament::tabs.item
            :active="$activeTab === 'actions'"
            wire:click="$set('activeTab', 'actions')"
            icon="heroicon-o-exclamation-circle"
        >
            Actions
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$activeTab === 'documents'"
            wire:click="$set('activeTab', 'documents')"
            icon="heroicon-o-document-text"
        >
            Documents
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
