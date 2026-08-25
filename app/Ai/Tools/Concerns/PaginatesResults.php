<?php

namespace App\Ai\Tools\Concerns;

use Laravel\Ai\Tools\Request;

trait PaginatesResults
{
    /**
     * Reads the "offset" schema param every paginated Search tool exposes —
     * how many matching results to skip, for fetching a subsequent page of
     * the same search.
     */
    protected function offset(Request $request): int
    {
        return max(0, (int) ($request['offset'] ?? 0));
    }

    /**
     * A trailing note telling the agent (and, once relayed, the user) how
     * many results were shown out of how many matched, and how to ask for
     * more. Deliberately worded so DataAssistant's instructions can tell the
     * model to preserve it verbatim, the same way it preserves links.
     */
    protected function paginationFooter(int $shown, int $offset, int $total): string
    {
        $seenSoFar = $offset + $shown;

        if ($seenSoFar >= $total) {
            return '';
        }

        $remaining = $total - $seenSoFar;

        return "\n\n_Showing {$shown} of {$total} — {$remaining} more match. Ask to see the next {$this->perPage} to continue._";
    }
}
