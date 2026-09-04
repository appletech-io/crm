<?php
    use App\Models\User;

    // auth()->user() is only ever populated here once a user is fully
    // logged in (sector-selector, confirm-password, verify-email) — every
    // other page sharing this layout (login, forgot/reset-password, the 2FA
    // challenge) runs before that, so $logoUrl stays null and falls back to
    // the platform default, same as AdminPanelProvider's ->brandLogo() —
    // except the reset-password link itself always carries the user's email
    // as a query param (see the form below), so we can still resolve their
    // company's branding on that one page without requiring a session.
    $logoUrl = auth()->user()?->company?->logoUrl()
        ?? (filled(request()->query('email'))
            ? User::where('email', request()->query('email'))->first()?->company?->logoUrl()
            : null);
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex mb-1 items-center justify-center rounded-md">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" class="h-12 w-auto">
                        @else
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        @endif
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
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
