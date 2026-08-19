<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

test('selected education candidates can be exported to a csv file', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $candidates = EducationCandidate::factory()->count(2)->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->selectTableRecords($candidates->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->callAction(TestAction::make('exportCsv')->table()->bulk())
        ->assertFileDownloaded();
});

test('selected healthcare candidates can be exported to a csv file', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $candidates = HealthcareCandidate::factory()->count(2)->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'all')
        ->selectTableRecords($candidates->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->callAction(TestAction::make('exportCsv')->table()->bulk())
        ->assertFileDownloaded();
});
