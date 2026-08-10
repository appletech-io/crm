<?php

use App\Http\Controllers\EmailImageController;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('a validly signed url for a file under the email-attachments directory redirects to a freshly generated temporary url', function () {
    config(['filesystems.default' => 's3']);

    $path = EmailImageController::DIRECTORY.'/abc123.png';

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturnSelf();
    Storage::shouldReceive('exists')
        ->once()
        ->with($path)
        ->andReturnTrue();
    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->with($path, Mockery::type(CarbonInterface::class))
        ->andReturn('https://example-bucket.s3.amazonaws.com/signed-url');

    $url = URL::signedRoute('email-images.show', ['path' => $path]);

    $this->get($url)->assertRedirect('https://example-bucket.s3.amazonaws.com/signed-url');
});

test('a missing file returns a 404, with no authentication required to reach that point', function () {
    Storage::fake('local');

    $url = URL::signedRoute('email-images.show', ['path' => EmailImageController::DIRECTORY.'/missing.png']);

    expect(auth()->check())->toBeFalse();

    $this->get($url)->assertNotFound();
});

test('an unsigned or tampered url is rejected', function () {
    $this->get('/email-images/'.EmailImageController::DIRECTORY.'/abc123.png')->assertForbidden();
});

test('a validly signed url is still rejected if the path falls outside the email-attachments directory', function () {
    $url = URL::signedRoute('email-images.show', ['path' => 'candidates/1/safeguarding-training.pdf']);

    $this->get($url)->assertNotFound();
});
