<x-filament-widgets::widget>
    <x-filament::section heading="Top Clients by Revenue">
        @php($rows = $this->rows())

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">Client</th>
                        <th style="text-align: right; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">Bookings</th>
                        <th style="text-align: right; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">Revenue</th>
                        <th style="text-align: right; padding: 0.5rem; white-space: nowrap; border-bottom: 1px solid rgba(120, 120, 120, 0.25);">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-bottom: 1px solid rgba(120, 120, 120, 0.12);">
                            <td style="padding: 0.5rem; white-space: nowrap; font-weight: 500;">{{ $row['clientName'] }}</td>
                            <td style="padding: 0.5rem; text-align: right;">{{ $row['bookings'] }}</td>
                            <td style="padding: 0.5rem; text-align: right;">£{{ number_format($row['revenue'], 2) }}</td>
                            <td style="padding: 0.5rem; text-align: right;">£{{ number_format($row['margin'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 1rem; text-align: center;">
                                No bookings in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
