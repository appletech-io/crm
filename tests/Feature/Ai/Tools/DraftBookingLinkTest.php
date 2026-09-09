<?php

use App\Ai\Tools\DraftBookingLink;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
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

    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
});

test('it links to the create booking page with the candidate, client, job title, and single date pre-filled', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Oakwood School']);
    $jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Teacher',
    ]);

    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'client_name' => 'Oakwood',
        'job_title' => 'Teacher',
        'start_date' => '2026-09-07',
    ]));

    $url = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'dates' => ['2026-09-07'],
    ]);

    expect($result)->toContain("[Open a pre-filled booking for Jane Doe]({$url})")
        ->and($result)->toContain('nothing has been created yet');
});

test('it never actually creates a booking record', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'client_name' => $client->name,
        'start_date' => '2026-09-07',
    ]));

    expect(Booking::count())->toBe(0);
});

test('a multi-day range expands to every weekday and skips weekends', function () {
    // 2026-09-07 is a Monday, 2026-09-13 is the following Sunday.
    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-13',
    ]));

    $url = BookingResource::getUrl('create', [
        'candidate_id' => $this->candidate->id,
        'dates' => ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'],
    ]);

    expect($result)->toContain($url);
});

test('a weekend-only range reports there are no weekdays rather than drafting an empty booking', function () {
    // 2026-09-12 and 2026-09-13 are a Saturday and Sunday.
    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'start_date' => '2026-09-12',
        'end_date' => '2026-09-13',
    ]));

    expect($result)->toBe('That date range has no weekdays in it.');
});

test('no matching candidate is reported rather than guessed', function () {
    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Nonexistent Person',
        'start_date' => '2026-09-07',
    ]));

    expect($result)->toBe('No candidate matching "Nonexistent Person" was found.');
});

test('multiple matching candidates are reported rather than guessed', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane',
        'start_date' => '2026-09-07',
    ]));

    expect($result)->toContain('Multiple candidates match "Jane"')
        ->and($result)->toContain('Jane Doe')
        ->and($result)->toContain('Jane Smith');
});

test('no matching client is reported rather than guessed', function () {
    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'client_name' => 'Nonexistent School',
        'start_date' => '2026-09-07',
    ]));

    expect($result)->toBe('No client matching "Nonexistent School" was found.');
});

test('a candidate belonging to a different consultant can still be drafted, matching the create form\'s own options', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $otherConsultant->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $otherConsultant->id,
        'first_name' => 'Someone',
        'last_name' => 'Else',
    ]);

    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Someone Else',
        'start_date' => '2026-09-07',
    ]));

    expect($result)->toContain((string) $candidate->id);
});

test('a client belonging to a different consultant is not offered, matching the create form\'s own options', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $otherConsultant->assignRole('consultant');
    $this->actingAs($otherConsultant);
    Cache::put("user.{$otherConsultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$otherConsultant->id}.active_industry_id", $this->industry->id);

    Client::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Someone Elses School', 'consultant_id' => $this->user->id]);

    $result = (new DraftBookingLink)->handle(new Request([
        'candidate_name' => 'Jane Doe',
        'client_name' => 'Someone Elses School',
        'start_date' => '2026-09-07',
    ]));

    expect($result)->toBe('No client matching "Someone Elses School" was found.');
});
