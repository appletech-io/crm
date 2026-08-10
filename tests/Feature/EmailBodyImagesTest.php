<?php

use App\Http\Controllers\EmailImageController;
use App\Services\Mail\EmailBodyImages;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

test('an img tag pointing at a signed email-images url is rewritten to a cid reference and returned as an inline attachment', function () {
    $path = EmailImageController::DIRECTORY.'/abc123.png';
    Storage::disk('local')->put($path, 'the actual image bytes');
    $url = URL::signedRoute('email-images.show', ['path' => $path]);

    $result = EmailBodyImages::embedInline("<p>Hi</p><img src=\"{$url}\" alt=\"\"><p>Bye</p>");

    expect($result['attachments'])->toHaveCount(1);

    $attachment = $result['attachments'][0];
    expect($attachment['name'])->toBe('abc123.png')
        ->and($attachment['content'])->toBe('the actual image bytes')
        ->and($attachment['inline'])->toBeTrue()
        ->and($attachment['contentId'])->not->toBeEmpty();

    expect($result['body'])
        ->toContain("cid:{$attachment['contentId']}")
        ->not->toContain($url);
});

test('multiple images in the same body each get their own content id', function () {
    $pathOne = EmailImageController::DIRECTORY.'/one.png';
    $pathTwo = EmailImageController::ADHOC_DIRECTORY.'/two.png';
    Storage::disk('local')->put($pathOne, 'bytes-one');
    Storage::disk('local')->put($pathTwo, 'bytes-two');

    $body = '<img src="'.URL::signedRoute('email-images.show', ['path' => $pathOne]).'">'
        .'<img src="'.URL::signedRoute('email-images.show', ['path' => $pathTwo]).'">';

    $result = EmailBodyImages::embedInline($body);

    expect($result['attachments'])->toHaveCount(2);
    expect($result['attachments'][0]['contentId'])->not->toBe($result['attachments'][1]['contentId']);
});

test('a body with no images is returned unchanged with no attachments', function () {
    $result = EmailBodyImages::embedInline('<p>Just some text, no images here.</p>');

    expect($result['body'])->toBe('<p>Just some text, no images here.</p>')
        ->and($result['attachments'])->toBe([]);
});

test('an img tag whose file no longer exists on disk is left untouched rather than producing a broken cid', function () {
    $path = EmailImageController::DIRECTORY.'/never-uploaded.png';
    $url = URL::signedRoute('email-images.show', ['path' => $path]);

    $result = EmailBodyImages::embedInline("<img src=\"{$url}\">");

    expect($result['attachments'])->toBe([])
        ->and($result['body'])->toContain($url);
});
