<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="text-xs text-gray-500 uppercase dark:text-gray-400">
                <th class="py-1 pr-4">Date</th>
                <th class="py-1 pr-4">Consultant</th>
                <th class="py-1 pr-4">Relates to</th>
                <th class="py-1 pr-4">Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($activities as $activity)
                <tr class="border-t border-gray-100 dark:border-white/5">
                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $activity['created_at']->format('d M Y, H:i') }}</td>
                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $activity['consultant'] }}</td>
                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $activity['kind'] }}: {{ $activity['subject'] }}</td>
                    <td class="py-1.5 pr-4">{{ $activity['note'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-3 text-sm text-gray-500 dark:text-gray-400">
                        Nothing logged this month.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
