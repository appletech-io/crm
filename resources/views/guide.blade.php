<x-layouts::app :title="'User Guide'">
    @php
        $sections = [
            'getting-started' => 'Getting Started',
            'dashboard' => 'Dashboard',
            'candidates' => 'Candidates',
            'clients' => 'Clients',
            'bookings-payroll' => 'Bookings & Payroll',
            'jobs-pipeline' => 'Jobs & the Job Pipeline',
            'compliance' => 'Compliance',
            'pools-catalogues' => 'Pools, Skills, Job Titles & Qualifications',
            'portals' => 'Client & Candidate Portals',
            'reports' => 'Reports & Analytics',
            'ai-assistant' => 'AI Assistant',
            'settings' => 'Settings',
            'statuses' => 'Quick Reference: Statuses',
        ];
    @endphp

    <div class="mx-auto flex max-w-6xl gap-10 px-4 py-8 sm:px-6 lg:px-8">
        <article class="min-w-0 flex-1 space-y-14 pb-24 text-gray-700 dark:text-gray-300">
            <header class="space-y-3 border-b border-gray-200 pb-8 dark:border-white/10">
                <h1 class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">User Guide</h1>
                <p class="max-w-2xl text-base leading-relaxed">
                    Everything in the CRM, in one place. This guide is written to apply across every
                    sector the system supports — Education, Healthcare, and generic industries like
                    construction or IT. Where a sector does something differently, it's called out
                    explicitly in a box like this one.
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Jump to a section using the list on the right, or use your browser's find (Cmd/Ctrl+F)
                    to search this page.
                </p>
            </header>

            {{-- ============================================================ --}}
            <section id="getting-started" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Getting Started</h2>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Signing in</h3>
                <p>
                    Staff accounts are set up for you by an admin — there's no public sign-up. Log in with
                    your email and password at the login screen. If your company has two-factor
                    authentication turned on for your account, you'll also be asked for a 6-digit code from
                    an authenticator app after your password.
                </p>
                <p>
                    You can also register a <strong>passkey</strong> (fingerprint, face unlock, or a
                    security key) for faster, passwordless sign-in — set this up from your account menu
                    under <em>Password &amp; 2FA</em>.
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Switching sector</h3>
                <p>
                    If your company operates in more than one industry (for example Education and
                    Healthcare, or Education and a generic sector), you're always working in one
                    "active" sector at a time — it decides which Candidates resource, compliance rules,
                    and job pipeline you see. Switch it from your account menu (top right) under
                    <em>Switch Sector</em>.
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Finding your way around</h3>
                <p>
                    The left-hand sidebar is your main navigation. Sections are grouped roughly as: your
                    everyday work (Candidates, Clients, Bookings/Jobs, Compliance), Payroll, Analytics, and
                    Settings/Admin — the last group only shows items you have permission to use.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="dashboard" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Dashboard</h2>
                <p>
                    Your homepage after logging in. What it shows adapts to your active sector — a company
                    using Bookings sees booking-focused KPIs and a "rebook rate" for next week; a
                    non-Bookings (placement-only) sector sees a pipeline-focused dashboard instead.
                    Widgets typically include: consultant performance for the current week, gross profit
                    and average margin, days placed, clients/candidates worked this week, and — where
                    relevant — how much of next week is already booked.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="candidates" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Candidates</h2>
                <p>
                    The Candidates list is where you search, filter, and manage everyone available to book
                    or place. Which fields and vetting steps a candidate has depends on your sector — the
                    list itself works the same way everywhere: search by name, filter by status/skill/pool/
                    consultant/rating, and use the row actions to quick-view, email, or open a candidate's
                    full record.
                </p>
                <p>
                    A candidate's full record includes their contact/personal details, compliance status
                    (see <a href="#compliance" class="text-green-700 underline underline-offset-2 dark:text-green-400">Compliance</a>),
                    skills, pay rates, availability, and their booking/placement history with a rating
                    that travels with them.
                </p>

                <x-guide.callout>
                    Education and Healthcare candidates each have their own dedicated resource with
                    sector-specific fields and a guided vetting wizard. Other sectors (construction, IT,
                    generic) share one flexible Candidate model whose compliance requirements are
                    configured by your company rather than fixed in the system — see Compliance below.
                </x-guide.callout>
            </section>

            {{-- ============================================================ --}}
            <section id="clients" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Clients</h2>
                <p>
                    Clients are the schools, care settings, or companies you place candidates with. Each
                    client record holds their contacts (with roles — main, booking, timesheet, invoice
                    contact), charge rates, activity history, and — for Bookings sectors — their upcoming
                    bookings and a Pipeline tab summarising open jobs and their value.
                </p>
                <p>
                    On the Clients list, a client's name is colour-coded so you can spot who needs
                    attention at a glance:
                </p>
                <ul class="list-disc space-y-1 pl-6">
                    <li><span class="font-semibold text-green-600 dark:text-green-400">Green</span> — currently working with you (a booking in progress today).</li>
                    <li><span class="font-semibold text-yellow-600 dark:text-yellow-500">Yellow</span> — has booked before, but it's been 60+ days since their last booking.</li>
                    <li><span class="font-semibold text-orange-600 dark:text-orange-400">Orange</span> — has never booked, and has been a client for 14+ days with no activity — a lapsed lead.</li>
                    <li>No colour — everything else, including a client with history who's only recently gone quiet.</li>
                </ul>
            </section>

            {{-- ============================================================ --}}
            <section id="bookings-payroll" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Bookings &amp; Payroll</h2>
                <p>
                    <em>(Sectors that use temp/day-rate bookings only — some sectors work placement-only
                    and won't have a Bookings section at all.)</em>
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Creating a booking</h3>
                <p>
                    A booking pairs a candidate with a client for a job title, over a day-by-day schedule —
                    full days, half days (AM/PM), or hourly. Pay and charge rates are set per session type,
                    and the margin calculator on the booking form shows your live gross margin as you fill
                    it in, correctly accounting for the extra employer cost of a PAYE candidate (umbrella
                    candidates don't carry that extra cost, since the umbrella company covers it).
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">The Bookings list</h3>
                <ul class="list-disc space-y-1 pl-6">
                    <li><strong>Weekly View</strong> (default) — every client's bookings for the current week, laid out Mon–Sun with an icon per day. Click an empty day to create a booking, or a booked day to open it.</li>
                    <li><strong>Requests</strong> — bookings a client has asked for that you haven't accepted yet.</li>
                    <li><strong>All</strong> — the full, filterable table of every booking.</li>
                </ul>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Approval &amp; payroll</h3>
                <p>
                    Each booked day moves through: <em>Pending</em> → <em>Sent</em> (confirmation emailed to
                    the client) → <em>Approved</em> or <em>Disputed</em> by the client (or by an admin on
                    their behalf, from Run Payroll). Only approved days flow into invoicing.
                </p>
                <ul class="list-disc space-y-1 pl-6">
                    <li><strong>Run Payroll</strong> (admin) — the working payroll screen for the current period, grouped by client, with a red warning icon for any client with unapproved or disputed days and a green check for clients fully signed off. Send confirmations, send reminders, and approve days here.</li>
                    <li><strong>Timesheets</strong> — your own personal, read-only version of the same list, opening on last period's still-unresolved days, so it reads as a chase list.</li>
                    <li><strong>Invoicing</strong> — generates client invoices, umbrella self-bills, and payroll export files from approved days only.</li>
                </ul>
            </section>

            {{-- ============================================================ --}}
            <section id="jobs-pipeline" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Jobs &amp; the Job Pipeline</h2>
                <p>
                    A "Job" (vacancy) is a role you're recruiting for — permanent, or temp cover tied to a
                    date range. Every job has its own Applicants board: a drag-and-drop kanban with a
                    column per pipeline stage (configured under Job Statuses in Settings).
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Working a job</h3>
                <p>
                    Opening a job lands you straight on its Applicants board, full screen — that's the main
                    working view, since drag-and-drop saves instantly with nothing to explicitly submit.
                    Use the <em>Details / Matches / Activity</em> button at the top to switch to the job's
                    own settings, its AI-ranked candidate matches, or its activity log; <em>Back to Board</em>
                    returns you to the kanban.
                </p>
                <p>Candidates land on the board three ways:</p>
                <ul class="list-disc space-y-1 pl-6">
                    <li>Applying directly via the public job link.</li>
                    <li>Being AI-matched, then moved across from the Matches tab.</li>
                    <li>Added manually — use the <strong>+ Candidate</strong> link in the first column's header to pull anyone from your candidate pool straight onto the board.</li>
                </ul>
                <p>
                    "Send Application Form" and "Create Booking" (temp jobs only) only appear on a card
                    once that candidate has been moved at least one column past the first stage — a fresh
                    application or a newly-added candidate won't show them until you've progressed them.
                </p>
                <p>
                    A job's own overall status always reflects whichever candidate has progressed
                    furthest through its pipeline — you don't need to separately update the job's status
                    by hand as candidates move along.
                </p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">The standalone Job Pipeline page</h3>
                <p>
                    A company-wide view of every job and candidate, laid out as a Jobs → Candidates →
                    Status flow. Click any status to see the jobs currently at that stage (scroll to load
                    more, 20 at a time); each status also shows how many candidates are actually sitting
                    there right now. Use the pool dropdown to narrow the Candidates step to a specific
                    group — picking a client-linked pool narrows the Jobs list to that client too.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="compliance" class="scroll-mt-8 space-y-6">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Compliance</h2>
                <p>
                    Every sector answers the same question — "is this candidate allowed to work as X?" —
                    but each does it differently. Find your sector below.
                </p>

                <div class="space-y-3 rounded-xl border border-gray-200 p-5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Education</h3>
                    <p>
                        A fixed checklist covering DBS (certificate + Update Service check, or a front/back
                        upload), CV, photo, references, qualifications, skills, pay rates/payment method,
                        barred list check, overseas police clearance (where relevant), proof of address and
                        National Insurance number matching, Teacher Reference Number (where relevant),
                        safeguarding training, Benedict's Law training, and right to work (passport, visa, or
                        birth certificate, each with its own expiry). A candidate is "compliant" once every
                        item that applies to them is complete. Work through it via the guided
                        <strong>Vetting Wizard</strong> — it's resumable, so you can leave and come back.
                        Expiring items are flagged from <strong>3 days</strong> before they lapse.
                    </p>
                </div>

                <div class="space-y-3 rounded-xl border border-gray-200 p-5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Healthcare</h3>
                    <p>
                        The same shape as Education — CV, photo, references, DBS, skills, pay rates, right
                        to work, and so on — with <strong>Professional Registration</strong> (registering
                        body, registration number, date checked) in place of Education's Teacher Reference
                        Number and Benedict's Law requirements. Also worked through via a guided
                        <strong>Vetting Wizard</strong>. Expiring items are flagged from
                        <strong>14 days</strong> before they lapse — a longer window than Education's.
                    </p>
                </div>

                <div class="space-y-3 rounded-xl border border-gray-200 p-5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Generic sectors (construction, IT, and others)</h3>
                    <p>
                        Nothing is fixed — your company defines its own <strong>Compliance Items</strong>
                        (for example "DBS", with fields like a certificate number, issue date, and an expiry
                        date) under <em>Settings → Compliance Settings</em>. Each item is then mapped to the
                        job titles that require it, which is what actually decides whether a candidate is
                        eligible for a given role — a candidate can fill in an item that isn't required for
                        their job title, but only the items mapped to that title gate booking eligibility.
                        Expiring items are flagged from <strong>14 days</strong> before they lapse.
                    </p>
                    <x-guide.callout>
                        Compliance Settings only appears for generic-sector companies. If you're on
                        Education or Healthcare, there's nothing to configure here — your checklist is
                        already fixed by the system.
                    </x-guide.callout>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Chasing what's outstanding</h3>
                <p>
                    The <strong>Compliance</strong> nav item is your day-to-day worklist — for generic
                    sectors it's already filtered to candidates with something incomplete; for Education/
                    Healthcare it lists candidates alongside their Vetting Wizard progress. The
                    <strong>Compliance Dashboard</strong> (admin) gives a company-wide, sector-aware summary
                    of who's compliant, who's pending, and who needs chasing today.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="pools-catalogues" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Pools, Skills, Job Titles &amp; Qualifications</h2>
                <ul class="list-disc space-y-2 pl-6">
                    <li><strong>Candidate Pools</strong> — your own (or a company-wide) groupings of candidates, e.g. "shortlisted for BlueWave Digital" or "Available Now". Used to filter the Job Pipeline's Candidates step and for bulk actions like emailing or adding to a job.</li>
                    <li><strong>Client Pools</strong> — the equivalent for clients; a client's primary pool is what actually controls which consultant sees them day-to-day.</li>
                    <li><strong>Skills</strong> — tags on a candidate's profile, organised into top-level and child skills, used for matching and search.</li>
                    <li><strong>Job Titles</strong> — your company's role catalogue. Drives default pay/charge rates, which compliance items apply (generic sectors), and job-matching.</li>
                    <li><strong>Qualifications</strong> — (Education) the qualification catalogue and which job titles each one permits a candidate to work.</li>
                </ul>
            </section>

            {{-- ============================================================ --}}
            <section id="portals" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Client &amp; Candidate Portals</h2>
                <p>
                    Clients and candidates have their own separate, cut-down logins — they never see the
                    main CRM.
                </p>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Client portal</h3>
                <p>
                    A client contact can approve or dispute each booked day (<em>My Bookings</em>), see
                    candidates they've booked and rated well with a one-click <em>Request Booking</em>
                    (<em>My Candidates</em>), and rate candidates from bookings in the last month.
                </p>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Candidate portal</h3>
                <p>
                    A candidate can set their availability, see their own compliance checklist, and upload
                    documents (CV, photo, DBS, etc.) that feed straight into that checklist.
                </p>
                <x-guide.callout>
                    The candidate portal is currently only available for Education candidates. Healthcare
                    and generic-sector candidates don't have a self-service login yet — manage their
                    compliance and documents on their behalf from the main Candidates area instead.
                </x-guide.callout>
            </section>

            {{-- ============================================================ --}}
            <section id="reports" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Reports &amp; Analytics</h2>
                <ul class="list-disc space-y-2 pl-6">
                    <li><strong>Candidates</strong> — candidate count, how many are placed, and your placement rate.</li>
                    <li><strong>Clients</strong> — active clients, booking revenue and margin, placements and placement value.</li>
                    <li><strong>Vacancies &amp; Placements</strong> — open vs. filled jobs, estimated pipeline value, and actual margin delivered.</li>
                    <li><strong>Revenue &amp; Margin</strong> — bookings, revenue, cost, margin and average margin % over any date range, filterable by consultant or client, with a full per-booking breakdown.</li>
                </ul>
                <p>
                    There's also a per-consultant <strong>Monthly Report</strong> with an AI-written summary
                    of the month and a call-coaching tab, separate from the main Analytics section.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="ai-assistant" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">AI Assistant</h2>
                <p>
                    Available from most pages as a chat panel. Ask it a plain-English question about your
                    own data instead of building a report — it can search candidates, clients, vacancies
                    and bookings, check whether a candidate is eligible for a job title, flag compliance
                    items about to expire, find nearby available candidates, summarise a vacancy's AI
                    matches, and pull your (or a colleague's) recent performance. It remembers the
                    conversation as you go, so you can ask follow-up questions naturally.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="settings" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Settings</h2>
                <p>Each settings area is a landing page of cards linking to the actual configuration:</p>
                <ul class="list-disc space-y-2 pl-6">
                    <li><strong>Job Settings</strong> — Job Statuses (your pipeline stages, drag to reorder, plus automations for moving jobs between statuses automatically).</li>
                    <li><strong>Client Settings</strong> — Contact Job Titles, Client Types, Client Pools.</li>
                    <li><strong>Candidate Settings</strong> — Skills, Candidate Statuses (with their own automations), Candidate Pools, Job Titles, Qualifications and their job-title mapping, Reference Forms, Sample Profiles.</li>
                    <li><strong>Compliance Settings</strong> — generic sectors only: Compliance Items and which job titles require each one.</li>
                </ul>
                <p>
                    Admin-only areas also include Team &amp; Roles (staff and permissions), Candidate &amp;
                    Client Logins (portal access), Email Templates, and — for multi-company setups — Site
                    Settings.
                </p>
            </section>

            {{-- ============================================================ --}}
            <section id="statuses" class="scroll-mt-8 space-y-4">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Quick Reference: Statuses</h2>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Booking status</h3>
                <p>Requested → Upcoming → Awaiting Approval → Approved → Completed.</p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Day payroll status</h3>
                <p>Pending → Sent → Approved <em>or</em> Disputed.</p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Client colour-coding</h3>
                <p>Green (active today) → Yellow (booked before, 60+ days quiet) → Orange (never booked, 14+ days old) → no colour.</p>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Job Statuses</h3>
                <p>
                    Fully configurable per company under Job Settings — there's no fixed list. Whatever
                    stages you set up are what appears on every job's Applicants board and the Job
                    Pipeline page.
                </p>
            </section>
        </article>

        <nav class="sticky top-8 hidden h-fit w-56 shrink-0 lg:block">
            <p class="mb-3 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">On this page</p>
            <ul class="space-y-1 border-l border-gray-200 dark:border-white/10">
                @foreach ($sections as $anchor => $label)
                    <li>
                        <a
                            href="#{{ $anchor }}"
                            class="block border-l-2 border-transparent py-1 pl-3 text-sm text-gray-500 transition hover:border-gray-400 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-100"
                        >
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</x-layouts::app>
