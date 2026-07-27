@php
    use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
@endphp

<x-filament::tabs class="mb-6">
    <x-filament::tabs.item
        tag="a"
        :href="EducationCandidateResource::getUrl('search')"
        :active="$activeTab === 'search'"
    >
        Search
    </x-filament::tabs.item>

    <x-filament::tabs.item
        tag="a"
        :href="EducationCandidateResource::getUrl('index')"
        :active="$activeTab === 'index'"
    >
        All Candidates
    </x-filament::tabs.item>
</x-filament::tabs>
