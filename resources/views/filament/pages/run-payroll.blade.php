<x-filament-panels::page>
    <style>
        /*
         * Tints each client group header to match its red/green status
         * badge (see HasPayrollBookingsTable::clientApprovalBadge()) —
         * Filament's table Group has no colour/class API of its own, so
         * this keys off the badge's own data attribute via :has() instead.
         */
        .fi-ta-group-header:has([data-payroll-group-status="awaiting"]) {
            background-color: rgb(254 242 242);
        }

        .dark .fi-ta-group-header:has([data-payroll-group-status="awaiting"]) {
            background-color: rgb(69 10 10 / 0.25);
        }

        .fi-ta-group-header:has([data-payroll-group-status="approved"]) {
            background-color: rgb(240 253 244);
        }

        .dark .fi-ta-group-header:has([data-payroll-group-status="approved"]) {
            background-color: rgb(5 46 22 / 0.25);
        }
    </style>

    {{ $this->table }}
</x-filament-panels::page>
