<?php

use App\Ai\Tools\SearchCandidates;
use App\Enums\PaymentMethod;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\Qualification;
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
});

test('it returns candidates matching the status filter', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'PGCE',
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'qualification_id' => $qualification->id,
    ]);

    $status = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Live',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    $result = (new SearchCandidates)->handle(new Request(['status' => 'Live']));

    expect($result)->toContain('Jane Doe')
        ->and($result)->toContain('Live')
        ->and($result)->toContain('PGCE');
});

test('it filters by skill', function () {
    $matching = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Skilled',
        'last_name' => 'Candidate',
    ]);
    $nonMatching = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Unskilled',
        'last_name' => 'Candidate',
    ]);

    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Phonics',
    ]);
    $matching->skills()->attach($skill);

    $result = (new SearchCandidates)->handle(new Request(['skill' => 'Phonics']));

    expect($result)->toContain('Skilled Candidate')
        ->and($result)->not->toContain('Unskilled Candidate');
});

test('it filters by region but never echoes the matched location back', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Leah',
        'last_name' => 'Reston',
        'city' => 'Leicester',
    ]);
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Mia',
        'last_name' => 'Norwood',
        'city' => 'Manchester',
    ]);

    $result = (new SearchCandidates)->handle(new Request(['region' => 'Leicester']));

    expect($result)->toContain('Leah Reston')
        ->and($result)->not->toContain('Mia Norwood')
        ->and($result)->not->toContain('Leicester');
});

test('it never exposes compliance or personal-identity fields', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'ni_number' => 'QQ123456C',
        'dbs_certificate_number' => '001234567890',
        'address' => '1 Secret Street',
    ]);

    $result = (new SearchCandidates)->handle(new Request([]));

    expect($result)->not->toContain('QQ123456C')
        ->and($result)->not->toContain('001234567890')
        ->and($result)->not->toContain('Secret Street');
});

test('it links each candidate to their edit page', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $result = (new SearchCandidates)->handle(new Request([]));

    $url = EducationCandidateResource::getUrl('edit', ['record' => $candidate]);
    expect($result)->toContain("[Jane Doe]({$url})");
});

test('it returns a plain message when nothing matches', function () {
    $result = (new SearchCandidates)->handle(new Request(['status' => 'Nonexistent']));

    expect($result)->toBe('No candidates matched.');
});

test('it does not return candidates from a different company', function () {
    EducationCandidate::factory()->create(['first_name' => 'Other', 'last_name' => 'Company']);

    $result = (new SearchCandidates)->handle(new Request([]));

    expect($result)->toBe('No candidates matched.');
});

test('it paginates results and reports how many more match', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Paginated Qualification',
    ]);

    EducationCandidate::factory()->count(51)->create([
        'company_id' => $this->user->company_id,
        'qualification_id' => $qualification->id,
    ]);

    $firstPage = (new SearchCandidates)->handle(new Request(['qualification' => 'Paginated Qualification']));

    expect($firstPage)->toContain('Showing 50 of 51 — 1 more match. Ask to see the next 50 to continue.');

    $secondPage = (new SearchCandidates)->handle(new Request(['qualification' => 'Paginated Qualification', 'offset' => 50]));

    expect($secondPage)->not->toContain('more match');
});

test('it filters by pool', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'Shortlist',
    ]);

    $match = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Pooled',
        'last_name' => 'Candidate',
    ]);
    $pool->candidatesOfType(EducationCandidate::class)->attach($match->id);

    $nonMatch = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Unpooled',
        'last_name' => 'Candidate',
    ]);

    $result = (new SearchCandidates)->handle(new Request(['pool' => 'Shortlist']));

    expect($result)->toContain('Pooled Candidate')
        ->and($result)->not->toContain('Unpooled Candidate');
});

test('it filters by payment method', function () {
    $paye = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Paye',
        'last_name' => 'Candidate',
        'payment_method' => PaymentMethod::Paye,
    ]);
    $umbrella = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Umbrella',
        'last_name' => 'Candidate',
        'payment_method' => PaymentMethod::Umbrella,
    ]);

    $result = (new SearchCandidates)->handle(new Request(['payment_method' => 'umbrella']));

    expect($result)->toContain('Umbrella Candidate')
        ->and($result)->not->toContain('Paye Candidate');
});

test('a consultant only sees their own candidates', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $own = EducationCandidate::factory()->create([
        'company_id' => $consultant->company_id,
        'first_name' => 'Own',
        'last_name' => 'Candidate',
        'consultant_id' => $consultant->id,
    ]);
    $other = EducationCandidate::factory()->create([
        'company_id' => $consultant->company_id,
        'first_name' => 'Someone',
        'last_name' => 'Elses',
        'consultant_id' => $this->user->id,
    ]);

    $result = (new SearchCandidates)->handle(new Request([]));

    expect($result)->toContain('Own Candidate')
        ->and($result)->not->toContain('Someone Elses');
});
