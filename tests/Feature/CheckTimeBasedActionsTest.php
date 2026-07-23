<?php

use App\Models\Action;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'created_at', 'operator' => 'days_since_at_least', 'value' => '30'],
        ],
        'todo_name' => 'Client has been dormant for a month',
    ]);
});

test('creates a todo for a client whose time-based condition has now elapsed', function () {
    // Create with a recent timestamp so the save-triggered observer does not
    // already satisfy the condition, then backdate quietly (no observer
    // re-fire) to simulate time having passed since — isolating this test to
    // the scheduled sweep, not the observer.
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
    ]);
    $client->timestamps = false;
    $client->created_at = now()->subDays(40);
    $client->saveQuietly();

    expect(TodoItem::count())->toBe(0);

    $this->artisan('actions:check-time-based')->assertSuccessful();

    expect(TodoItem::where('model_type', Client::class)->where('model_id', $client->id)->exists())->toBeTrue();
});

test('does not create a todo when the time-based condition has not yet elapsed', function () {
    Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'created_at' => now()->subDays(5),
    ]);

    $this->artisan('actions:check-time-based')->assertSuccessful();

    expect(TodoItem::count())->toBe(0);
});

test('skips clients with no consultant', function () {
    Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'created_at' => now()->subDays(40),
    ]);

    $this->artisan('actions:check-time-based')->assertSuccessful();

    expect(TodoItem::count())->toBe(0);
});

test('a non-time-based action is not swept twice by the scheduled command', function () {
    Action::query()->delete();

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'name', 'operator' => 'filled'],
        ],
    ]);

    // The save-triggered observer already fires this action on create, since
    // its condition has nothing to do with elapsed time.
    Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'name' => 'Acme School',
    ]);

    expect(TodoItem::count())->toBe(1);

    // The scheduled sweep only targets model types with a days_since_at_least
    // condition, so it shouldn't re-check this action or duplicate the todo.
    $this->artisan('actions:check-time-based')->assertSuccessful();

    expect(TodoItem::count())->toBe(1);
});

test('the command is registered on the daily schedule', function () {
    $events = app(Schedule::class)->events();

    $matching = collect($events)->first(fn ($event) => str_contains($event->command, 'actions:check-time-based'));

    expect($matching)->not->toBeNull();
    expect($matching->expression)->toBe('0 0 * * *');
});
