<?php

use App\Filament\Resources\SampleProfiles\Pages\CreateSampleProfile;
use App\Filament\Resources\SampleProfiles\Pages\ListSampleProfiles;
use App\Filament\Resources\SampleProfiles\SampleProfileResource;
use App\Models\Company;
use App\Models\Industry;
use App\Models\SampleProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListSampleProfiles::class)->assertSuccessful();
});

test('non-admin cannot access the sample profiles resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $this->get('/crm/sample-profiles')->assertRedirect('/crm');
});

test('an admin can upload a sample profile', function () {
    $file = UploadedFile::fake()->create('sample.pdf', 10, 'application/pdf');

    Livewire::test(CreateSampleProfile::class)
        ->fillForm(['path' => $file])
        ->call('create')
        ->assertHasNoFormErrors();

    $sample = SampleProfile::first();

    expect($sample)->not->toBeNull()
        ->and($sample->company_id)->toBe($this->company->id)
        ->and($sample->industry_id)->toBe($this->industry->id)
        ->and($sample->uploaded_by)->toBe($this->user->id);

    Storage::disk('local')->assertExists($sample->path);
});

test('a sixth sample profile is rejected', function () {
    SampleProfile::factory()->count(5)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $file = UploadedFile::fake()->create('sample.pdf', 10, 'application/pdf');

    Livewire::test(CreateSampleProfile::class)
        ->fillForm(['path' => $file])
        ->call('create');

    expect(SampleProfile::where('company_id', $this->company->id)->where('industry_id', $this->industry->id)->count())->toBe(5);
});

test('the create action is disabled once the limit is reached', function () {
    SampleProfile::factory()->count(5)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListSampleProfiles::class)
        ->assertActionDisabled('create');
});

test('sample profiles are scoped to the current company and industry', function () {
    $otherCompany = Company::factory()->create();
    SampleProfile::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
    ]);

    $mine = SampleProfile::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListSampleProfiles::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCountTableRecords(1);
});

test('MAX_PER_SECTOR is 5', function () {
    expect(SampleProfileResource::MAX_PER_SECTOR)->toBe(5);
});
