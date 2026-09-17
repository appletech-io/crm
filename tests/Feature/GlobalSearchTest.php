<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Livewire\GlobalSearch;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;
});

test('clients are globally searchable by phone and email', function () {
    expect(ClientResource::getGloballySearchableAttributes())->toContain('phone', 'contacts.email');
});

test('candidates are globally searchable by phone, mobile, and email', function () {
    expect(EducationCandidateResource::getGloballySearchableAttributes())->toContain('phone', 'mobile', 'email');
});

test('a client can be found in global search by phone number', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Ashlawn School',
        'phone' => '01926123456',
        'consultant_id' => $this->user->id,
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', '01926123456')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain($client->name);
});

test('a client can be found in global search by a contacts email', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Ashlawn School',
        'consultant_id' => $this->user->id,
    ]);

    $client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Clare',
        'last_name' => 'Webster',
        'email' => 'office@ashlawn.example.com',
        'main_contact' => true,
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'office@ashlawn.example.com')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain($client->name);
});

test('a candidate can be found in global search by mobile number, and their full name is shown', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'last_name' => 'Fry',
        'mobile' => '07700900123',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', '07700900123')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain('Stephen Fry');
});

test('a candidate can be found in global search by email, and their full name is shown', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'last_name' => 'Fry',
        'email' => 'stephen@example.com',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'stephen@example.com')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain('Stephen Fry');
});

test('a candidate can be found in global search by last name', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'last_name' => 'Fry',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'Fry')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain('Stephen Fry');
});

test('a healthcare candidate shows their full name in global search', function () {
    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Alice',
        'last_name' => 'Nightingale',
        'email' => 'alice.nightingale@example.com',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'alice.nightingale@example.com')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain('Alice Nightingale');
});

test('a generic candidate shows their full name in global search', function () {
    $genericIndustry = Industry::factory()->create(['slug' => 'generic']);
    Cache::put("user.{$this->user->id}.active_industry", 'generic');
    Cache::put("user.{$this->user->id}.active_industry_id", $genericIndustry->id);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $genericIndustry->id,
        'first_name' => 'Robin',
        'last_name' => 'Shaw',
        'email' => 'robin.shaw@example.com',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'robin.shaw@example.com')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain('Robin Shaw');
});
