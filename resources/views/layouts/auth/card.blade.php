<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')

        <style>
            .auth-card-glow {
                background-image:
                    radial-gradient(80% 42% at 50% 0%, rgba(243, 228, 205, 0.55) 0%, rgba(0, 0, 0, 0) 70%),
                    radial-gradient(135% 78% at 50% 0%, rgba(244, 231, 215, 0.45) 0%, rgba(0, 0, 0, 0) 80%);
            }

            .dark .auth-card-glow {
                background-image:
                    radial-gradient(80% 42% at 50% 0%, rgba(45, 74, 55, 0.35) 0%, rgba(0, 0, 0, 0) 70%),
                    radial-gradient(135% 78% at 50% 0%, rgba(38, 63, 47, 0.3) 0%, rgba(0, 0, 0, 0) 80%);
            }
        </style>
    </head>
    <body class="min-h-screen bg-stone-50 antialiased dark:bg-stone-950">
        <div class="auth-card-glow flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div
                class="flex w-full max-w-lg flex-col gap-6 text-gray-950 dark:text-white [--color-accent:oklch(0.5_0.071_162.267)] [--color-accent-content:oklch(0.5_0.071_162.267)] [--color-accent-foreground:var(--color-white)] dark:[--color-accent:oklch(0.65_0.09_162.267)] dark:[--color-accent-content:oklch(0.75_0.09_162.267)] dark:[--color-accent-foreground:var(--color-white)] [&_[data-flux-button]]:rounded-full"
            >
                <div class="flex flex-col gap-6">
                    <div class="w-full bg-white shadow-xs ring-1 ring-gray-950/5 sm:rounded-2xl dark:bg-gray-900 dark:ring-white/10">
                        <div class="px-6 py-12 sm:px-12">{{ $slot }}</div>
                    </div>

                    @isset($below)
                        <div class="w-full bg-white shadow-xs ring-1 ring-gray-950/5 sm:rounded-2xl dark:bg-gray-900 dark:ring-white/10">
                            <div class="px-6 py-12 sm:px-12">{{ $below }}</div>
                        </div>
                    @endisset
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @if ($toast = session()->pull('toast'))
            <script>
                document.addEventListener('DOMContentLoaded', () => window.Flux.toast(@js($toast)));
            </script>
        @endif

        @fluxScripts
    </body>
</html>
