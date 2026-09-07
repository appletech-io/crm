<?php

use App\Ai\Agents\DataAssistant;
use App\Livewire\AskAssistant;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('a user with no active industry cannot access the page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // No industries at all means the SetActiveIndustry middleware never sets
    // an active industry, so the component's own mount() guard aborts with a
    // 403 — which the app's global exception handler turns into a redirect
    // back to a panel the user can actually use, rather than a dead-end page.
    $this->actingAs($user)->get('/crm/ask-assistant')->assertRedirect('/crm');
});

test('the page shows the logged-in users own company logo, not the default', function () {
    Storage::fake('local');
    Storage::disk('local')->put('company-logos/acme.png', 'fake logo contents');
    $this->user->company->update(['logo' => 'company-logos/acme.png']);

    $this->get('/crm/ask-assistant')
        ->assertOk()
        ->assertSee(route('company.logo', $this->user->company), false)
        ->assertDontSee(asset('images/appletech.png'), false)
        ->assertSee('<link rel="icon" href="'.route('company.logo.favicon', $this->user->company).'">', false);
});

test('the page renders with the suggested prompts visible', function () {
    Livewire::test(AskAssistant::class)
        ->assertSuccessful()
        ->assertSee('Which candidates are Live?');
});

test('the full page does not show an expand link, since it already is the full page', function () {
    Livewire::test(AskAssistant::class)
        ->assertDontSee('Expand to full page');
});

test('the popup shows an expand link to the full page', function () {
    Livewire::test(AskAssistant::class, ['isPopup' => true])
        ->assertSee('Expand to full page')
        ->assertSeeHtml('href="'.route('ask-assistant').'"');
});

test('the qualification suggestion is tailored to the education sector', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    Livewire::test(AskAssistant::class)
        ->assertSee('Which candidates have a Teacher qualification and are Live?');
});

test('the qualification suggestion is tailored to the healthcare sector', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    Livewire::test(AskAssistant::class)
        ->assertSee('Which candidates have a Nursing qualification and are Live?');
});

test('the prompt help modal lists examples for every tool', function () {
    Livewire::test(AskAssistant::class)
        ->assertSee('What can I ask?')
        ->assertSee('Bookings')
        ->assertSee('Clients')
        ->assertSee('Candidates')
        ->assertSee('Vacancies')
        ->assertSee('Your performance')
        ->assertSee("What's a named consultant's performance this week? (admins only)")
        ->assertSee('Vacancy matches')
        ->assertSee('Can this candidate be booked?')
        ->assertSee('Compliance')
        ->assertSee('DBS expiring soon?')
        ->assertSee('Nearby candidates')
        ->assertSee('Which candidates are within 10 miles of a specific client?')
        ->assertSee('Best-rated candidates nearby')
        ->assertSee('Find me a good candidate near a client')
        ->assertSee('Draft an email')
        ->assertSee('Write a follow-up email to a candidate about their application');
});

test('the prompt help modal best-rated-nearby example is tailored to the education sector', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    Livewire::test(AskAssistant::class)
        ->assertSee('Find me a good maths teacher near a client');
});

test('the prompt help modal compliance example is tailored to the education sector', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    Livewire::test(AskAssistant::class)
        ->assertSee('Safeguarding Training expiring soon?');
});

test('the prompt help modal qualification example is tailored to the sector', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    Livewire::test(AskAssistant::class)
        ->assertSee('Which candidates have a Nursing qualification and are Live?');
});

test('clicking a suggestion populates the prompt field', function () {
    Livewire::test(AskAssistant::class)
        ->call('useSuggestion', "Find clients matching 'Primary'")
        ->assertSet('prompt', "Find clients matching 'Primary'");
});

test('sending a prompt appends both messages using the agent response', function () {
    DataAssistant::fake(['Riverside School has 3 upcoming bookings.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Show me bookings for Riverside School')
        ->call('send')
        ->assertSet('prompt', '')
        ->assertSet('messages.0.role', 'user')
        ->assertSet('messages.0.content', 'Show me bookings for Riverside School')
        ->call('respond')
        ->assertSet('messages.1.role', 'assistant')
        ->assertSet('messages.1.content', 'Riverside School has 3 upcoming bookings.');

    DataAssistant::assertPrompted('Show me bookings for Riverside School');
});

test('the user\'s own message renders as soon as send() runs, before the agent has replied', function () {
    DataAssistant::fake(['Riverside School has 3 upcoming bookings.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Show me bookings for Riverside School')
        ->call('send')
        ->assertSee('Show me bookings for Riverside School')
        ->assertDontSee('Riverside School has 3 upcoming bookings.')
        ->assertSet('pendingPrompt', 'Show me bookings for Riverside School');
});

test('a user message renders with no leading blank space before its text', function () {
    DataAssistant::fake();

    $html = Livewire::test(AskAssistant::class)
        ->set('prompt', 'Show me bookings for Riverside School')
        ->call('send')
        ->html();

    // Blade's own structural HTML comment markers around @if/@endif are
    // invisible to the browser and don't affect layout — strip them so this
    // only fails on whitespace that would actually render. whitespace-pre-line
    // on the user bubble renders any stray blank line or leading space
    // between the bubble's opening tag and its text as a visible gap above
    // the message — so the text must sit directly against the tag.
    $html = preg_replace('/<!--\[if (?:BLOCK|ENDBLOCK)]><!\[endif]-->/', '', $html);

    expect($html)->toContain('>Show me bookings for Riverside School</div>');
});

test('markdown links in the agent response render as clickable links', function () {
    DataAssistant::fake(['- [Jane Doe](https://example.com/candidates/1) is Live.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->assertSeeHtml('<a href="https://example.com/candidates/1">Jane Doe</a>');
});

test('user messages are never rendered as markdown', function () {
    DataAssistant::fake(['Sure.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'What about [not a link](javascript:alert(1))?')
        ->call('send')
        ->call('respond')
        ->assertDontSeeHtml('<a href="javascript:alert(1)">not a link</a>');
});

test('sending a blank prompt does nothing', function () {
    DataAssistant::fake();

    Livewire::test(AskAssistant::class)
        ->set('prompt', '   ')
        ->call('send')
        ->call('respond')
        ->assertSet('messages', []);

    DataAssistant::assertNeverPrompted();
});

test('respond does nothing if there is no pending prompt to answer', function () {
    DataAssistant::fake();

    Livewire::test(AskAssistant::class)
        ->call('respond')
        ->assertSet('messages', []);

    DataAssistant::assertNeverPrompted();
});

test('clearing the chat empties the message list', function () {
    DataAssistant::fake(['An answer.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'A question')
        ->call('send')
        ->call('respond')
        ->call('clearChat')
        ->assertSet('messages', []);
});

test('a "show me more" prompt appears when a result says more results match', function () {
    DataAssistant::fake(['- A\n- B\n\n_Showing 50 of 128 — 78 more match. Ask to see the next 50 to continue._']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->assertSet('moreResultsAvailable', true)
        ->assertSee('Show me more');
});

test('the "show me more" prompt does not appear when nothing more matches', function () {
    DataAssistant::fake(['- A\n- B']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->assertSet('moreResultsAvailable', false)
        ->assertDontSee('Show me more');
});

test('clicking "show me more" continues the same conversation the agent already has context for', function () {
    DataAssistant::fake([
        '- A\n\n_Showing 50 of 128 — 78 more match. Ask to see the next 50 to continue._',
        '- B\n\n_Showing 50 of 128 — 28 more match. Ask to see the next 50 to continue._',
    ]);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->call('showMore')
        ->call('respond')
        ->assertSet('moreResultsAvailable', true);

    DataAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Show me more'));
});

test('clicking "show me more" stops offering more once nothing is left', function () {
    DataAssistant::fake([
        '- A\n\n_Showing 50 of 60 — 10 more match. Ask to see the next 50 to continue._',
        '- B',
    ]);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->call('showMore')
        ->call('respond')
        ->assertSet('moreResultsAvailable', false)
        ->assertDontSeeHtml('$wire.showMore()');
});

test('clearing the chat resets the "show me more" state', function () {
    DataAssistant::fake(['- A\n\n_Showing 50 of 128 — 78 more match. Ask to see the next 50 to continue._']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond')
        ->call('clearChat')
        ->assertSet('moreResultsAvailable', false)
        ->assertSet('conversationId', null);
});

test('the conversation is continued across messages, not restarted each time', function () {
    DataAssistant::fake(['First reply.', 'Second reply.']);

    $component = Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond');

    $conversationId = $component->get('conversationId');

    expect($conversationId)->not->toBeNull();

    $component
        ->set('prompt', 'And which of those are in Manchester?')
        ->call('send')
        ->call('respond')
        ->assertSet('conversationId', $conversationId);
});

test('a fresh conversation is started after clearing the chat', function () {
    DataAssistant::fake(['First reply.', 'Second reply.']);

    $component = Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond');

    $firstConversationId = $component->get('conversationId');

    $component
        ->call('clearChat')
        ->set('prompt', 'A brand new question')
        ->call('send')
        ->call('respond');

    expect($component->get('conversationId'))
        ->not->toBeNull()
        ->not->toBe($firstConversationId);
});
