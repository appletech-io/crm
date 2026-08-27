<?php

use App\Filament\EducationCandidate\Pages\Compliance;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => Candidate::class,
    ]);
    $this->user->assignRole('candidate');
    $this->user->industries()->attach($this->industry);
    $this->actingAs($this->user);
});

test('the candidate can see the wizard step for a required compliance item and save their own values', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'DBS',
    ]);
    $field = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text', 'name' => 'DBS Number']);
    $this->jobTitle->complianceItems()->attach($item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(Compliance::class)
        ->assertSuccessful()
        ->assertSee('DBS')
        ->assertSee('DBS Number')
        ->fillForm(["field_{$field->id}" => 'DBS-1234'])
        ->call('save');

    expect($this->candidate->complianceValues()->where('compliance_item_field_id', $field->id)->first()->text_value)
        ->toBe('DBS-1234');
});
