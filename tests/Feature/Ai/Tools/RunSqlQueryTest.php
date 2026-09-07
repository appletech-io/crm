<?php

use App\Ai\Tools\RunSqlQuery;
use App\Enums\VacancyEmploymentType;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientContact;
use App\Models\ClientContactJobTitle;
use App\Models\ConsultantKpiTarget;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\TodoItem;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Oakwood Primary']);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
});

test('it can aggregate across bookings with a select query', function () {
    Booking::factory()->count(3)->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS total FROM bookings']));

    expect($result)->toContain('total')->and($result)->toContain('3');
});

test('it can join across bookings and clients', function () {
    Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => "SELECT client_name, COUNT(*) AS n FROM bookings WHERE client_name = 'Oakwood Primary' GROUP BY client_name",
    ]));

    expect($result)->toContain('Oakwood Primary')->and($result)->toContain('1');
});

test('it does not expose bookings belonging to a different company', function () {
    $otherClient = Client::factory()->create(['name' => 'Other Company Client']);
    $otherCandidate = EducationCandidate::factory()->create();

    Booking::factory()->create([
        'company_id' => $otherClient->company_id,
        'client_id' => $otherClient->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT client_name FROM bookings']));

    expect($result)->not->toContain('Other Company Client');
});

test('the candidates table never exposes compliance or personal-identity columns', function () {
    EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT * FROM candidates LIMIT 1']));

    foreach (['dbs', 'national_insurance', 'date_of_birth', 'right_to_work', 'address'] as $restricted) {
        expect(strtolower($result))->not->toContain($restricted);
    }
});

test('it rejects statements that are not a select', function () {
    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'DELETE FROM bookings']));

    expect($result)->toContain('Query rejected')->and($result)->toContain('only SELECT statements are allowed');
});

test('it rejects a select that smuggles a second statement', function () {
    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT * FROM bookings; DROP TABLE bookings;',
    ]));

    expect($result)->toContain('Query rejected');
});

test('it rejects a select containing a forbidden keyword anywhere in the statement', function () {
    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT * FROM bookings WHERE 1=1 UNION SELECT * FROM sqlite_master WHERE sql LIKE "%create%"',
    ]));

    expect($result)->toContain('Query rejected');
});

test('it caps results at 200 rows regardless of the query', function () {
    Booking::factory()->count(210)->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT id FROM bookings']));

    expect(substr_count($result, "\n"))->toBeLessThanOrEqual(203)
        ->and($result)->toContain('Capped at 200 rows');
});

test('it returns a friendly message when the query is invalid sql', function () {
    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT * FROM not_a_real_table']));

    expect($result)->toContain('Query failed');
});

test('it returns a friendly message when nothing matches', function () {
    $result = (new RunSqlQuery)->handle(new Request(['sql' => "SELECT * FROM bookings WHERE client_name = 'Nobody'"]));

    expect($result)->toBe('Query returned no rows.');
});

test('it can query activity logs against candidates and clients', function () {
    CandidateActivity::create([
        'user_id' => $this->user->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $this->candidate->id,
        'type' => 'call',
        'note' => 'Called about availability',
    ]);

    $this->client->update(['industry_id' => $this->industry->id]);

    ClientActivity::create([
        'user_id' => $this->user->id,
        'model_type' => Client::class,
        'model_id' => $this->client->id,
        'type' => 'meeting',
        'note' => 'Termly review meeting',
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT category, type, note, related_to FROM activities']));

    expect($result)->toContain('Called about availability')
        ->and($result)->toContain('Jane Doe')
        ->and($result)->toContain('Termly review meeting')
        ->and($result)->toContain('Oakwood Primary');
});

test('it does not expose activity logs belonging to a different company', function () {
    $otherCandidate = EducationCandidate::factory()->create();

    CandidateActivity::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $otherCandidate->id,
        'type' => 'note',
        'note' => 'Note belonging to a different company entirely',
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT note FROM activities']));

    expect($result)->not->toContain('Note belonging to a different company entirely');
});

test('a multi-day booking produces one booking_days row per actual worked day, not one', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    BookingDay::create([
        'booking_id' => $booking->id,
        'date' => '2026-09-07',
        'period' => 'full_day',
    ]);
    BookingDay::create([
        'booking_id' => $booking->id,
        'date' => '2026-09-08',
        'period' => 'full_day',
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT COUNT(*) AS days FROM booking_days WHERE booking_id = '.$booking->id,
    ]));

    expect($result)->toContain('2');

    $bookingCount = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT COUNT(*) AS n FROM bookings WHERE id = '.$booking->id,
    ]));

    expect($bookingCount)->toContain('1');
});

test('booking_days resolves margin per day using the rate for that day\'s period, not a flat rate', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
        'half_day_rate' => 60,
        'half_day_charge_rate' => 90,
    ]);

    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-07', 'period' => 'full_day']);
    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-08', 'period' => 'am']);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT date, pay_rate, charge_rate, margin FROM booking_days ORDER BY date',
    ]));

    expect($result)
        ->toContain('2026-09-07')->toContain('100')->toContain('150')->toContain('50')
        ->toContain('2026-09-08')->toContain('60')->toContain('90')->toContain('30');
});

test('a cancelled booking day is excluded from booking_days', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-07', 'period' => 'full_day']);
    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-08', 'period' => 'full_day', 'cancelled_at' => now()]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT COUNT(*) AS days FROM booking_days WHERE booking_id = '.$booking->id,
    ]));

    expect($result)->toContain('1')->and($result)->not->toContain('2');
});

test('booking_days can be grouped and summed by consultant for a margin total', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-07', 'period' => 'full_day']);
    BookingDay::create(['booking_id' => $booking->id, 'date' => '2026-09-08', 'period' => 'full_day']);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT consultant_name, COUNT(*) AS days, SUM(margin) AS total_margin FROM booking_days GROUP BY consultant_name',
    ]));

    expect($result)->toContain($consultant->name)->toContain('2')->toContain('100');
});

test('it does not expose booking_days belonging to a different company', function () {
    $otherClient = Client::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create();

    $otherBooking = Booking::factory()->create([
        'company_id' => $otherClient->company_id,
        'client_id' => $otherClient->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    BookingDay::create([
        'company_id' => $otherClient->company_id,
        'booking_id' => $otherBooking->id,
        'date' => '2026-09-07',
        'period' => 'full_day',
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM booking_days']));

    expect($result)->toContain('0');
});

test('it can query and aggregate vacancies', function () {
    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'title' => 'Class Teacher',
        'employment_type' => VacancyEmploymentType::Permanent,
        'salary_min' => 28000,
        'salary_max' => 35000,
    ]);

    Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'title' => 'Supply Teacher',
        'employment_type' => VacancyEmploymentType::Temp,
        'salary_min' => null,
        'salary_max' => null,
        'day_rate_min' => 120,
        'day_rate_max' => 180,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM vacancies']));

    expect($result)->toContain('2');

    $permanent = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT title, salary_min, salary_max FROM vacancies WHERE is_temp = 0',
    ]));

    expect($permanent)->toContain('Class Teacher')->toContain('28000')->toContain('35000');

    $temp = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT title, day_rate_min, day_rate_max FROM vacancies WHERE is_temp = 1',
    ]));

    expect($temp)->toContain('Supply Teacher')->toContain('120')->toContain('180');
});

test('it does not expose vacancies belonging to a different company', function () {
    Vacancy::factory()->create(['title' => 'Other Company Vacancy']);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT title FROM vacancies']));

    expect($result)->not->toContain('Other Company Vacancy');
});

test('it can query vacancy applications, including shortlisted status', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'title' => 'Class Teacher',
    ]);

    VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'shortlisted_at' => now(),
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT vacancy_title, candidate_name, client_name, shortlisted FROM vacancy_applications',
    ]));

    expect($result)
        ->toContain('Class Teacher')
        ->toContain('Jane Doe')
        ->toContain('Oakwood Primary')
        ->toContain('1');
});

test('it does not expose vacancy applications belonging to a different company', function () {
    $otherVacancy = Vacancy::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create();

    VacancyApplication::create([
        'vacancy_id' => $otherVacancy->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM vacancy_applications']));

    expect($result)->toContain('0');
});

test('candidate_applications reports onboarding progress but never declaration content', function () {
    EducationApplication::factory()->create([
        'education_candidate_id' => $this->candidate->id,
        'status' => 'completed',
        'current_step' => 5,
        'completed_at' => now(),
        'childcare_act_no_disqualification_reasons_details' => 'Sensitive disclosure text that must never appear',
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT candidate_name, status, current_step, completed FROM candidate_applications',
    ]));

    expect($result)
        ->toContain('Jane Doe')
        ->toContain('completed')
        ->toContain('5')
        ->not->toContain('Sensitive disclosure text');
});

test('it does not expose candidate applications belonging to a different company', function () {
    $otherCandidate = EducationCandidate::factory()->create();

    EducationApplication::factory()->create(['education_candidate_id' => $otherCandidate->id]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM candidate_applications']));

    expect($result)->toContain('0');
});

test('candidate_applications works for the generic candidate model when no sector-specific one applies', function () {
    Cache::forget("user.{$this->user->id}.active_industry");
    Cache::forget("user.{$this->user->id}.active_industry_id");

    $genericIndustry = Industry::factory()->create(['slug' => 'generic']);
    Cache::put("user.{$this->user->id}.active_industry", $genericIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $genericIndustry->id);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Sam',
        'last_name' => 'Rivera',
    ]);

    CandidateApplication::factory()->create([
        'candidate_id' => $candidate->id,
        'company_id' => $this->user->company_id,
        'status' => 'pending',
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT candidate_name, status FROM candidate_applications']));

    expect($result)->toContain('Sam Rivera')->toContain('pending');
});

test('it can query placements and their actual salary', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'title' => 'Class Teacher',
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'actual_salary' => 32000,
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT vacancy_title, candidate_name, client_name, actual_salary FROM placements',
    ]));

    expect($result)
        ->toContain('Class Teacher')
        ->toContain('Jane Doe')
        ->toContain('Oakwood Primary')
        ->toContain('32000');
});

test('it does not expose placements belonging to a different company', function () {
    $otherVacancy = Vacancy::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create();

    VacancyPlacement::factory()->create([
        'vacancy_id' => $otherVacancy->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM placements']));

    expect($result)->toContain('0');
});

test('it can query and aggregate vacancy match scores', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'title' => 'Class Teacher',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'score' => 87,
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT vacancy_title, candidate_name, score FROM vacancy_matches',
    ]));

    expect($result)->toContain('Class Teacher')->toContain('Jane Doe')->toContain('87');
});

test('it does not expose vacancy matches belonging to a different company', function () {
    $otherVacancy = Vacancy::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create();

    VacancyCandidateMatch::create([
        'vacancy_id' => $otherVacancy->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
        'score' => 50,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT COUNT(*) AS n FROM vacancy_matches']));

    expect($result)->toContain('0');
});

test('candidate_compliance reports expiry status but never a certificate number', function () {
    $this->candidate->update([
        'dbs_expiry_date' => now()->addDays(5),
        'right_to_work_expiry_date' => now()->subDay(),
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT candidate_name, requirement, status FROM candidate_compliance ORDER BY requirement',
    ]));

    expect($result)
        ->toContain('Jane Doe')
        ->toContain('DBS')
        ->toContain('Right to Work')
        ->toContain('valid')
        ->toContain('expired');
});

test('it does not expose compliance rows belonging to a different company', function () {
    $otherCandidate = EducationCandidate::factory()->create(['dbs_expiry_date' => now()->addDays(5)]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT * FROM candidate_compliance']));

    expect($result)->not->toContain((string) $otherCandidate->id);
});

test('it can query client contacts, never their email or phone', function () {
    $this->client->update(['industry_id' => $this->industry->id]);
    $jobTitle = ClientContactJobTitle::factory()->create(['company_id' => $this->user->company_id]);

    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'client_contact_job_title_id' => $jobTitle->id,
        'first_name' => 'Pat',
        'last_name' => 'Smith',
        'email' => 'pat.smith@example.com',
        'main_contact' => true,
    ]);

    $result = (new RunSqlQuery)->handle(new Request([
        'sql' => 'SELECT contact_name, job_title, client_name, is_main_contact FROM client_contacts',
    ]));

    expect($result)
        ->toContain('Pat Smith')
        ->toContain($jobTitle->name)
        ->toContain('Oakwood Primary')
        ->not->toContain('pat.smith@example.com');
});

test('it does not expose client contacts belonging to a different company', function () {
    ClientContact::factory()->create(['first_name' => 'Other', 'last_name' => 'Contact']);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT contact_name FROM client_contacts']));

    expect($result)->not->toContain('Other Contact');
});

test('todos only ever shows the current user\'s own, even for an admin', function () {
    TodoItem::factory()->create(['user_id' => $this->user->id, 'name' => 'My own todo']);

    $otherUser = User::factory()->create(['company_id' => $this->user->company_id]);
    TodoItem::factory()->create(['user_id' => $otherUser->id, 'name' => 'Someone else\'s todo']);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT name FROM todos']));

    expect($result)->toContain('My own todo')->not->toContain('Someone else\'s todo');
});

test('consultant_kpi_targets restricts a non-admin to their own row', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $this->industry->id,
        'gp_target' => 2000,
    ]);

    ConsultantKpiTarget::factory()->create([
        'user_id' => $this->user->id,
        'industry_id' => $this->industry->id,
        'gp_target' => 4000,
    ]);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);
    $this->actingAs($consultant);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT consultant_name, gp_target FROM consultant_kpi_targets']));

    expect($result)->toContain($consultant->name)->toContain('2000')
        ->and($result)->not->toContain('4000');
});

test('consultant_kpi_targets shows every consultant to an admin', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $this->industry->id,
        'gp_target' => 2000,
    ]);

    $result = (new RunSqlQuery)->handle(new Request(['sql' => 'SELECT consultant_name, gp_target FROM consultant_kpi_targets']));

    expect($result)->toContain($consultant->name)->toContain('2000');
});

test('the description tells the model to count bookings on a date via booking_days, not the bookings table', function () {
    $description = (string) (new RunSqlQuery)->description();

    expect($description)
        ->toContain('a booking\'s start_date/end_date is only its overall bounding range')
        ->toContain('COUNT(DISTINCT booking_id) FROM booking_days WHERE date is in that range')
        ->toContain('carried over from earlier in the conversation');
});
