<?php

use App\Ai\Tools\CandidateComplianceExpiry;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

function setActiveIndustry(User $user, string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    return $industry;
}

test('it links the candidate to their edit page', function () {
    setActiveIndustry($this->user, 'education');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'safeguarding_expiry_date' => now()->addDay(),
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'safeguarding']));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toContain("[Jane Doe]({$url})");
});

test('it returns candidates with a requirement expiring soon', function () {
    setActiveIndustry($this->user, 'education');

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'safeguarding_expiry_date' => now()->addDay(),
    ]);
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Far',
        'last_name' => 'Future',
        'safeguarding_expiry_date' => now()->addYear(),
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'safeguarding']));

    expect($result)->toContain('Jane Doe')
        ->and($result)->toContain('Safeguarding Training expires')
        ->and($result)->not->toContain('Far Future');
});

test('it includes already-expired requirements', function () {
    setActiveIndustry($this->user, 'education');

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'dbs_expiry_date' => now()->subDays(5),
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs']));

    expect($result)->toContain('Jane Doe')
        ->and($result)->toContain('DBS expired');
});

test('it excludes candidates outside the expiry window', function () {
    setActiveIndustry($this->user, 'education');

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Far',
        'last_name' => 'Future',
        'dbs_expiry_date' => now()->addYear(),
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs']));

    expect($result)->toBe('No candidates have compliance requirements expiring within 3 days.');
});

test('it never exposes certificate or document numbers', function () {
    setActiveIndustry($this->user, 'education');

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'dbs_expiry_date' => now()->addDay(),
        'dbs_certificate_number' => '001234567890',
        'ni_number' => 'QQ123456C',
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs']));

    expect($result)->not->toContain('001234567890')
        ->and($result)->not->toContain('QQ123456C');
});

test('an unrecognized requirement for the sector falls back to checking everything applicable', function () {
    setActiveIndustry($this->user, 'healthcare');

    HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'dbs_expiry_date' => now()->addDay(),
    ]);

    // Benedict's Law does not exist for healthcare — should not error, and
    // should fall back to checking every field that does apply.
    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'benedicts_law']));

    expect($result)->toContain('Jane Doe')
        ->and($result)->toContain('DBS expires');
});

test('the expiry window can be overridden', function () {
    setActiveIndustry($this->user, 'education');

    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'dbs_expiry_date' => now()->addDays(20),
    ]);

    $default = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs']));
    $extended = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs', 'days' => 30]));

    expect($default)->toBe('No candidates have compliance requirements expiring within 3 days.')
        ->and($extended)->toContain('Jane Doe');
});

test('healthcare uses its own default expiry window', function () {
    setActiveIndustry($this->user, 'healthcare');

    HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'dbs_expiry_date' => now()->addDays(10),
    ]);

    $result = (new CandidateComplianceExpiry)->handle(new Request(['requirement' => 'dbs']));

    expect($result)->toContain('Jane Doe');
});
