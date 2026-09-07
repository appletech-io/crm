<?php

use App\Ai\Agents\DataAssistant;
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
