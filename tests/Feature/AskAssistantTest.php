<?php

use App\Ai\Agents\DataAssistant;
use App\Livewire\AskAssistant;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
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

test('the page renders with the suggested prompts visible', function () {
    Livewire::test(AskAssistant::class)
        ->assertSuccessful()
        ->assertSee('Which candidates are Live?');
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
        ->assertSee('DBS expiring soon?');
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
        ->assertSet('messages.1.role', 'assistant')
        ->assertSet('messages.1.content', 'Riverside School has 3 upcoming bookings.');

    DataAssistant::assertPrompted('Show me bookings for Riverside School');
});

test('markdown links in the agent response render as clickable links', function () {
    DataAssistant::fake(['- [Jane Doe](https://example.com/candidates/1) is Live.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->assertSeeHtml('<a href="https://example.com/candidates/1">Jane Doe</a>');
});

test('user messages are never rendered as markdown', function () {
    DataAssistant::fake(['Sure.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'What about [not a link](javascript:alert(1))?')
        ->call('send')
        ->assertDontSeeHtml('<a href="javascript:alert(1)">not a link</a>');
});

test('sending a blank prompt does nothing', function () {
    DataAssistant::fake();

    Livewire::test(AskAssistant::class)
        ->set('prompt', '   ')
        ->call('send')
        ->assertSet('messages', []);

    DataAssistant::assertNeverPrompted();
});

test('clearing the chat empties the message list', function () {
    DataAssistant::fake(['An answer.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'A question')
        ->call('send')
        ->call('clearChat')
        ->assertSet('messages', []);
});
