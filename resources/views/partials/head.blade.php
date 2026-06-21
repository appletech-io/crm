<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<script>
    // Keep Filament (theme) and Flux (flux.appearance) in sync
    const stored = localStorage.getItem('flux.appearance') || localStorage.getItem('theme');

    if (stored) {
        localStorage.setItem('flux.appearance', stored);
        localStorage.setItem('theme', stored);
    }

    // Watch for changes to either key
    const originalSetItem = localStorage.setItem.bind(localStorage);
    localStorage.setItem = function(key, value) {
        originalSetItem(key, value);
        if (key === 'theme') originalSetItem('flux.appearance', value);
        if (key === 'flux.appearance') originalSetItem('theme', value);
    };
</script>
