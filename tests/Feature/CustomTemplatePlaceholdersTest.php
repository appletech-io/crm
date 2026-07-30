<?php

use App\Enums\EmailTemplateAudience;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EducationCandidate;
use App\Services\Mail\CustomTemplatePlaceholders;

test('definitions for Both never includes client-only placeholders', function () {
    expect(CustomTemplatePlaceholders::definitions(EmailTemplateAudience::Both))
        ->not->toHaveKey('client_name')
        ->not->toHaveKey('client_address');
});

test('definitions for Candidate matches the shared set', function () {
    expect(CustomTemplatePlaceholders::definitions(EmailTemplateAudience::Candidate))
        ->toBe(CustomTemplatePlaceholders::definitions(EmailTemplateAudience::Both));
});

test('definitions for Client includes the client-only placeholders', function () {
    expect(CustomTemplatePlaceholders::definitions(EmailTemplateAudience::Client))
        ->toHaveKey('client_name')
        ->toHaveKey('client_address')
        ->toHaveKey('client_city')
        ->toHaveKey('client_postcode');
});

test('resolve builds candidate values from the candidate directly', function () {
    $candidate = EducationCandidate::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $values = CustomTemplatePlaceholders::resolve($candidate);

    expect($values['recipient_first_name'])->toBe('Jane');
    expect($values['recipient_last_name'])->toBe('Doe');
    expect($values['recipient_name'])->toBe('Jane Doe');
    expect($values['recipient_email'])->toBe('jane@example.com');
    expect($values)->not->toHaveKey('client_name');
});

test('resolve builds client values from the passed contact, not the candidate fields', function () {
    $client = Client::factory()->create(['name' => 'Acme Ltd']);
    $contact = ClientContact::factory()->create([
        'client_id' => $client->id,
        'first_name' => 'Sam',
        'last_name' => 'Smith',
        'email' => 'sam@acme.test',
    ]);

    $values = CustomTemplatePlaceholders::resolve($client, $contact);

    expect($values['recipient_first_name'])->toBe('Sam');
    expect($values['recipient_last_name'])->toBe('Smith');
    expect($values['recipient_name'])->toBe('Sam Smith');
    expect($values['recipient_email'])->toBe('sam@acme.test');
    expect($values['client_name'])->toBe('Acme Ltd');
});

test('resolve for a client with no contact returns blank recipient fields', function () {
    $client = Client::factory()->create(['name' => 'Acme Ltd']);

    $values = CustomTemplatePlaceholders::resolve($client);

    expect($values['recipient_email'])->toBe('');
    expect($values['recipient_name'])->toBe('');
    expect($values['client_name'])->toBe('Acme Ltd');
});
