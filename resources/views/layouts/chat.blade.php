<?php
    $company = auth()->user()?->company;
    $companyLogoUrl = $company?->logoUrl();
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['faviconUrl' => $company?->faviconUrl()])
    </head>
    <body class="h-screen overflow-hidden bg-linear-to-b from-zinc-50 to-zinc-100 antialiased dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex h-full flex-col">
            <header class="flex shrink-0 items-center justify-between px-6 py-4">
                <a
                    href="/crm"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
                >
                    <flux:icon.arrow-left variant="micro" />
                    {{ __('Back to CRM') }}
                </a>

                <img src="{{ $companyLogoUrl ?? asset('images/appletech.png') }}" class="h-7 w-auto" alt="{{ config('app.name') }}">
            </header>

            <main class="flex flex-1 items-center justify-center overflow-hidden px-4 pb-6 md:px-8">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @stack('scripts')
        @fluxScripts
    </body>
</html>
