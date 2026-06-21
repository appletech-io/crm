<?php

use App\Models\EducationClient;

test('it can create an education client', function () {
    $client = EducationClient::factory()->create([
        'name' => 'John Doe',
        'subject' => 'Mathematics',
        'grade_level' => 'Secondary',
    ]);

    expect($client->name)->toBe('John Doe')
        ->and($client->subject)->toBe('Mathematics')
        ->and($client->grade_level)->toBe('Secondary');
});

test('it has soft deletes', function () {
    $client = EducationClient::factory()->create();

    $client->delete();

    expect($client->fresh()->deleted_at)->not->toBeNull();
});
