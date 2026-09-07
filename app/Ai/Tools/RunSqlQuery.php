<?php

namespace App\Ai\Tools;

use App\Services\Ai\TenantDataSandbox;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use PDO;
use Stringable;
use Throwable;

class RunSqlQuery implements Tool
{
    /**
     * Applied to every query regardless of what the model asks for, so no
     * generated SQL — however it aggregates or joins — can ever return more
     * than this many rows.
     */
    private const ROW_LIMIT = 200;

    private const FORBIDDEN_KEYWORDS = [
        'insert', 'update', 'delete', 'drop', 'alter', 'attach', 'pragma',
        'create', 'replace', 'vacuum', 'reindex',
    ];

    public function description(): Stringable|string
    {
        return "Run a read-only SQLite SELECT query against a snapshot of the current user's own data, for ".
            'analysis, aggregation, or reporting questions the other tools can\'t answer directly (counts, sums, '.
            'averages, group-by breakdowns, joins across bookings/clients/candidates/vacancies). The snapshot has '.
            "fourteen tables:\n".
            'bookings(id, candidate_name, client_name, job_title, status, start_date, end_date, day_rate, '.
            "day_charge_rate, half_day_rate, half_day_charge_rate, hourly_rate, hourly_charge_rate, consultant_name)\n".
            'booking_days(id, booking_id, date, period, candidate_name, client_name, job_title, consultant_name, '.
            "status, pay_rate, charge_rate, margin)\n".
            "clients(id, name, client_type, city, county, postcode, consultant_name)\n".
            "candidates(id, first_name, last_name, status, qualification, city, county, postcode, average_rating, ratings_count)\n".
            'vacancies(id, title, client_name, job_title, status, positions_available, is_temp, salary_min, '.
            "salary_max, day_rate_min, day_rate_max, open_for_applications, consultant_name)\n".
            "vacancy_applications(id, vacancy_title, candidate_name, client_name, shortlisted, applied_at)\n".
            "candidate_applications(id, candidate_name, status, current_step, completed, created_at)\n".
            "placements(id, vacancy_title, candidate_name, client_name, actual_salary, placed_at)\n".
            "vacancy_matches(id, vacancy_title, candidate_name, client_name, score, matched_at)\n".
            "candidate_compliance(id, candidate_name, requirement, expiry_date, status)\n".
            "client_contacts(id, contact_name, job_title, client_name, is_main_contact)\n".
            "todos(id, name, priority, completed, linked_to, created_at)\n".
            'consultant_kpi_targets(id, consultant_name, gp_target, candidate_days_target, '.
            "working_candidates_target, clients_booked_target, rebook_rate_target)\n".
            "activities(id, category, type, note, body, user_name, related_to, created_at)\n".
            'bookings has one row per booking engagement (its date range and rate card) — good for "which '.
            'bookings", "when", "for which client/candidate" questions. It has NO correct answer for "how many '.
            'days" or margin/revenue/cost: a booking spanning a week is still one row here, and its rate columns '.
            'don\'t say which one applied on which day. '.
            'booking_days has one row per actual worked day/half-day/hours period (cancelled days already '.
            'excluded) with that period\'s real pay_rate, charge_rate, and margin (charge_rate - pay_rate) already '.
            'resolved — this is the ONLY correct table for "how many booking days", "margin", "revenue", or "cost" '.
            'questions, whether for one consultant or grouped/summed across many. Never estimate days or margin '.
            'from the bookings table\'s date range or rate columns — always use booking_days for those. '.
            'The same applies to "how many bookings" for a specific date or date range — whether stated outright '.
            '("today", "this week") or carried over from earlier in the conversation (e.g. a follow-up "how many '.
            'does the whole company have" right after a "today" question still means today): '.
            'a booking\'s start_date/end_date is only its overall bounding range, not proof it\'s actually scheduled '.
            'on every day inside it — some of those days may never have been scheduled at all, or were cancelled. '.
            'Counting WHERE start_date <= X AND end_date >= X over-counts. Instead use '.
            'COUNT(DISTINCT booking_id) FROM booking_days WHERE date is in that range — only "how many bookings '.
            'do we have" with no date/range mentioned at all is fine as a plain COUNT(*) FROM bookings. '.
            'vacancies is one row per job vacancy — is_temp/open_for_applications are 1 or 0; salary_min/max apply '.
            'to permanent vacancies and day_rate_min/max to temp ones (the other pair is null). '.
            'vacancy_applications is one row per candidate who applied to a vacancy (shortlisted is 1 or 0) — for '.
            '"who applied", "how many applicants", "how many shortlisted" questions; this is a different, more '.
            'specific concept than candidate_applications below. '.
            'candidate_applications is the candidate\'s own onboarding/signup form progress — status '.
            '(pending/completed/expired), current_step (education sector only, otherwise null), and whether it\'s '.
            'complete. It never contains the form\'s declaration answers, parsed CV data, email, or token — only '.
            'ever discuss progress/status from here, never any declaration content, which is never available. '.
            'placements is one row per candidate successfully placed into a vacancy, with the actual agreed salary '.
            'and placement date — for "how many placements", "placement revenue/fee" type questions (fee amount '.
            'itself isn\'t stored here, only actual_salary). '.
            'vacancy_matches is existing candidate-to-vacancy match scores (0-100), never recomputed here — same '.
            'restriction as the vacancy_matches tool: a match only exists once matching has actually been run for '.
            'that vacancy. '.
            'candidate_compliance has one row per candidate per tracked compliance requirement that has an expiry '.
            'date, "status" is expired or valid as of today — this is the only compliance/requirement data '.
            'available; it never contains a certificate or document number, and no other compliance detail exists '.
            'anywhere in this sandbox. '.
            'client_contacts is people at a client — never their email or phone, only name, job title, and '.
            'whether they\'re the main contact. '.
            'todos is the CURRENT USER\'S OWN todo items only — every user, including admins, only ever sees their '.
            'own, since that mirrors the app\'s own todo visibility; never imply another user\'s todos are visible. '.
            'consultant_kpi_targets is one row per consultant\'s performance targets — a non-admin only ever sees '.
            'their own row here, same restriction as consultant_performance; join/compare against booking_days for '.
            'actual figures, these columns are targets only, never actuals. '.
            'activities is every logged call/note/meeting/email/etc against a candidate, client, or vacancy — '.
            '"category" is candidate/client/vacancy, "type" is the activity type (call/note/meeting/email/etc), '.
            '"user_name" is who logged it, and "related_to" is the candidate/client/vacancy name it was logged '.
            "against.\n".
            'The candidates, candidate_applications, and candidate_compliance tables never contain DBS numbers, '.
            'National Insurance numbers, dates of birth, addresses, right-to-work documents, onboarding-form '.
            'declaration content, or any certificate/document number — those are never available here or anywhere '.
            'else, and client_contacts never contains an email or phone number. '.
            'Only a single SELECT statement is allowed — no INSERT/UPDATE/DELETE/DDL, and no multiple statements. '.
            'Results are capped at '.self::ROW_LIMIT.' rows regardless of the query.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()
                ->description('A single SQLite SELECT statement against the sandbox tables — see this tool\'s own description for the full list and their columns.')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['sql']);

        if ($error = $this->rejectionReasonFor($sql)) {
            return "Query rejected: {$error}";
        }

        try {
            $pdo = app(TenantDataSandbox::class)->build();
            $statement = $pdo->query('SELECT * FROM ('.rtrim($sql, "; \t\n\r\0\x0B").') LIMIT '.self::ROW_LIMIT);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return "Query failed: {$e->getMessage()}";
        }

        if (empty($rows)) {
            return 'Query returned no rows.';
        }

        return $this->formatAsTable($rows);
    }

    private function rejectionReasonFor(string $sql): ?string
    {
        if ($sql === '') {
            return 'no SQL was provided.';
        }

        if (! preg_match('/^select\b/i', $sql)) {
            return 'only SELECT statements are allowed.';
        }

        if (str_contains(rtrim($sql, "; \t\n\r\0\x0B"), ';')) {
            return 'only a single statement is allowed.';
        }

        $keywordPattern = '/\b('.implode('|', self::FORBIDDEN_KEYWORDS).')\b/i';

        if (preg_match($keywordPattern, $sql)) {
            return 'only read-only SELECT queries are allowed.';
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function formatAsTable(array $rows): string
    {
        $columns = array_keys($rows[0]);

        $header = '| '.implode(' | ', $columns).' |';
        $divider = '| '.implode(' | ', array_fill(0, count($columns), '---')).' |';

        $body = collect($rows)
            ->map(fn (array $row): string => '| '.implode(' | ', array_map(
                fn ($value): string => $value === null ? '' : str_replace('|', '\\|', (string) $value),
                $row
            )).' |')
            ->implode("\n");

        $footer = count($rows) === self::ROW_LIMIT
            ? "\n\n(Capped at ".self::ROW_LIMIT.' rows — narrow the query with a WHERE clause or aggregate for a fuller picture.)'
            : '';

        return "{$header}\n{$divider}\n{$body}{$footer}";
    }
}
