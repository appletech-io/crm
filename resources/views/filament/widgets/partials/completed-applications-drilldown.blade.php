<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="text-xs text-gray-500 uppercase dark:text-gray-400">
                <th class="py-1 pr-4">Candidate</th>
                <th class="py-1 pr-4">Consultant</th>
                <th class="py-1 pr-4">Completed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($candidates as $candidate)
                <tr class="border-t border-gray-100 dark:border-white/5">
                    <td class="py-1.5 pr-4">
                        <a href="{{ $candidate['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">
                            {{ $candidate['name'] }}
                        </a>
                    </td>
                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $candidate['consultant'] }}</td>
                    <td class="py-1.5 pr-4 whitespace-nowrap">{{ $candidate['completed_at']?->format('d M Y, H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-3 text-sm text-gray-500 dark:text-gray-400">
                        No applications completed this month.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
