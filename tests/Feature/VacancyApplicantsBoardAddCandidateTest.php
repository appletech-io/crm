<?php

use App\Enums\ActivityType;
use App\Filament\Widgets\VacancyApplicantsBoard;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->firstStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->firstStatus->id,
    ]);
});

test('a candidate not already on the board is offered by the add candidate action', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jamie',
        'last_name' => 'Fox',
    ]);

    Livewire::test(VacancyApplicantsBoard::class, ['record' => $this->vacancy])
        ->mountAction('addCandidate')
        ->assertFormFieldExists('candidate_id', function (Select $field) use ($candidate): bool {
            $options = $field->getOptions();

            return ($options[$candidate->id] ?? null) === 'Jamie Fox';
        });
});

test('a candidate already on the board is not offered again', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'job_status_id' => $this->firstStatus->id,
    ]);

    Livewire::test(VacancyApplicantsBoard::class, ['record' => $this->vacancy])
        ->mountAction('addCandidate')
        ->assertFormFieldExists('candidate_id', fn (Select $field): bool => ! array_key_exists($candidate->id, $field->getOptions()));
});

test('a candidate from a different company is not offered', function () {
    $otherCompany = Company::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $otherCompany->id]);

    Livewire::test(VacancyApplicantsBoard::class, ['record' => $this->vacancy])
        ->mountAction('addCandidate')
        ->assertFormFieldExists('candidate_id', fn (Select $field): bool => ! array_key_exists($otherCandidate->id, $field->getOptions()));
});

test('submitting the action creates an application that lands in the first pipeline column', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(VacancyApplicantsBoard::class, ['record' => $this->vacancy])
        ->callAction('addCandidate', data: ['candidate_id' => $candidate->id])
        ->assertNotified();

    $application = VacancyApplication::query()
        ->where('vacancy_id', $this->vacancy->id)
        ->where('candidate_type', EducationCandidate::class)
        ->where('candidate_id', $candidate->id)
        ->first();

    expect($application)->not->toBeNull()
        ->and($application->job_status_id)->toBe($this->firstStatus->id);

    expect($this->vacancy->activities()->where('type', ActivityType::Note->value)->exists())->toBeTrue();
});

test('submitting the action for a candidate already on the board does not duplicate the application', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $secondStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $existing = VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'job_status_id' => $secondStatus->id,
    ]);

    Livewire::test(VacancyApplicantsBoard::class, ['record' => $this->vacancy])
        ->callAction('addCandidate', data: ['candidate_id' => $candidate->id]);

    expect(VacancyApplication::where('vacancy_id', $this->vacancy->id)->count())->toBe(1)
        ->and($existing->refresh()->job_status_id)->toBe($secondStatus->id);
});
