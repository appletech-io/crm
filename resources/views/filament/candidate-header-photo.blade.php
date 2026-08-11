@php
    $record = \Livewire\Livewire::current()?->record;

    $photo = $record?->documents()->where('document_type', \App\Enums\DocumentType::Photo)->first();

    $photoUrl = $photo
        ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->temporaryUrl($photo->path, now()->addMinutes(10))
        : null;
@endphp

{{--
    The heading block (breadcrumbs + this photo + the <h1> + the status
    subheading) is a flex child with flex-grow: 0 by default, so it's
    squeezed down to its own content width rather than using the space
    actually available in the header row — with a long name next to the
    photo, that's what was forcing the name onto two cramped lines.
--}}
<style>
    .fi-resource-education-candidates .fi-header-has-breadcrumbs > div:first-child,
    .fi-resource-healthcare-candidates .fi-header-has-breadcrumbs > div:first-child {
        flex-grow: 1;
        min-width: 0;
    }
</style>

@if ($photoUrl)
    <img
        src="{{ $photoUrl }}"
        alt="Candidate photo"
        class="h-14 w-14 rounded-full object-cover"
        style="width: 3.5rem; height: 3.5rem; border-radius: 9999px; object-fit: cover; float: left; margin-right: 0.75rem; flex-shrink: 0;"
    >
@endif
