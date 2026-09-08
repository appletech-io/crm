{{--
    Collapses the sidebar whenever the pointer isn't over it, and expands it
    on hover, so the nav stays out of the way without needing the toggle
    button clicked each time.

    Driven through Filament's own Alpine sidebar store rather than CSS, so
    every internal detail it controls — item labels, group dropdowns, the
    main content's left offset — stays in step. Listeners are delegated from
    `document` rather than bound to the sidebar element, which means they
    survive Livewire SPA navigation without being re-attached.
--}}
<script>
    (() => {
        // Matches the breakpoint Filament's own sidebar store uses, so the
        // mobile sidebar keeps its tap-to-open behaviour untouched.
        const DESKTOP_BREAKPOINT = 1024;

        const sidebarStore = () => window.Alpine?.store('sidebar') ?? null;

        const isDesktop = () => window.innerWidth >= DESKTOP_BREAKPOINT;

        document.addEventListener('mouseover', (event) => {
            if (! isDesktop() || ! event.target.closest?.('.fi-main-sidebar')) {
                return;
            }

            sidebarStore()?.open();
        });

        document.addEventListener('mouseout', (event) => {
            if (! isDesktop()) {
                return;
            }

            const sidebar = event.target.closest?.('.fi-main-sidebar');

            // mouseout also fires when moving between the sidebar's own
            // children, so only collapse once the pointer has genuinely
            // left the whole element.
            if (! sidebar || sidebar.contains(event.relatedTarget)) {
                return;
            }

            sidebarStore()?.close();
        });
    })();
</script>
