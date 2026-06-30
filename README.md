# Applebough

A multi-tenant, multi-sector recruitment CRM built with the Laravel ecosystem. It manages candidates, clients, and the end-to-end candidate application process, with AI-assisted CV parsing, automated status transitions, and per-company email delivery via Microsoft Outlook or Mailgun.

---

## Tech Stack

| Layer | Package | Version |
|---|---|---|
| Framework | Laravel | ^13.7 |
| Reactive UI | Livewire | ^4.1 |
| Admin panel | Filament | ^5.0 |
| Component library | Flux UI (free) | ^2.13.1 |
| CSS | Tailwind CSS | ^4.0 |
| AI | laravel/ai | ^0.8 |
| Testing | Pest | ^4.7 |
| PHP | — | 8.4 |

---

## Architecture

### Multi-Tenancy

Every significant model uses the `BelongsToCompany` trait (`app/Models/Traits/BelongsToCompany.php`). This trait registers a global Eloquent scope that automatically filters all queries to `company_id = auth()->user()->company_id` and sets `company_id` on creation. No manual scoping is required in queries — isolation between tenants is enforced at the model layer.

### Multi-Sector

The application is designed to support multiple recruitment sectors (e.g. education, healthcare) from a single codebase. The active sector is stored per-user in the Laravel cache (`user.{id}.active_industry`) and accessed via two global helpers:

```php
active_industry();    // returns the slug, e.g. "education"
active_industry_id(); // returns the primary key
```

The `Industry` model maps sector slugs to their candidate model class via a static `$candidateModelMap` array. Every resource, skill, pool, and status query is filtered by `active_industry_id()`, meaning the admin panel and all logic automatically reflect the consultant's currently active sector. Switching sectors updates the cache and re-scopes everything without a page reload.

---

## Core Domain

### Candidates

The primary entity is `EducationCandidate` (the education sector implementation of the generic candidate concept). Key fields include:

- **Personal:** `title`, `first_name`, `middle_name`, `last_name`, `previous_surname`, `gender`, `nationality`, `date_of_birth`
- **Address:** `address`, `city`, `county`, `country`, `postcode`, `latitude`, `longitude`
- **Contact:** `email`, `phone`, `mobile`, `emergency_contact_name`, `emergency_contact_number`
- **Professional:** `employment_history`, `education_and_qualification`, `availability` (JSON), `key_stages` (JSON), `qualification_id`, `specialism`
- **Admin:** `consultant_id`, `notes`

Candidates are soft-deleted and scoped to the company. Relationships include skills, pools, statuses, an application, and an activity timeline.

### Clients

`EducationClient` represents the hiring organisations (schools, academies) that a company recruits for. Like candidates, clients are industry-prefixed and company-scoped. Key fields include `name`, `email`, `subject`, `grade_level`, and `notes`.

---

## Candidate Application Process

When a new candidate record is created, `CandidateCreated` action automatically:

1. Creates an `EducationApplication` record with a UUID token and a 7-day expiry.
2. Dispatches `SendApplicationEmail` to deliver the application link to the candidate.

The candidate follows a two-step public flow (no login required):

**Step 1 — Email verification** (`/application/{token}`)
The candidate verifies ownership of their email address by entering a code sent to them.

**Step 2 — Self-service form** (`/application/{token}/form`)
A Livewire component (`⚡application-form.blade.php`) that:
- Accepts a CV upload (PDF, up to 10 MB).
- Sends it to the AI CV parser which extracts personal details, address, employment history, and more.
- Pre-fills the form fields; the candidate reviews and completes any missing information (title, previous name, gender, nationality, emergency contact, etc. are always entered manually).
- On submission, updates the `EducationCandidate` record and marks the application `completed`.

If the candidate returns to the form after a partial submission (e.g. CV already parsed), the application's stored `cv_parsed_data` is used to pre-fill the form, skipping the CV upload step.

The `EducationApplication` model tracks: `status`, `email_verified`, `expires_on`, `cv_parsed_data` (JSON, cast to array), and `completed_at`.

---

## Integrations

### Laravel AI SDK — CV Parsing

**Package:** `laravel/ai ^0.8`

The `CvParser` agent (`app/Ai/Agents/CvParser.php`) uses GPT-4o via the OpenAI provider with structured output to extract up to 15 fields from a CV PDF:

```
firstName, middleName, lastName, dateOfBirth, address, city, county, country,
postcode, phone, mobile, employmentHistory, educationAndQualification, skills, summary
```

The `CvParserService` (`app/Services/CvParserService.php`) calls the agent by attaching the uploaded file as a `Document::fromPath(...)` and maps the structured response to a `CvExtraction` DTO. The service is injected directly into the Livewire component's `parseCv` action method.

### Google — Address Autocomplete & Geocoding

**Config key:** `services.google.places_key`

Two distinct usages:

**1. Address autocomplete (Filament admin)**
The candidate edit form in Filament calls the Google Places Autocomplete API (`places:autocomplete`) as a consultant types an address, then resolves the selected place via `places/{placeId}` to extract and populate `address`, `city`, `county`, `country`, and `postcode` fields automatically.

**2. Geocoding (background job)**
`GeocodeEducationCandidate` (`app/Jobs/GeocodeEducationCandidate.php`) calls the Google Geocoding API (`maps/api/geocode/json`) with the candidate's postcode to resolve `latitude` and `longitude`. This job is dispatched by `EducationCandidateObserver` whenever the candidate's `postcode` field changes.

### Email — Microsoft Outlook (Graph API) & Mailgun

Each company independently configures its email provider. The `EmailProvider` enum offers two options:

**Microsoft Graph API** (`app/Services/MicrosoftGraphMailer.php`)
Sends email by calling `https://graph.microsoft.com/v1.0/users/{sender}/sendMail` using a cached OAuth2 client-credentials token obtained from Azure AD. Credentials (`MS_TENANT_ID`, `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_SENDER_EMAIL`) are configured per company via the Filament Microsoft Settings page.

**Mailgun** (`app/Services/MailgunMailer.php`)
Calls the Mailgun REST API directly (not via Laravel's built-in Mailgun driver), using per-company API keys stored on the `Company` model.

At runtime, `SendApplicationEmail` (and other email jobs) resolve the correct mailer via `match($company->email_provider)`. There are no Laravel `Mailable` classes — emails are sent as raw HTML through the service layer.

---

## Candidate Skills

`CandidateSkill` is a company and industry-scoped model that supports a **parent/child hierarchy** via a self-referential `parent_id`. This allows skills to be grouped (e.g. "Primary" → "EYFS", "KS1", "KS2").

Skills attach to candidates via a morph pivot table (`candidate_skill_candidates`), resolved dynamically based on the active industry. This means the same skill infrastructure works across all sectors without separate tables. When a consultant assigns a child skill in the Filament UI, the parent skill is automatically added too, enforcing consistent tagging.

---

## Candidate Pools

Pools allow consultants to group candidates for quick access or targeting. `CandidatePool` has a `company_pool` boolean that determines its visibility:

| Type | `company_pool` | `user_id` | Visible to |
|---|---|---|---|
| Company-wide | `true` | `null` | All consultants in the company |
| Personal | `false` | set | The creating consultant only |

All pools are still company-scoped via `BelongsToCompany`. Candidates attach to pools via a morph pivot (`candidate_pool_candidates`). Consultants can query their own pools and any company-wide pools in a single unified list.

---

## Candidate Statuses & Automations

### Statuses

`CandidateStatus` is a configurable, company and industry-scoped model (not a PHP enum). Companies define their own status names and colours. A `CandidateCandidateStatus` pivot links candidates to one or more statuses. The model includes a `colorForName()` helper that maps common status names (Live, Onboarding, Offline, DNU, etc.) to Filament badge colours for consistent display.

### Automations

Status transitions can be automated via `CandidateStatusAutomation` records, configured in Filament's `AutomationsRelationManager` on each status. The engine works as follows:

1. `EducationCandidateObserver::updated()` fires on every candidate save.
2. It calls `CheckCandidateStatusAutomations::run($candidate)`.
3. That action loads all automations associated with the candidate's current statuses.
4. Each automation defines a `completed_fields` array — a list of attribute paths or wildcard relation checks (e.g. `skills.*`) that must be non-empty.
5. The first automation whose conditions are fully satisfied triggers `ChangeCandidateStatus::run()`, which transitions the candidate to the target status and stops evaluation.

This allows companies to define rules such as "automatically move a candidate from *Onboarding* to *Live* once they have a date of birth, address, and at least one skill assigned" — without any custom code.

---

## Local Development

The application is served by [Laravel Herd](https://herd.laravel.com/) at `https://applebough.test`.

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start asset pipeline
npm run dev

# Run tests
php artisan test
```

### Required environment variables

```env
# Database
DB_CONNECTION=mysql

# OpenAI (CV parsing)
OPENAI_API_KEY=

# Google (address autocomplete + geocoding)
GOOGLE_PLACES_KEY=

# Email — configure one or both per company via Filament settings
# Microsoft Graph
MS_TENANT_ID=
MS_CLIENT_ID=
MS_CLIENT_SECRET=
MS_SENDER_EMAIL=

# Mailgun
MAILGUN_API_KEY=
MAILGUN_DOMAIN=
```
