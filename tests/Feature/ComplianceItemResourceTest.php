<?php

use App\Enums\ComplianceItemDataType;
use App\Filament\Resources\ComplianceItems\ComplianceItemResource;
use App\Filament\Resources\ComplianceItems\Pages\CreateComplianceItem;
use App\Filament\Resources\ComplianceItems\Pages\EditComplianceItem;
use App\Filament\Resources\ComplianceItems\Pages\ListComplianceItems;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    // 'generic' is the slug this session wired to Candidate::class in
    // Industry::$candidateModelMap — canViewAny() on this resource is
    // keyed off that mapping, not a fixed slug name.
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListComplianceItems::class)
        ->assertSuccessful();
});

test('this resource is not visible for the education or healthcare industries', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $educationIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $educationIndustry->id);

    expect(ComplianceItemResource::canViewAny())->toBeFalse();

    $this->get('/crm/compliance-items')->assertRedirect('/crm');
});

test('non-admin cannot access compliance items resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    $this->get('/crm/compliance-items')->assertRedirect('/crm');
});

test('list only shows compliance items for the active company and industry', function () {
    $ownItem = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $otherItem = ComplianceItem::factory()->create();

    Livewire::test(ListComplianceItems::class)
        ->assertCanSeeTableRecords([$ownItem])
        ->assertCanNotSeeTableRecords([$otherItem]);
});

test('can create a compliance item, and industry_id is stamped from the active industry', function () {
    Livewire::test(CreateComplianceItem::class)
        ->fillForm([
            'name' => 'DBS Check',
            'description' => 'A current DBS certificate.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = ComplianceItem::where('name', 'DBS Check')->first();

    expect($item)->not->toBeNull()
        ->and($item->company_id)->toBe($this->company->id)
        ->and($item->industry_id)->toBe($this->industry->id);
});

test('name is required', function () {
    Livewire::test(CreateComplianceItem::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

test('multiple fields with different data types can be added to an item via its edit page', function () {
    $item = ComplianceItem::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(EditComplianceItem::class, ['record' => $item->getRouteKey()])
        ->fillForm([
            'fields' => [
                'field-1' => ['name' => 'DBS Number', 'data_type' => 'text'],
                'field-2' => ['name' => 'Issue Date', 'data_type' => 'date'],
                'field-3' => ['name' => 'Expiry Date', 'data_type' => 'date_expiry'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh();

    expect($item->fields)->toHaveCount(3);

    expect($item->fields->pluck('data_type')->map(fn (ComplianceItemDataType $type) => $type->value)->sort()->values()->all())
        ->toBe(['date', 'date_expiry', 'text']);
});

test('can edit and delete a compliance item', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Name',
    ]);
    ComplianceItemField::factory()->create(['compliance_item_id' => $item->id]);

    Livewire::test(EditComplianceItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->fresh()->name)->toBe('New Name');

    Livewire::test(EditComplianceItem::class, ['record' => $item->getRouteKey()])
        ->callAction('delete');

    expect(ComplianceItem::find($item->id))->toBeNull();
});
