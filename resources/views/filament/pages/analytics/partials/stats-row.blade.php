@props(['stats'])

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));">
    @foreach ($stats as $label => $value)
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</p>
        </div>
    @endforeach
</div>
