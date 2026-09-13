# Plan: "No Bookings" dashboard + Job Pipeline Flow

Status: draft for review — no code written yet.
Scope: `app/Filament/Pages/Dashboard.php` and its dashboard classes, plus one new widget.

## 1. What triggers the new view

Applebough already has the exact toggle this depends on. `company_industry.uses_bookings`
(edited via `CompanyFeaturesForm`, read via the `active_industry_uses_bookings()` helper in
`app/helpers.php`) marks a sector where an agency only places permanent/contract roles —
"sector" and "industry" are the same dimension in this codebase (`Industry` model), so there's
only one flag to check, not two.

The gap: `Dashboard::__construct()` currently picks a dashboard class by **industry name alone**
(`ItDashboard` for `it`, `ConstructionDashboard` for `construction`, etc.), not by the bookings
flag. `ConstructionDashboard` always renders the booking-based widgets even if a company has
switched Bookings off for Construction — confirmed by the existing test
`ConstructionItDashboardTest`, which literally asserts *"the dashboard resolves
ConstructionDashboard for the construction industry, reusing the booking-based widgets"* with no
regard for the toggle. IT is currently the only sector hardcoded to the no-bookings treatment.

**Change:** resolve the dashboard by `active_industry_uses_bookings()` first, industry name
second:

```
no active industry          -> NoSectorDashboard (unchanged)
active industry, bookings ON  -> existing per-industry class (Education/Healthcare/Construction/…)
active industry, bookings OFF -> NoBookingsDashboard (generalised from today's ItDashboard)
```

`ItDashboard` becomes the `NoBookingsDashboard` case rather than an IT special case — IT keeps
working exactly as it does today (it always has bookings off), but Construction, Education or
Healthcare get the same treatment the moment a company flips their toggle off, with no per-industry
code needed. Compliance-only dashboards (`Compliance{Industry}Dashboard`) are handled separately —
see Part 2.

### Job-level status, not per-candidate — deliberate, confirmed

Worth being explicit about, since the reference screenshot (Vincere) can read either way: in
Vincere, those tabs (Shortlisted → Sent → 1st Interview → 2nd Interview → Offers → Placed →
Renewals) are a **candidate's progress on one specific job application** — a property of the
candidate-vacancy pairing, not the job record. A job there has its own separate lifecycle status
(Live/On Hold/Filled/Closed) shown elsewhere; the same candidate can be "Sent" on one job and
"Interviewing" on another simultaneously.

This plan is deliberately **not** that. Every segment here is `JobStatus` — a property of the
`Vacancy` itself (Open/On Hold/Filled/Cancelled, or whatever a company renames them to). A
company that wants their job statuses named "Shortlisted/Sent/Interview/Offer/Placed" gets that
label set, but it's still describing the job's own state, not any individual candidate's
progress against it. The true Vincere-style per-application pipeline would need a genuine status
field on `VacancyApplication` (which today only has `shortlisted_at` — nothing for Sent/Interview/
Offer/Placed) — a materially larger addition than anything else in this plan, and explicitly out
of scope here.

## 2. Layout: KPIs (unchanged shape) + new Job Pipeline Flow

`NoBookingsDashboard::getWidgets()` keeps `ItPlacementsOverview` (rename candidate:
`ConsultantPlacementsOverview`, since it's no longer IT-specific — view file renames with it) and
`GenericConsultantKpiOverview`, then adds one new full-width widget beneath them:

```
NoBookingsDashboard::getWidgets() = [
    ConsultantPlacementsOverview::class,   // was ItPlacementsOverview
    GenericConsultantKpiOverview::class,
    JobPipelineFlow::class,                // new
]
```

`JobPipelineFlow` is a new Livewire/Filament widget (`columnSpan: 'full'`) rendering the
chevron-style flow from the reference screenshot, but as an aggregate summary rather than a
per-record table:

```
[ Jobs ]  [ Candidates ▾ ]  >  [ Stage 1 ]  >  [ Stage 2 ]  >  ... >  [ Stage N ]
  42          128 ▾              18/25            9/14              3/3
```

- **Jobs** and **Candidates** are always the first two segments, fixed — never driven by config.
- Every segment from there on is generated from that company + industry's `JobStatus` rows
  (`App\Models\JobStatus`), in place of the screenshot's hardcoded Shortlisted → Sent → 1st
  Interview → 2nd Interview → Offers → Placed labels. A company that names its statuses that way
  sees exactly that flow; a company using Open/On Hold/Filled/Cancelled (today's seeded defaults)
  sees that instead. This directly reuses the query shape already proven in
  `app/Filament/Widgets/Reports/JobPipelineChart.php` (`JobStatus::withCount('vacancies')`), just
  rendered as a flow instead of a doughnut, and split into open vs. total.
- **Renewals** (the screenshot's last tab) is dropped — it's a Booking/temp-contract concept and
  doesn't apply to a no-bookings sector.

## 3. What each segment counts

**Jobs** — total open vacancies for the active industry, scoped the same way
`ItPlacementsOverview::pipelineStats()` already does (`Vacancy::forActiveIndustry()`, consultant
filter applied — see §5). Shown as a single number (open count), matching the existing "Open
Vacancies" stat elsewhere on the page so the two don't disagree.

**Candidates** — count of candidates in the *currently selected* Candidate Pool (see §4), for the
active industry.

**Each Job Status segment** — for every `JobStatus` row belonging to the company + active
industry, two numbers:
- `total` = vacancies currently sitting at that status (`job_status_id = $status->id`, scoped by
  industry/consultant as above).
- `open` = the subset of those still accepting placements, using the same "open" definition
  `ItPlacementsOverview` already uses: `placements_count < positions_available`.

This is a present-day distribution (how many jobs sit in each column right now), not a historical
funnel — Applebough doesn't record job-status transition history, and statuses aren't guaranteed
to be linearly ordered (e.g. "On Hold" and "Cancelled" aren't "further along" than "Open"), so a
cumulative "reached this stage or later" count would misrepresent the data. **Confirmed**: current
count only, no new status-history tracking — matches how `JobPipelineChart` already reports these
same numbers today.

## 4. Candidate pool switcher

Reuses the exact visibility rule `CandidatePoolResource::getEloquentQuery()` already enforces —
pools owned by the current user, or company-wide pools (`company_pool = true`), scoped to the
active industry:

```php
CandidatePool::query()
    ->where('industry_id', active_industry_id())
    ->where(fn ($q) => $q->where('user_id', Auth::id())
        ->orWhere(fn ($q) => $q->where('company_pool', true)->whereNull('user_id')))
    ->get();
```

The widget gets a small `<select wire:model.live="poolId">` next to the "Candidates" segment
(styled like the existing consultant-picker in `it-placements-overview.blade.php`), defaulting to
no pool selected → "all candidates visible to me" for the active industry (i.e. the count the
Candidates segment already shows before anyone touches the dropdown). Picking a pool switches the
count to `$pool->candidates()->count()`.

## 5. Consultant scoping (consistency with the rest of the page)

`ItPlacementsOverview` already owns a consultant dropdown for admins and dispatches
`dashboard-consultant-changed`; `GenericConsultantKpiOverview` listens for it. `JobPipelineFlow`
should listen too, so picking a consultant filters the Jobs figure and every Job Status segment
to that consultant's vacancies, keeping all three widgets in sync. Non-admins are implicitly
scoped to themselves either way (`Vacancy::scopeVisibleToCurrentUser()` already encodes this rule
— worth reusing directly rather than re-deriving it).

## 6. The flow UI itself

New Blade partial, e.g. `resources/views/filament/widgets/job-pipeline-flow.blade.php`:

- A flex row of chevron segments, each a CSS `clip-path` arrow shape (matching the screenshot's
  look) — no charting library needed, this is layout, not a chart.
- Each segment shows: stage label, `open/total` (or a bare count for Jobs), and a solid-vs-hollow
  or saturation difference between the open and filled portion, similar in spirit to the
  screenshot's teal/grey split — using each `JobStatus`'s own `color` via
  `App\Filament\Support\StatusColorPalette` so a status's color stays consistent with wherever
  else it's shown (badges, the doughnut chart).
- Wraps to a scrollable row rather than shrinking illegibly once a company has 6+ statuses —
  worth a max-width/overflow-x rule so this doesn't break on companies with a long status list.
- **Click-through (confirmed, in v1)**: every segment is a link, not just a label.
  - Each Job Status segment links to `VacancyResource::index`, pre-filtered to that status. This
    needs one small addition — `VacanciesTable` (`app/Filament/Resources/Vacancies/Tables/
    VacanciesTable.php`) has no `job_status_id` filter today (only `employment_type` and
    `TrashedFilter`), so add `SelectFilter::make('job_status_id')` there (same pattern
    `ClientPipelineOverview` already uses), then deep-link with Filament's standard
    `?tableFilters[job_status_id][value]={id}` query string.
  - The **Jobs** segment links to the same index unfiltered.
  - The **Candidates** segment links to the currently-selected pool's own edit page
    (`CandidatePoolResource::getUrl('edit', ['record' => $pool])`), which already lists that
    pool's candidates via `CandidatesRelationManager` — no new resource or filter needed there.
    When no pool is selected, it links to the plain `CandidateResource` index instead.

## 7. Job status ordering — drag-and-drop (confirmed)

Jobs move through a general progression (Shortlisted → Sent → Interview → Offer → Placed, or
whatever a company calls its stages), so the flow's left-to-right order needs to be something a
site admin sets deliberately, not creation order. Confirmed as in-scope:

- **Migration**: add `sort_order` (unsigned integer, default `0`) to `job_statuses`. Backfill
  existing rows in their current creation order (`id` ascending) scoped per `(company_id,
  industry_id)`, so nothing visually reshuffles for existing companies on deploy — the seeded
  Open/On Hold/Filled/Cancelled rows keep that exact order until someone drags them.
- **`JobStatusResource`** (`app/Filament/Resources/JobStatuses/Tables/JobStatusesTable.php`):
  add `->reorderable('sort_order')` and `->defaultSort('sort_order')` to the table — this is the
  same mechanism Filament tables use elsewhere for drag handles, just not yet used anywhere in
  this codebase, so it's a small, self-contained addition rather than a new pattern to invent.
  `JobStatusResource::canViewAny()` already restricts this page to `admin`/`site_admin`, which is
  the right gate for reordering too.
- **New-row creation**: `ListJobStatuses::getHeaderActions()` currently creates a status via a
  modal (`CreateAction::make()->mutateDataUsing(...)`) with no `sort_order` set. Extend that
  `mutateDataUsing` closure to append new statuses to the end of the list:
  `$data['sort_order'] = JobStatus::where('company_id', $data['company_id'])->where('industry_id', $data['industry_id'])->max('sort_order') + 1`.
- **Every query that lists statuses in flow order** — `JobPipelineFlow` (new) and, as a nice side
  effect, the existing `app/Filament/Widgets/Reports/JobPipelineChart.php` doughnut — should
  `orderBy('sort_order')` rather than relying on default `id` order. The doughnut chart doesn't
  strictly need it (a pie has no "left to right"), but its legend will read more sensibly in the
  same order the flow uses, at zero extra cost.

This turns former Open Question #1 into a confirmed part of the build — no separate decision
needed before starting.

## 8. Files touched

New:
- `database/migrations/xxxx_xx_xx_add_sort_order_to_job_statuses_table.php`
- `app/Filament/Pages/Dashboards/NoBookingsDashboard.php` (rename/generalise of `ItDashboard.php`)
- `app/Filament/Widgets/JobPipelineFlow.php`
- `resources/views/filament/widgets/job-pipeline-flow.blade.php`
- `tests/Feature/JobPipelineFlowTest.php`
- `tests/Feature/JobStatusReorderTest.php`

Renamed:
- `app/Filament/Widgets/ItPlacementsOverview.php` → `ConsultantPlacementsOverview.php` (and its
  view file) — optional, but the class's own docblock already flags it as construction-shared, so
  the IT-specific name is stale once this is generalised further. Can be skipped if you'd rather
  not touch a working, tested class purely for naming.

Modified:
- `app/Filament/Pages/Dashboard.php` — resolution logic keyed off `active_industry_uses_bookings()`
- `app/Models/JobStatus.php` — no code change strictly required (plain int column), but worth a
  `scopeOrdered()` (`orderBy('sort_order')`) so every caller uses one consistent method name
  instead of repeating `orderBy('sort_order')` inline.
- `app/Filament/Resources/JobStatuses/Tables/JobStatusesTable.php` — `->reorderable('sort_order')`,
  `->defaultSort('sort_order')`.
- `app/Filament/Resources/JobStatuses/Pages/ListJobStatuses.php` — assign `sort_order` on create.
- `app/Filament/Widgets/Reports/JobPipelineChart.php` — order by `sort_order` (cosmetic, optional).
- `database/factories/JobStatusFactory.php` — set a `sort_order` in the factory definition so
  tests creating multiple statuses get a deterministic, distinct order rather than all `0`.
- `app/Filament/Resources/Vacancies/Tables/VacanciesTable.php` — add a `job_status_id`
  `SelectFilter`, so the flow's click-through has something to deep-link into.
- `tests/Feature/DashboardTest.php` — the two resolution tests need updating: Construction should
  resolve to the no-bookings dashboard once its toggle is off, and to the existing
  `ConstructionDashboard` when it's on. Add a case toggling `uses_bookings` mid-test to prove both
  branches.
- `tests/Feature/ConstructionItDashboardTest.php` — same; its current name and "reusing the
  booking-based widgets" assertion describe the old always-on behaviour and need rewriting to
  reflect the toggle, not just the industry.

---

# Part 2: Generic compliance dashboard (Education/Healthcare-only sectors, extended)

Separate concern from Part 1 above — touches candidate compliance, not the job pipeline — but
grouped in the same doc since it came out of the same conversation. Confirmed direction: same
KPI + bucketed-table layout Education/Healthcare already have, adapted to generic Compliance
Items rather than a fixed step list.

## 12. The actual gap

`ComplianceDashboard::__construct()` (`app/Filament/Pages/ComplianceDashboard.php`) looks for a
class named `Compliance{Industry}Dashboard`. Only `ComplianceEducationDashboard` and
`ComplianceHealthcareDashboard` exist. For every other sector (IT, Construction, and any other
generic-candidate industry), that class doesn't exist, `$this->dashboard` stays `null`, and
`getWidgets()` silently returns `[]` — a compliance-only user in one of those sectors sees a
blank dashboard today. This plan adds the missing class rather than leaving that gap.

## 13. Why it can't just reuse Education/Healthcare's widgets as-is

Education/Healthcare compliance is a **fixed, ordered wizard**: a candidate has a numeric
`compliance_step` (1-9), a hardcoded `stepLabelsList` per sector, and a `compliance_completed_at`
timestamp set manually at the wizard's final "Confirm" step
(`EducationVetting/Pages/VettingWizard.php`, `HealthcareVetting/Pages/HealthcareVettingWizard.php`).

Generic candidates use a completely different model — `App\Services\Candidates
\ComplianceRequirements`, built on `ComplianceItem`/`ComplianceItemField`/
`CandidateComplianceValue` (a flat, company/industry-configurable list of requirements, each
with its own fields, no inherent order or step number). The generic `candidates` table also
doesn't have `compliance_step` or `compliance_completed_at` columns at all — those only exist on
`education_candidates` and `healthcare_candidates`. So this isn't a drop-in reuse; it's a
parallel implementation using the same shapes:

- **New migration**: add `compliance_completed_at` (+ `compliance_completed_by`) to the
  `candidates` table, mirroring Education/Healthcare's columns.
- **New `ComplianceGenericKpiOverview extends ComplianceKpiOverview`**, returning
  `Candidate::class` — the abstract class's stats ("Through to Live This Week", "Outstanding in
  Vetting", "Average Time to Live", "Application to Compliance Complete") already work off
  `statuses.status` name lookups ("Live", "Vetting") and `compliance_completed_at`, both of which
  are the same convention generic candidates already use elsewhere (`CandidateStatusSeeder` seeds
  "Vetting"/"Live" the same way for every sector) — so this subclass should need close to zero new
  logic once the column above exists.
- **New `ComplianceItemVettingTable`**, parallel to `ComplianceVettingTable` but bucketing by how
  many of a candidate's required Compliance Items are still incomplete (via
  `ComplianceRequirements::for($candidate)`) instead of a numeric `compliance_step` against a
  fixed label list — same "Not Complete / Mostly Complete / Almost Complete" three-way split,
  same proportional thirds math as `ComplianceVettingTable::buckets()`, just measured in
  items-complete-out-of-total rather than step-number.
- **New `ComplianceGenericDashboard implements DashboardInterface`**, combining the two widgets
  above in the same KPI-row-plus-buckets shape as `ComplianceEducationDashboard`.
- **`ComplianceDashboard::__construct()`** — fall back to `ComplianceGenericDashboard` when no
  `Compliance{Industry}Dashboard` class exists for the active industry, instead of leaving
  `$this->dashboard` null.

## 14. Setting `compliance_completed_at` — confirmed: manual

Education/Healthcare set this timestamp at a deliberate, human "Confirm" step in a multi-page
wizard — a recruiter actively reviews and signs off. Generic candidates have no such wizard, so
this needed a call: **confirmed manual**, not automated. A lightweight "Mark compliance complete"
action — enabled only once `ComplianceRequirements::for($candidate)` reports every item actually
complete — that a consultant/compliance user clicks deliberately, matching Education/Healthcare's
review-then-confirm pattern rather than a silent background calculation.

## 15. Files touched (Part 2)

New:
- `database/migrations/xxxx_xx_xx_add_compliance_tracking_to_candidates_table.php`
- `app/Filament/Pages/Dashboards/ComplianceGenericDashboard.php`
- `app/Filament/Widgets/ComplianceGenericKpiOverview.php`
- `app/Filament/Widgets/ComplianceItemVettingTable.php`
- `tests/Feature/ComplianceGenericDashboardTest.php`

Modified:
- `app/Filament/Pages/ComplianceDashboard.php` — fallback resolution to `ComplianceGenericDashboard`.
- Whichever resource/page edits a generic candidate's compliance values — needs the new "Mark
  compliance complete" action from §14 (enabled only once every Compliance Item is complete).

---

## 16. Open questions before I'd start building

1. **Renaming `ItPlacementsOverview`** — fine to leave as-is if you'd rather not touch a tested,
   working class just for naming clarity (Part 1, cosmetic only). The only thing left open.

## 17. Suggested build order

**Part 1 — Job Pipeline Flow**
1. Add `sort_order` to `job_statuses` (migration + backfill) and wire up `->reorderable()` +
   `->defaultSort()` on `JobStatusResource`; set `sort_order` on new-status creation.
2. Refactor `Dashboard::__construct()` resolution logic; update the two existing test files.
3. Add the `job_status_id` filter to `VacanciesTable`.
4. Build `JobPipelineFlow` widget + Blade partial against real data, ordered by `sort_order`,
   with click-through links (no pool switcher yet).
5. Add the candidate pool switcher.
6. Wire up the shared consultant-filter event.
7. Feature tests: status reordering persists and is respected by the flow's ordering, resolution
   switching on the bookings toggle, widget counts (open/total) against seeded vacancies at
   various statuses, pool switching, consultant filtering, click-through URLs.

**Part 2 — Generic compliance dashboard** (independent of Part 1 — different area of the app,
can be built and shipped separately)
1. Add `compliance_completed_at`/`compliance_completed_by` to `candidates`, plus the manual "Mark
   compliance complete" action from §14.
2. Build `ComplianceGenericKpiOverview` and `ComplianceItemVettingTable`.
3. Build `ComplianceGenericDashboard`; wire the fallback into `ComplianceDashboard::__construct()`.
4. Feature tests: a compliance-only user in a generic sector sees KPIs + buckets instead of a
   blank page; bucket counts match `ComplianceRequirements` completion state; the "Mark complete"
   action only enables once every item is actually complete, and only then stamps the timestamp.
