<?php

namespace App\Services\Ai;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\CandidateActivity;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientContact;
use App\Models\ConsultantKpiTarget;
use App\Models\EducationApplication;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\Vacancy;
use App\Models\VacancyActivity;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use App\Models\VacancyPlacement;
use PDO;

/**
 * Builds a throwaway, in-memory SQLite database containing only the current
 * user's own already-scoped data — the exact same scoping (industry +
 * visibleToCurrentUser()) every other Ask Assistant tool uses — with
 * compliance/personal-identity columns never selected from the source in
 * the first place.
 *
 * Built fresh on every call, never persisted and never shared between
 * requests or users, so there is no code path by which another tenant's
 * rows, or a restricted column, can ever reach a query run against it.
 * This is what makes it safe to let the AI write its own SQL against the
 * result — the query engine only ever sees data the current user was
 * already allowed to see.
 */
class TenantDataSandbox
{
    /**
     * Bookings beyond this count (most recent first) are left out — a
     * reporting/aggregation aid, not a replacement for search_bookings'
     * exhaustive, paginated search over the full history.
     */
    private const MAX_BOOKINGS = 5000;

    /**
     * Same rationale as MAX_BOOKINGS, just against the much larger
     * per-day/per-period row count.
     */
    private const MAX_BOOKING_DAYS = 20000;

    public function build(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->buildBookingsTable($pdo);
        $this->buildBookingDaysTable($pdo);
        $this->buildClientsTable($pdo);
        $this->buildCandidatesTable($pdo);
        $this->buildVacanciesTable($pdo);
        $this->buildVacancyApplicationsTable($pdo);
        $this->buildCandidateApplicationsTable($pdo);
        $this->buildPlacementsTable($pdo);
        $this->buildVacancyMatchesTable($pdo);
        $this->buildCandidateComplianceTable($pdo);
        $this->buildClientContactsTable($pdo);
        $this->buildTodosTable($pdo);
        $this->buildConsultantKpiTargetsTable($pdo);
        $this->buildActivitiesTable($pdo);

        return $pdo;
    }

    /**
     * One row per booking engagement — its date range and flat rate card,
     * for questions about the booking itself (who, where, when, status).
     * Deliberately has no "how many days" or "margin" answer of its own: a
     * booking's actual worked days live one-per-row in booking_days below,
     * since a multi-day booking is still a single row here and its rate
     * columns don't say which one applies without knowing each day's period
     * (am/pm/full day/hours) — see buildBookingDaysTable().
     */
    private function buildBookingsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE bookings (
            id INTEGER,
            candidate_name TEXT,
            client_name TEXT,
            job_title TEXT,
            status TEXT,
            start_date TEXT,
            end_date TEXT,
            day_rate REAL,
            day_charge_rate REAL,
            half_day_rate REAL,
            half_day_charge_rate REAL,
            hourly_rate REAL,
            hourly_charge_rate REAL,
            consultant_name TEXT
        )');

        $statement = $pdo->prepare(
            'INSERT INTO bookings VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        Booking::query()
            ->visibleToCurrentUser()
            ->with(['client:id,name', 'jobTitle:id,name', 'consultant:id,name', 'candidate'])
            ->orderByDesc('start_date')
            ->limit(self::MAX_BOOKINGS)
            ->get()
            ->each(function (Booking $booking) use ($statement): void {
                $statement->execute([
                    $booking->id,
                    trim("{$booking->candidate?->first_name} {$booking->candidate?->last_name}") ?: null,
                    $booking->client?->name,
                    $booking->jobTitle?->name,
                    $booking->status?->value,
                    $booking->start_date?->toDateString(),
                    $booking->end_date?->toDateString(),
                    $booking->day_rate,
                    $booking->day_charge_rate,
                    $booking->half_day_rate,
                    $booking->half_day_charge_rate,
                    $booking->hourly_rate,
                    $booking->hourly_charge_rate,
                    $booking->consultant?->name,
                ]);
            });
    }

    /**
     * One row per actual worked day (or half-day/hours period) of a
     * booking — the only correct source for "how many days" or margin
     * questions, since a booking spanning a week is still one row in
     * `bookings` but five here, and each row's pay/charge rate is resolved
     * per its own period via BookingDay::payRate()/chargeRate(), the exact
     * same period-to-rate-column lookup BookingRevenuePeriodCalculator uses
     * for the app's own margin reporting — so totals computed from this
     * table match what the app shows elsewhere. Cancelled days are excluded
     * entirely, matching that same calculator's own scoping.
     */
    private function buildBookingDaysTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE booking_days (
            id INTEGER,
            booking_id INTEGER,
            date TEXT,
            period TEXT,
            candidate_name TEXT,
            client_name TEXT,
            job_title TEXT,
            consultant_name TEXT,
            status TEXT,
            pay_rate REAL,
            charge_rate REAL,
            margin REAL
        )');

        $statement = $pdo->prepare('INSERT INTO booking_days VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        BookingDay::query()
            ->whereNull('cancelled_at')
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser())
            ->with(['booking.client:id,name', 'booking.jobTitle:id,name', 'booking.consultant:id,name', 'booking.candidate'])
            ->orderByDesc('date')
            ->limit(self::MAX_BOOKING_DAYS)
            ->get()
            ->each(function (BookingDay $day) use ($statement): void {
                $payRate = $day->payRate();
                $chargeRate = $day->chargeRate();

                $statement->execute([
                    $day->id,
                    $day->booking_id,
                    $day->date?->toDateString(),
                    $day->period?->value,
                    trim("{$day->booking?->candidate?->first_name} {$day->booking?->candidate?->last_name}") ?: null,
                    $day->booking?->client?->name,
                    $day->booking?->jobTitle?->name,
                    $day->booking?->consultant?->name,
                    $day->payrollStatus()->value,
                    $payRate,
                    $chargeRate,
                    $payRate !== null && $chargeRate !== null ? $chargeRate - $payRate : null,
                ]);
            });
    }

    private function buildClientsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE clients (
            id INTEGER,
            name TEXT,
            client_type TEXT,
            city TEXT,
            county TEXT,
            postcode TEXT,
            consultant_name TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO clients VALUES (?, ?, ?, ?, ?, ?, ?)');

        Client::query()
            ->visibleForReporting()
            ->where('industry_id', active_industry_id())
            ->with(['clientType:id,name', 'consultant:id,name'])
            ->get()
            ->each(function (Client $client) use ($statement): void {
                $statement->execute([
                    $client->id,
                    $client->name,
                    $client->clientType?->name,
                    $client->city,
                    $client->county,
                    $client->postcode,
                    $client->consultant?->name,
                ]);
            });
    }

    /**
     * Deliberately mirrors SearchCandidates' own restriction — first name,
     * last name, status, qualification, region, and rating only. Never a
     * DBS number, NI number, date of birth, address, or right-to-work
     * document, no matter what the AI's generated SQL asks for, because
     * those columns are never selected from the source models at all.
     */
    private function buildCandidatesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE candidates (
            id INTEGER,
            first_name TEXT,
            last_name TEXT,
            status TEXT,
            qualification TEXT,
            city TEXT,
            county TEXT,
            postcode TEXT,
            average_rating REAL,
            ratings_count INTEGER
        )');

        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModel) {
            return;
        }

        $supportsQualification = method_exists($candidateModel, 'qualification');

        $statement = $pdo->prepare('INSERT INTO candidates VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $candidateModel::query()
            ->select(array_filter([
                'id', 'first_name', 'last_name', 'city', 'county', 'postcode',
                'average_rating', 'ratings_count',
                $supportsQualification ? 'qualification_id' : null,
            ]))
            ->with(array_filter(['latestStatus.status', $supportsQualification ? 'qualification' : null]))
            ->visibleToCurrentUser()
            ->get()
            ->each(function ($candidate) use ($statement, $supportsQualification): void {
                $statement->execute([
                    $candidate->id,
                    $candidate->first_name,
                    $candidate->last_name,
                    $candidate->latestStatus?->status?->name,
                    $supportsQualification ? $candidate->qualification?->name : null,
                    $candidate->city,
                    $candidate->county,
                    $candidate->postcode,
                    $candidate->average_rating,
                    $candidate->ratings_count,
                ]);
            });
    }

    private function buildVacanciesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE vacancies (
            id INTEGER,
            title TEXT,
            client_name TEXT,
            job_title TEXT,
            status TEXT,
            positions_available INTEGER,
            is_temp INTEGER,
            salary_min REAL,
            salary_max REAL,
            day_rate_min REAL,
            day_rate_max REAL,
            open_for_applications INTEGER,
            consultant_name TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO vacancies VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        Vacancy::query()
            ->visibleToCurrentUser()
            ->with(['client:id,name', 'jobTitle:id,name', 'jobStatus:id,name', 'consultant:id,name'])
            ->get()
            ->each(function (Vacancy $vacancy) use ($statement): void {
                $statement->execute([
                    $vacancy->id,
                    $vacancy->title,
                    $vacancy->client?->name,
                    $vacancy->jobTitle?->name,
                    $vacancy->jobStatus?->name,
                    $vacancy->positions_available,
                    $vacancy->isTemp() ? 1 : 0,
                    $vacancy->salary_min,
                    $vacancy->salary_max,
                    $vacancy->day_rate_min,
                    $vacancy->day_rate_max,
                    $vacancy->open_for_applications ? 1 : 0,
                    $vacancy->consultant?->name,
                ]);
            });
    }

    /**
     * One row per candidate who applied to a specific vacancy — scoped
     * entirely through the vacancy's own visibleToCurrentUser()/industry
     * check, since that's the record actually gating who may see it.
     */
    private function buildVacancyApplicationsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE vacancy_applications (
            id INTEGER,
            vacancy_title TEXT,
            candidate_name TEXT,
            client_name TEXT,
            shortlisted INTEGER,
            applied_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO vacancy_applications VALUES (?, ?, ?, ?, ?, ?)');

        VacancyApplication::query()
            ->whereHas('vacancy', fn ($query) => $query->visibleToCurrentUser())
            ->with(['vacancy:id,title,client_id', 'vacancy.client:id,name', 'candidate'])
            ->get()
            ->each(function (VacancyApplication $application) use ($statement): void {
                $statement->execute([
                    $application->id,
                    $application->vacancy?->title,
                    trim("{$application->candidate?->first_name} {$application->candidate?->last_name}") ?: null,
                    $application->vacancy?->client?->name,
                    $application->isShortlisted() ? 1 : 0,
                    $application->created_at?->toDateTimeString(),
                ]);
            });
    }

    /**
     * The candidate onboarding/signup application form's progress only —
     * status, which step they're on, and whether it's complete. Deliberately
     * never selects any of its terms/security-clearance/childcare-act
     * declaration fields or free-text detail columns, its parsed CV data, or
     * its email/token — those are compliance and personal-identity adjacent
     * in exactly the way the candidates table already excludes, so they're
     * never read from the source here either.
     *
     * The underlying model differs per industry — EducationApplication,
     * HealthcareApplication, and the generic CandidateApplication all have a
     * different shape and relation name to their candidate — so this
     * resolves the one for the active industry the same way
     * buildCandidatesTable() resolves its candidate model.
     */
    private function buildCandidateApplicationsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE candidate_applications (
            id INTEGER,
            candidate_name TEXT,
            status TEXT,
            current_step INTEGER,
            completed INTEGER,
            created_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO candidate_applications VALUES (?, ?, ?, ?, ?, ?)');

        match (active_industry()) {
            'education' => EducationApplication::query()
                ->whereHas('educationCandidate', fn ($query) => $query->visibleToCurrentUser())
                ->with('educationCandidate:id,first_name,last_name')
                ->get()
                ->each(fn (EducationApplication $application) => $statement->execute([
                    $application->id,
                    trim("{$application->educationCandidate?->first_name} {$application->educationCandidate?->last_name}") ?: null,
                    $application->status,
                    $application->current_step,
                    $application->completed_at !== null ? 1 : 0,
                    $application->created_at?->toDateTimeString(),
                ])),
            'healthcare' => HealthcareApplication::query()
                ->whereHasMorph('candidate', [HealthcareCandidate::class], fn ($query) => $query->visibleToCurrentUser())
                ->with('candidate')
                ->get()
                ->each(fn (HealthcareApplication $application) => $statement->execute([
                    $application->id,
                    trim("{$application->candidate?->first_name} {$application->candidate?->last_name}") ?: null,
                    $application->status,
                    null,
                    $application->completed_at !== null ? 1 : 0,
                    $application->created_at?->toDateTimeString(),
                ])),
            default => CandidateApplication::query()
                ->whereHas('candidate', fn ($query) => $query->visibleToCurrentUser())
                ->with('candidate:id,first_name,last_name')
                ->get()
                ->each(fn (CandidateApplication $application) => $statement->execute([
                    $application->id,
                    trim("{$application->candidate?->first_name} {$application->candidate?->last_name}") ?: null,
                    $application->status,
                    null,
                    $application->completed_at !== null ? 1 : 0,
                    $application->created_at?->toDateTimeString(),
                ])),
        };
    }

    /**
     * One row per candidate placed into a vacancy — scoped through the
     * vacancy's own visibility check, same as vacancy_applications.
     */
    private function buildPlacementsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE placements (
            id INTEGER,
            vacancy_title TEXT,
            candidate_name TEXT,
            client_name TEXT,
            actual_salary REAL,
            placed_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO placements VALUES (?, ?, ?, ?, ?, ?)');

        VacancyPlacement::query()
            ->whereHas('vacancy', fn ($query) => $query->visibleToCurrentUser())
            ->with(['vacancy:id,title,client_id', 'vacancy.client:id,name', 'candidate'])
            ->get()
            ->each(function (VacancyPlacement $placement) use ($statement): void {
                $statement->execute([
                    $placement->id,
                    $placement->vacancy?->title,
                    trim("{$placement->candidate?->first_name} {$placement->candidate?->last_name}") ?: null,
                    $placement->vacancy?->client?->name,
                    $placement->actual_salary,
                    $placement->placed_at?->toDateTimeString(),
                ]);
            });
    }

    /**
     * Existing candidate-to-vacancy match scores (see the vacancy_matches
     * tool for the same underlying data) — for "average match score",
     * "how many strong matches" type aggregate questions this sandbox's
     * fixed tool can't answer on its own. Scored 0-100, never recomputed
     * here.
     */
    private function buildVacancyMatchesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE vacancy_matches (
            id INTEGER,
            vacancy_title TEXT,
            candidate_name TEXT,
            client_name TEXT,
            score INTEGER,
            matched_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO vacancy_matches VALUES (?, ?, ?, ?, ?, ?)');

        VacancyCandidateMatch::query()
            ->whereHas('vacancy', fn ($query) => $query->visibleToCurrentUser())
            ->with(['vacancy:id,title,client_id', 'vacancy.client:id,name', 'candidate'])
            ->get()
            ->each(function (VacancyCandidateMatch $match) use ($statement): void {
                $statement->execute([
                    $match->id,
                    $match->vacancy?->title,
                    trim("{$match->candidate?->first_name} {$match->candidate?->last_name}") ?: null,
                    $match->vacancy?->client?->name,
                    $match->score,
                    $match->created_at?->toDateTimeString(),
                ]);
            });
    }

    /**
     * Mirrors the candidate_compliance_expiry tool's own field list and
     * restriction exactly — expiry DATES only, for the sector-specific
     * requirements that have one (Safeguarding/Benedict's Law/DBS/Right to
     * Work/Visa). Never touches the sibling certificate/document-number
     * columns on the same row, and never touches the separate generic
     * ComplianceItem/CandidateComplianceValue builder at all, since that
     * system stores arbitrary typed values (including free-text ones) with
     * no equivalent guarantee that a "value" column is never a document
     * number — safer to leave it out of the sandbox entirely than risk
     * exposing one.
     */
    private function buildCandidateComplianceTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE candidate_compliance (
            id INTEGER,
            candidate_name TEXT,
            requirement TEXT,
            expiry_date TEXT,
            status TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO candidate_compliance VALUES (?, ?, ?, ?, ?)');

        $slug = active_industry();
        $candidateModel = Industry::candidateModelForSlug($slug ?? '');

        /** @var array<string, array<string, array{0: string, 1: string}>> */
        $fieldsBySlug = [
            'education' => [
                'safeguarding_expiry_date' => 'Safeguarding Training',
                'benedicts_law_expiry_date' => "Benedict's Law",
                'dbs_expiry_date' => 'DBS',
                'right_to_work_expiry_date' => 'Right to Work',
                'visa_expiry_date' => 'Visa',
            ],
            'healthcare' => [
                'dbs_expiry_date' => 'DBS',
                'right_to_work_expiry_date' => 'Right to Work',
                'visa_expiry_date' => 'Visa',
            ],
        ];

        $fields = $fieldsBySlug[$slug] ?? [];

        if (! $candidateModel || $fields === []) {
            return;
        }

        $candidateModel::query()
            ->select(array_merge(['id', 'first_name', 'last_name'], array_keys($fields)))
            ->visibleToCurrentUser()
            ->get()
            ->each(function ($candidate) use ($statement, $fields): void {
                foreach ($fields as $column => $label) {
                    $expiryDate = $candidate->{$column};

                    if ($expiryDate === null) {
                        continue;
                    }

                    $statement->execute([
                        $candidate->id,
                        trim("{$candidate->first_name} {$candidate->last_name}") ?: null,
                        $label,
                        $expiryDate->toDateString(),
                        $expiryDate->isPast() ? 'expired' : 'valid',
                    ]);
                }
            });
    }

    /**
     * Contact people at clients — name, job title, and whether they're the
     * main contact, for "who's the contact at client X" type questions.
     * Deliberately never selects email or phone, the same restriction
     * already applied to candidates.
     */
    private function buildClientContactsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE client_contacts (
            id INTEGER,
            contact_name TEXT,
            job_title TEXT,
            client_name TEXT,
            is_main_contact INTEGER
        )');

        $statement = $pdo->prepare('INSERT INTO client_contacts VALUES (?, ?, ?, ?, ?)');

        ClientContact::query()
            ->whereHas('client', fn ($query) => $query
                ->visibleForReporting()
                ->where('industry_id', active_industry_id()))
            ->with(['client:id,name', 'clientContactJobTitle:id,name'])
            ->get()
            ->each(function (ClientContact $contact) use ($statement): void {
                $statement->execute([
                    $contact->id,
                    trim("{$contact->first_name} {$contact->last_name}") ?: null,
                    $contact->clientContactJobTitle?->name,
                    $contact->client?->name,
                    $contact->main_contact ? 1 : 0,
                ]);
            });
    }

    /**
     * Every user's todos are their own — TodoItem defines no broader
     * visibility of its own (no admin-sees-all), so this mirrors that
     * exactly via its own ownedByCurrentUser() scope rather than inventing
     * wider access the app doesn't otherwise grant.
     */
    private function buildTodosTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE todos (
            id INTEGER,
            name TEXT,
            priority TEXT,
            completed INTEGER,
            linked_to TEXT,
            created_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO todos VALUES (?, ?, ?, ?, ?, ?)');

        TodoItem::query()
            ->ownedByCurrentUser()
            ->with('model')
            ->get()
            ->each(function (TodoItem $todo) use ($statement): void {
                $statement->execute([
                    $todo->id,
                    $todo->name,
                    $todo->priority?->value,
                    $todo->isComplete() ? 1 : 0,
                    $todo->linkedRecordLabel(),
                    $todo->created_at?->toDateTimeString(),
                ]);
            });
    }

    /**
     * One row per consultant per industry. Mirrors consultant_performance's
     * own admin-vs-self restriction: a non-admin only ever sees their own
     * target row, since KPI targets are performance data in exactly the
     * same sense margin/revenue figures are.
     */
    private function buildConsultantKpiTargetsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE consultant_kpi_targets (
            id INTEGER,
            consultant_name TEXT,
            gp_target INTEGER,
            candidate_days_target INTEGER,
            working_candidates_target INTEGER,
            clients_booked_target INTEGER,
            rebook_rate_target REAL
        )');

        $statement = $pdo->prepare('INSERT INTO consultant_kpi_targets VALUES (?, ?, ?, ?, ?, ?, ?)');

        ConsultantKpiTarget::query()
            ->where('industry_id', active_industry_id())
            ->when(
                ! auth()->user()?->isAdmin(),
                fn ($query) => $query->where('user_id', auth()->id())
            )
            ->with('user:id,name')
            ->get()
            ->each(function (ConsultantKpiTarget $target) use ($statement): void {
                $statement->execute([
                    $target->id,
                    $target->user?->name,
                    $target->gp_target,
                    $target->candidate_days_target,
                    $target->working_candidates_target,
                    $target->clients_booked_target,
                    $target->rebook_rate_target,
                ]);
            });
    }

    /**
     * Notes/calls/meetings etc. logged against candidates, clients, and
     * vacancies. Each source is filtered via whereHasMorph against the same
     * visibleToCurrentUser()/industry scope as its owning record, so an
     * activity can only ever appear here if the record it's logged against
     * would itself be visible to the current user.
     */
    private function buildActivitiesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE activities (
            id INTEGER,
            category TEXT,
            type TEXT,
            note TEXT,
            body TEXT,
            user_name TEXT,
            related_to TEXT,
            created_at TEXT
        )');

        $statement = $pdo->prepare('INSERT INTO activities VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        $candidateModel = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($candidateModel) {
            CandidateActivity::query()
                ->whereHasMorph('model', [$candidateModel], fn ($query) => $query->visibleToCurrentUser())
                ->with(['user:id,name', 'model'])
                ->get()
                ->each(function (CandidateActivity $activity) use ($statement): void {
                    $statement->execute([
                        $activity->id,
                        'candidate',
                        $activity->type?->value,
                        $activity->note,
                        $activity->body,
                        $activity->user?->name,
                        trim("{$activity->model?->first_name} {$activity->model?->last_name}") ?: null,
                        $activity->created_at?->toDateTimeString(),
                    ]);
                });
        }

        ClientActivity::query()
            ->whereHasMorph('model', [Client::class], fn ($query) => $query
                ->visibleForReporting()
                ->where('industry_id', active_industry_id()))
            ->with(['user:id,name', 'model:id,name'])
            ->get()
            ->each(function (ClientActivity $activity) use ($statement): void {
                $statement->execute([
                    $activity->id,
                    'client',
                    $activity->type?->value,
                    $activity->note,
                    $activity->body,
                    $activity->user?->name,
                    $activity->model?->name,
                    $activity->created_at?->toDateTimeString(),
                ]);
            });

        VacancyActivity::query()
            ->whereHasMorph('model', [Vacancy::class], fn ($query) => $query->visibleToCurrentUser())
            ->with(['user:id,name', 'model:id,title'])
            ->get()
            ->each(function (VacancyActivity $activity) use ($statement): void {
                $statement->execute([
                    $activity->id,
                    'vacancy',
                    $activity->type?->value,
                    $activity->note,
                    $activity->body,
                    $activity->user?->name,
                    $activity->model?->title,
                    $activity->created_at?->toDateTimeString(),
                ]);
            });
    }
}
