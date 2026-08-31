<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex, nofollow" />

        <title>{{ $title ?? 'Companion Board' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        @stack('head')
    </head>
    <body class="min-h-screen bg-neutral-950 text-white antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
