@php
    $client = auth()->user()?->client();
@endphp

@if ($client)
    <div class="flex items-center px-4 text-sm font-semibold text-gray-950 dark:text-white">
        {{ \Illuminate\Support\Str::title($client->name) }}
    </div>
@endif
