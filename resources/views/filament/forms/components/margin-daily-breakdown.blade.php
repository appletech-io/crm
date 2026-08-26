@if (empty($rows))
    <p class="text-sm text-gray-500 dark:text-gray-400">No scheduled days yet.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-1 pr-4 font-medium">Date</th>
                    <th class="py-1 pr-4 font-medium">Period</th>
                    <th class="py-1 pr-4 font-medium">Pay</th>
                    <th class="py-1 pr-4 font-medium">Charge</th>
                    <th class="py-1 font-medium">Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $row['date'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $row['periodLabel'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $row['payLabel'] }}</td>
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-200">{{ $row['chargeLabel'] }}</td>
                        <td class="py-1 text-gray-700 dark:text-gray-200">{{ $row['marginLabel'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
