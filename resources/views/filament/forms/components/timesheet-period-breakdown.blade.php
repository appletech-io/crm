@if (empty($days))
    <p class="text-sm text-gray-500 dark:text-gray-400">No days in this period.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-1 pr-4 font-medium">Date</th>
                    <th class="py-1 pr-4 font-medium">Session</th>
                    <th class="py-1 pr-4 font-medium">Pay</th>
                    <th class="py-1 pr-4 font-medium">Approved</th>
                    <th class="py-1 pr-4 font-medium">Approved By</th>
                    <th class="py-1 font-medium">Sent to Payroll</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $day)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $day['date'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $day['period'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $day['pay'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $day['approved_at'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $day['approved_by'] }}</td>
                        <td class="py-1 text-gray-700 dark:text-gray-200">{{ $day['sent_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
