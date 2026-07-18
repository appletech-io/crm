<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\Client;
use App\Models\EducationCandidate;
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

test('a candidate can be found in global search by mobile number', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'mobile' => '07700900123',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', '07700900123')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain($candidate->first_name);
});

test('a candidate can be found in global search by email', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Stephen',
        'email' => 'stephen@example.com',
    ]);

    $results = Livewire::test(GlobalSearch::class)
        ->set('search', 'stephen@example.com')
        ->instance()
        ->getResults();

    $titles = $results->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain($candidate->first_name);
});
