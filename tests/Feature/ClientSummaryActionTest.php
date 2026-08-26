<?php

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Support\ClientSummaryAction;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
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

test('the quick view action is visible for a client row and mounts without error', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListClients::class)
        ->assertTableActionVisible('viewClientSummary', $client)
        ->mountTableAction('viewClientSummary', $client)
        ->assertSuccessful();
});

describe('data', function () {
    test('reflects the clients own details', function () {
        $clientType = ClientType::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Primary School']);

        $client = Client::factory()->create([
            'company_id' => $this->user->company_id,
            'industry_id' => $this->industry->id,
            'consultant_id' => $this->user->id,
            'client_type_id' => $clientType->id,
            'phone' => '01234567890',
            'city' => 'Birmingham',
            'address' => '1 School Lane',
            'postcode' => 'B1 1AA',
            'key_stages' => ['keystage_1', 'keystage_2'],
        ]);

        $data = ClientSummaryAction::data($client);

        expect($data['client_type'])->toBe('Primary School')
            ->and($data['consultant'])->toBe($this->user->name)
            ->and($data['phone'])->toBe('01234567890')
            ->and($data['address'])->toBe('1 School Lane, Birmingham, B1 1AA')
            ->and($data['key_stages'])->toBe('keystage_1, keystage_2');
    });

    test('falls back to sensible defaults when every optional field is blank', function () {
        $client = Client::factory()->create([
            'company_id' => $this->user->company_id,
            'industry_id' => $this->industry->id,
            'client_type_id' => null,
            'consultant_id' => null,
            'website' => null,
            'notes' => null,
            'key_stages' => null,
            'address' => null,
            'city' => null,
            'postcode' => null,
            'county' => null,
        ]);

        $data = ClientSummaryAction::data($client);

        expect($data['client_type'])->toBeNull()
            ->and($data['consultant'])->toBeNull()
            ->and($data['website'])->toBeNull()
            ->and($data['notes'])->toBeNull()
            ->and($data['key_stages'])->toBeNull()
            ->and($data['address'])->toBeNull()
            ->and($data['vacancies_label'])->toBe('None yet')
            ->and($data['vacancies_color'])->toBe('gray');
    });

    test('reports open and total vacancy counts', function () {
        $client = Client::factory()->create([
            'company_id' => $this->user->company_id,
            'industry_id' => $this->industry->id,
        ]);

        $jobTitle = JobTitle::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id]);
        $openStatus = JobStatus::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'is_filled_status' => false]);
        $filledStatus = JobStatus::factory()->create(['company_id' => $this->user->company_id, 'industry_id' => $this->industry->id, 'is_filled_status' => true]);

        Vacancy::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $client->id, 'job_title_id' => $jobTitle->id, 'job_status_id' => $openStatus->id]);
        Vacancy::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $client->id, 'job_title_id' => $jobTitle->id, 'job_status_id' => $filledStatus->id]);

        $data = ClientSummaryAction::data($client);

        expect($data['vacancies_label'])->toBe('1 open / 2 total')
            ->and($data['vacancies_color'])->toBe('success');
    });
});
