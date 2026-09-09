<?php

use App\Models\Company;
use App\Models\Industry;
use App\Models\ReferenceForm;
use App\Models\ReferenceFormField;
use App\Models\User;
use App\Services\References\ReferenceFormDraft;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['trading_name' => 'Apple Education']);
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->referenceForm = ReferenceForm::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Professional',
        'needs_position_and_organisation' => true,
    ]);
});

test('the preview renders every question, in order, with no way to submit', function () {
    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Worked From',
        'field_type' => 'date',
        'sort_order' => 1,
    ]);

    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Would you re-employ this candidate?',
        'field_type' => 'radio',
        'options' => ['Yes', 'No'],
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertOk()
        ->assertSee('Worked From')
        ->assertSee('Would you re-employ this candidate?')
        ->assertSee('Please Confirm')
        ->assertSee('School / Organisation Name')
        ->assertDontSee('Submit Reference');

    expect(strpos($response->content(), 'Worked From'))
        ->toBeLessThan(strpos($response->content(), 'Would you re-employ this candidate?'));
});

test('the preview substitutes :company_name in a question the same way the referee form does', function () {
    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Please inform :company_name of any concerns',
        'field_type' => 'textarea',
        'sort_order' => 1,
    ]);

    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertOk()
        ->assertSee('Please inform Apple Education of any concerns')
        ->assertDontSee(':company_name');
});

test('the preview omits the position and organisation fields when the form does not ask for them', function () {
    $this->referenceForm->update(['needs_position_and_organisation' => false]);

    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Worked From',
        'field_type' => 'date',
        'sort_order' => 1,
    ]);

    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertOk()
        ->assertSee('Please Confirm')
        ->assertDontSee('School / Organisation Name');
});

test('the preview of a statement-only form explains that no questions are sent', function () {
    $this->referenceForm->update(['is_statement_only' => true]);

    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertOk()
        ->assertSee('statement-only form');
});

test('a guest cannot preview a reference form', function () {
    $this->get(route('reference-forms.preview', $this->referenceForm))
        ->assertRedirect()
        ->assertDontSee($this->referenceForm->name);
});

test('a consultant cannot preview a reference form', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');

    // A 403 for a signed-in user is turned into a redirect to their own
    // panel by the handler in bootstrap/app.php, so that's what denial
    // looks like here rather than a 403 page.
    $this->actingAs($consultant)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertRedirect('/crm');
});

test('an admin at another company cannot preview a reference form', function () {
    $otherCompany = Company::factory()->create();
    $otherAdmin = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherCompany->industries()->attach($this->industry);
    $otherAdmin->industries()->attach($this->industry);
    $otherAdmin->assignRole('admin');

    Cache::put("user.{$otherAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$otherAdmin->id}.active_industry_id", $this->industry->id);

    $this->actingAs($otherAdmin)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertNotFound();
});

test('the preview shows unsaved builder state in draft mode, and the saved form without it', function () {
    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Saved question',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    ReferenceFormDraft::store($this->user, $this->referenceForm, [
        'name' => 'Renamed but not saved',
        'is_statement_only' => false,
        'needs_position_and_organisation' => true,
        'fields' => [
            ['label' => 'Unsaved question', 'field_type' => 'text', 'required' => true],
        ],
    ]);

    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', ['referenceForm' => $this->referenceForm, 'draft' => 1]))
        ->assertOk()
        ->assertSee('Unsaved question')
        ->assertSee('Renamed but not saved')
        ->assertDontSee('Saved question');

    // Without ?draft=1 the same page is the saved form, untouched by whatever
    // is parked in the draft.
    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', $this->referenceForm))
        ->assertOk()
        ->assertSee('Saved question')
        ->assertDontSee('Unsaved question');
});

test('a draft is only ever visible to the user who is editing', function () {
    $colleague = User::factory()->create(['company_id' => $this->company->id]);
    $colleague->industries()->attach($this->industry);
    $colleague->assignRole('admin');

    Cache::put("user.{$colleague->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$colleague->id}.active_industry_id", $this->industry->id);

    ReferenceFormField::factory()->create([
        'reference_form_id' => $this->referenceForm->id,
        'label' => 'Saved question',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    ReferenceFormDraft::store($this->user, $this->referenceForm, [
        'fields' => [['label' => 'Half-written question', 'field_type' => 'text']],
    ]);

    $this->actingAs($colleague)
        ->get(route('reference-forms.preview', ['referenceForm' => $this->referenceForm, 'draft' => 1]))
        ->assertOk()
        ->assertSee('Saved question')
        ->assertDontSee('Half-written question');
});

test('a draft question that is still half-written is left out of the preview', function () {
    ReferenceFormDraft::store($this->user, $this->referenceForm, [
        'fields' => [
            ['label' => 'Complete question', 'field_type' => 'text', 'required' => true],
            ['label' => '', 'field_type' => 'text'],
            ['label' => 'No answer type chosen yet', 'field_type' => ''],
        ],
    ]);

    $this->actingAs($this->user)
        ->get(route('reference-forms.preview', ['referenceForm' => $this->referenceForm, 'draft' => 1]))
        ->assertOk()
        ->assertSee('Complete question')
        ->assertDontSee('No answer type chosen yet');
});

test('the draft preview groups consecutive questions under a shared section heading', function () {
    ReferenceFormDraft::store($this->user, $this->referenceForm, [
        'fields' => [
            ['label' => 'First', 'field_type' => 'text', 'section_heading' => 'About the role'],
            ['label' => 'Second', 'field_type' => 'text', 'section_heading' => 'About the role'],
            ['label' => 'Third', 'field_type' => 'text', 'section_heading' => null],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reference-forms.preview', ['referenceForm' => $this->referenceForm, 'draft' => 1]))
        ->assertOk()
        ->assertSee('About the role');

    expect(substr_count($response->content(), 'About the role'))->toBe(1);
});
