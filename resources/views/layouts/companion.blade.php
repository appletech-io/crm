<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex, nofollow" />

        <title>{{ $title ?? 'Companion Board' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Deliberately no @fluxAppearance here: this MVP is fixed to a
        light theme regardless of any dark-mode preference stored by the
        main CRM app in this browser. --}}
        @stack('head')
    </head>
    <body class="min-h-screen bg-neutral-50 text-neutral-900 antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
