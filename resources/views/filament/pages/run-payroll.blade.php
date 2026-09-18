<x-filament-panels::page>
    {{ $this->table }}

    {{-- RunPayroll-only: ViewPayroll (the personal "Timesheets" chase list every
    consultant can see) shares this same template but has no business showing
    company-wide payroll provider errors to a non-admin. --}}
    @if (method_exists($this, 'hasPayrollProviderConfigured') && $this->hasPayrollProviderConfigured())
        @php($providerErrors = $this->bookingProviderErrors())

        <x-filament::section
            heading="Payroll Provider Errors"
            :description="$providerErrors->isEmpty()
                ? 'No placements or timesheets are currently failing to send.'
                : 'These bookings failed to send to your payroll provider. Fix the underlying issue and the next payroll run will resend them automatically.'"
            class="mt-6"
        >
            @if ($providerErrors->isNotEmpty())
                <div class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($providerErrors as $providerError)
                        <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                @if ($providerError->booking)
                                    <a
                                        href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $providerError->booking_id]) }}"
                                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        Booking #{{ $providerError->booking_id }} — {{ $providerError->booking->client?->name ?? 'Unknown client' }}
                                    </a>
                                @else
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Booking #{{ $providerError->booking_id }} (deleted)
                                    </span>
                                @endif

                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $providerError->updated_at->diffForHumans() }}
                                </span>
                            </div>

                            <ul class="list-disc space-y-0.5 pl-5 text-sm text-danger-600 dark:text-danger-400">
                                @foreach ($providerError->errors as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
