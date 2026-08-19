<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Filament\Support\AddToCandidatePoolAction;
use App\Models\CandidatePool;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

/** Pulls the pool Select component out of the bulk action's schema for direct, non-UI assertions. */
function candidatePoolSelectFor(string $candidateModelClass): Select
{
    $bulkAction = AddToCandidatePoolAction::bulk($candidateModelClass);
    $components = (new ReflectionProperty($bulkAction, 'schema'))->getValue($bulkAction);

    return $components[0];
}

test('selected education candidates on the All Candidates tab can be added to an existing pool', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'Cover Teachers',
    ]);

    $candidates = EducationCandidate::factory()->count(2)->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('addToPool', $candidates, data: ['candidate_pool_id' => $pool->id]);

    expect($pool->candidatesOfType(EducationCandidate::class)->pluck('id')->sort()->values()->all())
        ->toBe($candidates->pluck('id')->sort()->values()->all());
});

test('selected education candidates on the Search tab can be added to an existing pool', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'Cover Teachers',
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
    ]);
    $candidate->statuses()->create([
        'candidate_status_id' => CandidateStatus::factory()->create([
            'company_id' => $this->user->company_id,
            'industry_id' => $this->industry->id,
            'name' => 'Live',
        ])->id,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableBulkAction('addToPool', [$candidate], data: ['candidate_pool_id' => $pool->id]);

    expect($pool->candidatesOfType(EducationCandidate::class)->pluck('id')->all())->toBe([$candidate->id]);
});

test('selected healthcare candidates on the All Candidates tab can be added to an existing pool', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $healthcareIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $healthcareIndustry->id);

    $pool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $healthcareIndustry->id,
        'user_id' => $this->user->id,
        'name' => 'Bank Nurses',
    ]);

    $candidates = HealthcareCandidate::factory()->count(2)->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('addToPool', $candidates, data: ['candidate_pool_id' => $pool->id]);

    expect($pool->candidatesOfType(HealthcareCandidate::class)->pluck('id')->sort()->values()->all())
        ->toBe($candidates->pluck('id')->sort()->values()->all());
});

test('adding a healthcare candidate to a pool does not disturb an education candidate already in a separate pool', function () {
    $educationPool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'Education Pool',
    ]);
    $educationCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $educationPool->candidatesOfType(EducationCandidate::class)->attach($educationCandidate->id);

    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $healthcarePool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $healthcareIndustry->id,
        'user_id' => $this->user->id,
        'name' => 'Healthcare Pool',
    ]);
    Cache::put("user.{$this->user->id}.active_industry", $healthcareIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $healthcareIndustry->id);

    $healthcareCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->callTableBulkAction('addToPool', [$healthcareCandidate], data: ['candidate_pool_id' => $healthcarePool->id]);

    expect($educationPool->candidatesOfType(EducationCandidate::class)->pluck('id')->all())->toBe([$educationCandidate->id])
        ->and($healthcarePool->candidatesOfType(HealthcareCandidate::class)->pluck('id')->all())->toBe([$healthcareCandidate->id]);
});

test('the pool select only offers pools the consultant owns or shared company pools', function () {
    $ownPool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'My Pool',
    ]);

    $companyPool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => null,
        'company_pool' => true,
        'name' => 'Company Pool',
    ]);

    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $othersPrivatePool = CandidatePool::create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $otherConsultant->id,
        'name' => 'Someone Elses Private Pool',
    ]);

    $options = candidatePoolSelectFor(EducationCandidate::class)->getOptions();

    expect($options)->toHaveKey($ownPool->id)
        ->toHaveKey($companyPool->id)
        ->not->toHaveKey($othersPrivatePool->id);
});

test('creating a new private pool inline scopes it to the current user and industry', function () {
    $poolId = candidatePoolSelectFor(EducationCandidate::class)
        ->getCreateOptionUsing()(['name' => 'Brand New Pool', 'company_pool' => false]);

    $pool = CandidatePool::findOrFail($poolId);

    expect($pool->name)->toBe('Brand New Pool')
        ->and($pool->industry_id)->toBe($this->industry->id)
        ->and($pool->user_id)->toBe($this->user->id)
        ->and($pool->company_pool)->toBeFalsy();
});

test('creating a new company pool inline leaves it unowned so every consultant can see it', function () {
    $poolId = candidatePoolSelectFor(EducationCandidate::class)
        ->getCreateOptionUsing()(['name' => 'Shared Pool', 'company_pool' => true]);

    $pool = CandidatePool::findOrFail($poolId);

    expect($pool->user_id)->toBeNull()
        ->and($pool->company_pool)->toBeTruthy();
});
