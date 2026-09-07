<?php

use App\Ai\Agents\DataAssistant;

test('the instructions teach recruitment desk/book/portfolio jargon instead of taking it literally', function () {
    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('"desk", "book", or "portfolio"')
        ->toContain('never interpret it literally')
        ->toContain('run_sql_query grouping by')
        ->toContain('never by declining the question');
});

test('the instructions allow drafting emails but never sending them', function () {
    $instructions = (string) (new DataAssistant)->instructions();

    expect($instructions)
        ->toContain('You can also draft emails when asked')
        ->toContain('"Subject:" and "Body:" headings')
        ->toContain('never invent a detail you don\'t have')
        ->toContain('You do not have anyone\'s email address and you cannot send anything')
        ->toContain('never say or imply that an email has actually been sent');
});
