<?php

namespace App\Ai\Tools;

use App\Filament\Support\TodoLinkedRecord;
use App\Models\Industry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reports compliance expiry DATES only — deliberately selects nothing but
 * id/first_name/last_name plus the specific expiry-date columns being
 * checked, since every one of them has a sensitive sibling column on the
 * same row (DBS certificate number, NI number, professional registration
 * number, visa share code, etc). Never selects or returns those, and never
 * eager-loads the documents relation (which exposes storage paths).
 */
class CandidateComplianceExpiry implements Tool
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private const FIELDS = [
        'education' => [
            'safeguarding' => ['safeguarding_expiry_date', 'Safeguarding Training'],
            'benedicts_law' => ['benedicts_law_expiry_date', "Benedict's Law"],
            'dbs' => ['dbs_expiry_date', 'DBS'],
            'right_to_work' => ['right_to_work_expiry_date', 'Right to Work'],
            'visa' => ['visa_expiry_date', 'Visa'],
        ],
        'healthcare' => [
            'dbs' => ['dbs_expiry_date', 'DBS'],
            'right_to_work' => ['right_to_work_expiry_date', 'Right to Work'],
            'visa' => ['visa_expiry_date', 'Visa'],
        ],
    ];

    /**
     * Mirrors EXPIRY_WARNING_DAYS in app/Services/Education/CandidateVettingRequirements.php
     * and app/Services/Healthcare/CandidateVettingRequirements.php — those constants are
     * private, so this is kept in sync manually rather than reused directly.
     *
     * @var array<string, int>
     */
    private const DEFAULT_DAYS = [
        'education' => 3,
        'healthcare' => 14,
    ];

    public function description(): Stringable|string
    {
        return 'Search the current user\'s candidates (for the currently active sector) for compliance requirements '.
            'that are expired or expiring soon — Safeguarding Training, Benedict\'s Law (Education only), DBS, '.
            'Right to Work, or Visa. Returns candidate name and expiry status only — never certificate or document '.
            'numbers. Returns at most 20 matching candidates.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement' => $schema->string()->description('One of: safeguarding, benedicts_law (education only), dbs, right_to_work, visa. Leave blank to check everything that applies to this sector.'),
            'days' => $schema->integer()->description('How many days ahead counts as "expiring soon". Defaults to this sector\'s usual warning window.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $slug = active_industry();
        $candidateModelClass = Industry::candidateModelForSlug($slug ?? '');
        $fields = self::FIELDS[$slug] ?? [];

        if (! $candidateModelClass || $fields === []) {
            return 'No active sector is selected, or compliance tracking is not configured for it.';
        }

        $requested = $request->filled('requirement') ? Str::snake((string) $request['requirement']) : null;
        $toCheck = ($requested && isset($fields[$requested])) ? [$fields[$requested]] : array_values($fields);

        $days = $request->filled('days') ? (int) $request['days'] : self::DEFAULT_DAYS[$slug];
        $threshold = now()->addDays($days);

        $candidates = $candidateModelClass::query()
            ->select(array_merge(['id', 'first_name', 'last_name'], array_column($toCheck, 0)))
            ->where(function ($query) use ($toCheck, $threshold) {
                foreach ($toCheck as [$column, $label]) {
                    $query->orWhere(fn ($q) => $q->whereNotNull($column)->where($column, '<=', $threshold));
                }
            })
            ->limit(20)
            ->get();

        if ($candidates->isEmpty()) {
            return "No candidates have compliance requirements expiring within {$days} days.";
        }

        return $candidates
            ->map(function ($candidate) use ($toCheck, $threshold): string {
                $link = TodoLinkedRecord::candidateLink($candidate);
                $name = $link ? "[{$link['label']}]({$link['url']})" : 'Unknown candidate';

                $lines = collect($toCheck)
                    ->filter(fn ($field) => $candidate->{$field[0]} !== null && $candidate->{$field[0]}->lte($threshold))
                    ->map(function ($field) use ($candidate): string {
                        $date = $candidate->{$field[0]};

                        return $date->isPast()
                            ? "{$field[1]} expired {$date->diffForHumans()}"
                            : "{$field[1]} expires {$date->diffForHumans()}";
                    });

                return "- {$name} — ".$lines->implode(', ');
            })
            ->implode("\n");
    }
}
