<?php

use App\Ai\Agents\DataAssistant;
use App\Ai\Tools\DraftBookingLink;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the instructions teach recruitment desk/book/portfolio jargon instead of taking it literally', function () {
    $this->actingAs(User::factory()->create());

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('"desk", "book", or "portfolio"')
        ->toContain('never interpret it literally')
        ->toContain('run_sql_query grouping by')
        ->toContain('never by declining the question');
});

test('the instructions allow drafting emails but never sending them', function () {
    $this->actingAs(User::factory()->create());

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('You can also draft emails when asked')
        ->toContain('"Subject:" and "Body:" headings')
        ->toContain('never invent a detail you don\'t have')
        ->toContain('You do not have anyone\'s email address and you cannot send anything')
        ->toContain('never say or imply that an email has actually been sent');
});

test('the instructions name the current user so "I"/"me"/"my" resolves to them', function () {
    $user = User::factory()->create(['name' => 'Jordan Blake']);
    $this->actingAs($user);

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('You are talking to Jordan Blake')
        ->toContain('that means Jordan Blake specifically')
        ->toContain('Non-admins automatically only ever see their own bookings/clients/vacancies');
});

test('for an admin, the instructions require an explicit consultant_name filter for "I"/"me"/"my" questions', function () {
    $user = User::factory()->create(['name' => 'Morgan Reed']);
    $user->assignRole('admin');
    $this->actingAs($user);

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('You are talking to Morgan Reed, an admin')
        ->toContain('consultant_name="Morgan Reed"')
        ->toContain("WHERE consultant_name = 'Morgan Reed'");
});

test('the instructions say every search_bookings result within a from/to window belongs there, regardless of its own displayed dates', function () {
    $this->actingAs(User::factory()->create());

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('every item it returns belongs there, no matter how far its own displayed start')
        ->toContain('never silently drop some based on their displayed dates');
});

test('the instructions say never to guess a reason for a blank field', function () {
    $this->actingAs(User::factory()->create());

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('never speculate that it means data is "missing" or broken')
        ->toContain('just report the figure and say the field wasn\'t set, rather than guessing a cause');
});

test('the instructions say draft_booking_link never actually creates a booking', function () {
    $this->actingAs(User::factory()->create());

    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('it never actually creates a booking')
        ->toContain('never say or imply a booking was actually made');
});

test('draft_booking_link is registered as one of the agent\'s tools', function () {
    $this->actingAs(User::factory()->create());

    $tools = collect((new DataAssistant)->tools());

    expect($tools->contains(fn ($tool): bool => $tool instanceof DraftBookingLink))->toBeTrue();
});
