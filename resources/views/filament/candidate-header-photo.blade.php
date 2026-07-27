@php
    $record = \Livewire\Livewire::current()?->record;

    $photo = $record?->documents()->where('document_type', \App\Enums\DocumentType::Photo)->first();

    $photoUrl = $photo
        ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->temporaryUrl($photo->path, now()->addMinutes(10))
        : null;
@endphp

@if ($photoUrl)
    <img
        src="{{ $photoUrl }}"
        alt="Candidate photo"
        class="h-14 w-14 rounded-full object-cover"
        style="width: 3.5rem; height: 3.5rem; border-radius: 9999px; object-fit: cover; float: left; margin-right: 0.75rem; flex-shrink: 0;"
    >
@endif
