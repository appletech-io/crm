<?php

use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\CandidateStatus;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('the status filter accepts multiple values and matches candidates with any of them', function () {
    $statusA = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $statusB = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
    $statusC = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $candidateA = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidateA->statuses()->create(['candidate_status_id' => $statusA->id]);

    $candidateB = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidateB->statuses()->create(['candidate_status_id' => $statusB->id]);

    $candidateC = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidateC->statuses()->create(['candidate_status_id' => $statusC->id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->filterTable('status', [$statusA->id, $statusB->id])
        ->assertCanSeeTableRecords([$candidateA, $candidateB])
        ->assertCanNotSeeTableRecords([$candidateC]);
});
