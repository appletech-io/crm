<?php

use App\Enums\ActivityType;
use App\Models\CandidateActivity;
use App\Models\ClientActivity;
use App\Models\EducationCandidate;
use App\Models\VacancyActivity;

/**
 * The `note` column is a VARCHAR(255) on every activity table while `body`
 * is TEXT, so an over-long summary has to be relocated rather than written
 * — MySQL in strict mode raises a 1406 and loses the whole activity.
 */
dataset('activityModels', [
    'candidate' => [CandidateActivity::class],
    'client' => [ClientActivity::class],
    'vacancy' => [VacancyActivity::class],
]);

function logActivityWithNote(string $model, string $note, ?string $body = null)
{
    return $model::create([
        'model_type' => EducationCandidate::class,
        'model_id' => 1,
        'type' => ActivityType::Note->value,
        'note' => $note,
        'body' => $body,
    ]);
}

test('a note within the column width is stored untouched', function (string $model) {
    $activity = logActivityWithNote($model, 'Called the client about next week');

    expect($activity->fresh())
        ->note->toBe('Called the client about next week')
        ->body->toBeNull();
})->with('activityModels');

test('a note longer than the column width is trimmed, and kept in full in the body', function (string $model) {
    $longNote = str_repeat('a', 400);

    $activity = logActivityWithNote($model, $longNote)->fresh();

    expect(mb_strlen($activity->note))->toBeLessThanOrEqual($model::NOTE_MAX_LENGTH)
        ->and($activity->note)->toEndWith('...')
        ->and($activity->body)->toBe($longNote);
})->with('activityModels');

test('relocating a long note keeps the detail that was already in the body', function (string $model) {
    $longNote = str_repeat('b', 300);

    $activity = logActivityWithNote($model, $longNote, 'Spoke to Jane on reception.')->fresh();

    expect($activity->body)->toBe($longNote.PHP_EOL.PHP_EOL.'Spoke to Jane on reception.');
})->with('activityModels');

test('a note exactly at the column width is not relocated', function (string $model) {
    $note = str_repeat('c', $model::NOTE_MAX_LENGTH);

    $activity = logActivityWithNote($model, $note)->fresh();

    expect($activity->note)->toBe($note)
        ->and($activity->body)->toBeNull();
})->with('activityModels');

test('a long note is still trimmed when the activity is updated, not just created', function (string $model) {
    $activity = logActivityWithNote($model, 'Short to begin with');

    $activity->update(['note' => str_repeat('d', 500)]);

    expect(mb_strlen($activity->fresh()->note))->toBeLessThanOrEqual($model::NOTE_MAX_LENGTH);
})->with('activityModels');

test('multibyte characters are counted as characters, not bytes', function (string $model) {
    // 260 multibyte chars: under the limit by bytes would be wrong, the
    // column counts characters.
    $activity = logActivityWithNote($model, str_repeat('é', 260))->fresh();

    expect(mb_strlen($activity->note))->toBeLessThanOrEqual($model::NOTE_MAX_LENGTH);
})->with('activityModels');
