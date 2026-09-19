<?php

use App\Actions\Users\GenerateDefaultQuickLinks;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\UserQuickLinks\Pages\CreateUserQuickLink;
use App\Filament\Resources\UserQuickLinks\Pages\ListUserQuickLinks;
use App\Filament\Resources\UserQuickLinks\UserQuickLinkResource;
use App\Filament\Support\QuickLinkCatalog;
use App\Models\Company;
use App\Models\CompanyIndustry;
use App\Models\Industry;
use App\Models\User;
use App\Models\UserQuickLink;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->education = Industry::factory()->create(['slug' => 'education']);
    $this->healthcare = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach([$this->education->id, $this->healthcare->id]);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->education->id);
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->education->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->education->id);
});

test('education defaults include Job Pipeline, Vacancies and Candidates when Perm is on', function () {
    $links = GenerateDefaultQuickLinks::run();

    expect(collect($links)->pluck('label')->all())->toBe([
        'Job Pipeline', 'Vacancies', 'Candidates', 'Clients', 'Reports', 'My To-Dos',
    ])
        ->and($links)->toHaveCount(UserQuickLinkResource::MAX_QUICK_LINKS)
        ->and($links[2]['url'])->toBe(EducationCandidateResource::getUrl('index'));
});

test('education defaults drop Job Pipeline and Vacancies when Perm is off', function () {
    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->education->id)
        ->update(['uses_perm' => false]);

    $links = GenerateDefaultQuickLinks::run();

    expect(collect($links)->pluck('label')->all())->toBe(['Candidates', 'Clients', 'Reports', 'My To-Dos']);
});

test('healthcare defaults include Bookings, Vacancies and Candidates when both flags are on', function () {
    Cache::put("user.{$this->user->id}.active_industry", $this->healthcare->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->healthcare->id);

    $links = GenerateDefaultQuickLinks::run();

    expect(collect($links)->pluck('label')->all())->toBe([
        'Bookings', 'Vacancies', 'Candidates', 'Clients', 'Reports', 'My To-Dos',
    ])
        ->and($links[2]['url'])->toBe(HealthcareCandidateResource::getUrl('index'));
});

test('healthcare defaults drop Bookings when Bookings is off', function () {
    Cache::put("user.{$this->user->id}.active_industry", $this->healthcare->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->healthcare->id);

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->healthcare->id)
        ->update(['uses_bookings' => false]);

    expect(collect(GenerateDefaultQuickLinks::run())->pluck('label')->all())
        ->toBe(['Vacancies', 'Candidates', 'Clients', 'Reports', 'My To-Dos']);
});

test('healthcare quick links never include Care Logs when care_logging is off', function () {
    Cache::put("user.{$this->user->id}.active_industry", $this->healthcare->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->healthcare->id);

    expect(collect(GenerateDefaultQuickLinks::run())->pluck('label')->all())
        ->not->toContain('Care Logs');
});

test('healthcare quick links include Care Logs once care_logging is on, pushing My To-Dos out of the default 6', function () {
    Cache::put("user.{$this->user->id}.active_industry", $this->healthcare->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->healthcare->id);

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->healthcare->id)
        ->update(['care_logging' => true]);

    // The full catalog now has 7 entries for healthcare — one more than
    // GenerateDefaultQuickLinks::run()'s default cap of 6 — so the last one
    // (My To-Dos) doesn't make the auto-seeded starter set. Still available
    // to add manually via the quick link picker.
    expect(collect(QuickLinkCatalog::availableForCurrentUser())->pluck('label')->all())
        ->toBe(['Bookings', 'Vacancies', 'Candidates', 'Care Logs', 'Clients', 'Reports', 'My To-Dos'])
        ->and(collect(GenerateDefaultQuickLinks::run())->pluck('label')->all())
        ->toBe(['Bookings', 'Vacancies', 'Candidates', 'Care Logs', 'Clients', 'Reports']);
});

test('defaults fall back to a generic set with no active industry', function () {
    Cache::forget("user.{$this->user->id}.active_industry");
    Cache::forget("user.{$this->user->id}.active_industry_id");

    // Clients requires an active industry too, so with none set only the
    // industry-agnostic Reports and My To-Dos links remain.
    expect(collect(GenerateDefaultQuickLinks::run())->pluck('label')->all())
        ->toBe(['Reports', 'My To-Dos']);
});

test('visiting an authenticated page seeds default quick links for a user with none', function () {
    expect($this->user->quickLinks()->count())->toBe(0);

    $this->get('/crm')->assertOk();

    expect($this->user->quickLinks()->count())->toBe(6)
        ->and($this->user->quickLinks()->orderBy('position')->value('label'))->toBe('Job Pipeline');
});

test('default seeding never runs again once a user has deleted all of their quick links', function () {
    $this->get('/crm')->assertOk();
    expect($this->user->quickLinks()->count())->toBe(6);

    $this->user->quickLinks()->delete();

    $this->get('/crm')->assertOk();

    expect($this->user->quickLinks()->count())->toBe(0);
});

test('default seeding is skipped once the user already has any quick link of their own', function () {
    UserQuickLink::factory()->create(['user_id' => $this->user->id, 'label' => 'My Own Link']);

    $this->get('/crm')->assertOk();

    expect($this->user->quickLinks()->count())->toBe(1);
});

test('a user only sees and manages their own quick links', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $mine = UserQuickLink::factory()->create(['user_id' => $this->user->id, 'label' => 'Mine']);
    $theirs = UserQuickLink::factory()->create(['user_id' => $otherUser->id, 'label' => 'Theirs']);

    Livewire::test(ListUserQuickLinks::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);

    $this->get(UserQuickLinkResource::getUrl('edit', ['record' => $theirs]))
        ->assertNotFound();
});

test('a new quick link is automatically owned by the current user', function () {
    Livewire::test(CreateUserQuickLink::class)
        ->fillForm([
            'label' => 'Referral Form',
            'icon' => 'heroicon-o-envelope',
            'url' => 'https://example.com/referral',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(UserQuickLink::where('label', 'Referral Form')->first()->user_id)->toBe($this->user->id);
});

test('picking a page from the quick pick list fills in the label, icon and link fields', function () {
    Livewire::test(CreateUserQuickLink::class)
        ->set('data.quick_pick', ClientResource::getUrl('index'))
        ->assertSet('data.label', 'Clients')
        ->assertSet('data.icon', 'heroicon-o-building-library')
        ->assertSet('data.url', ClientResource::getUrl('index'));
});

test('the quick pick field is not itself persisted onto the record', function () {
    Livewire::test(CreateUserQuickLink::class)
        ->set('data.quick_pick', ClientResource::getUrl('index'))
        ->call('create')
        ->assertHasNoFormErrors();

    $quickLink = UserQuickLink::where('label', 'Clients')->first();

    expect($quickLink)->not->toBeNull()
        ->and($quickLink->url)->toBe(ClientResource::getUrl('index'));
});

test('the quick pick options only offer pages the current user can access', function () {
    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->education->id)
        ->update(['uses_perm' => false]);

    $options = Livewire::test(CreateUserQuickLink::class)
        ->instance()
        ->form
        ->getComponent('quick_pick')
        ->getOptions();

    expect($options)->not->toContain('Job Pipeline')
        ->not->toContain('Vacancies')
        ->toContain('Candidates');
});

test('the create action is hidden once a user already has the maximum of 6 quick links', function () {
    UserQuickLink::factory(6)->sequence(fn ($sequence) => ['position' => $sequence->index])->create(['user_id' => $this->user->id]);

    Livewire::test(ListUserQuickLinks::class)
        ->assertActionHidden('create');
});

test('creating a 7th quick link directly is blocked and redirects back to the list', function () {
    UserQuickLink::factory(6)->sequence(fn ($sequence) => ['position' => $sequence->index])->create(['user_id' => $this->user->id]);

    $this->get(UserQuickLinkResource::getUrl('create'))
        ->assertRedirect(UserQuickLinkResource::getUrl('index'));

    expect($this->user->quickLinks()->count())->toBe(6);
});

test('the topbar shows an icon link for each of the users quick links plus a manage link', function () {
    UserQuickLink::factory()->create([
        'user_id' => $this->user->id,
        'label' => 'Referral Form',
        'icon' => 'heroicon-o-envelope',
        'url' => 'https://example.com/referral',
        'position' => 0,
    ]);

    $this->get('/crm')
        ->assertOk()
        ->assertSeeHtml(e('https://example.com/referral'))
        ->assertSeeHtml(e(UserQuickLinkResource::getUrl('index')));
});
