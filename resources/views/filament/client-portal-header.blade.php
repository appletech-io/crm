@php
    $client = auth()->user()?->client();
@endphp

@if ($client)
    <div class="flex items-center px-4 text-sm font-semibold text-gray-950 dark:text-white">
        {{ $client->name }}
    </div>
@endif
