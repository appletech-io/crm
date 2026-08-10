<?php

use App\Http\Controllers\EmailImageController;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

test('a file under the ad-hoc directory older than the retention window is deleted', function () {
    $path = EmailImageController::ADHOC_DIRECTORY.'/old.png';
    Storage::disk('local')->put($path, 'bytes');
    touch(Storage::disk('local')->path($path), now()->subHours(25)->getTimestamp());

    $this->artisan('email:prune-adhoc-attachments')->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
});

test('a recently uploaded file under the ad-hoc directory is left alone', function () {
    $path = EmailImageController::ADHOC_DIRECTORY.'/recent.png';
    Storage::disk('local')->put($path, 'bytes');

    $this->artisan('email:prune-adhoc-attachments')->assertSuccessful();

    Storage::disk('local')->assertExists($path);
});

test('a saved templates permanent image is never touched, however old it is', function () {
    $path = EmailImageController::DIRECTORY.'/template-logo.png';
    Storage::disk('local')->put($path, 'bytes');
    touch(Storage::disk('local')->path($path), now()->subDays(30)->getTimestamp());

    $this->artisan('email:prune-adhoc-attachments')->assertSuccessful();

    Storage::disk('local')->assertExists($path);
});
