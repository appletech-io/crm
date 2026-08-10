<?php

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Http\Controllers\EmailImageController;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company = Company::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

function makeFakeTemporaryUpload(string $filename, string $contents = 'fake-image-bytes'): TemporaryUploadedFile
{
    FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($filename, false), $contents);

    return TemporaryUploadedFile::createFromLivewire($filename);
}

test('a pasted image is written directly to the email-attachments directory and the temp copy is cleaned up', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $file = makeFakeTemporaryUpload('screenshot.png', 'the actual image bytes');
    $tempPath = $file->path();

    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    $path = $richEditor->saveUploadedFileAttachment($file);

    expect($path)->toStartWith(EmailImageController::DIRECTORY.'/');
    expect(Storage::disk('local')->exists($path))->toBeTrue();
    expect(Storage::disk('local')->get($path))->toBe('the actual image bytes');
    expect(FileUploadConfiguration::storage()->exists($tempPath))->toBeFalse();
});

/**
 * Regression test: the original bug used $file->store(), which for a
 * temporary-upload disk matching the target disk moves the file via
 * Storage::move() — a server-side S3 CopyObject requiring s3:GetObjectAcl,
 * a permission this app's IAM user doesn't have. Since the disk is
 * configured with 'throw' => false, that failure was silently swallowed:
 * store() still returned a path as if it had succeeded, but the file was
 * never actually copied out of livewire-tmp/. This asserts the fix (a
 * plain content write) is what's actually used, not move/copy/store.
 */
test('saving an attachment writes the file content directly rather than moving or copying it', function () {
    config(['filesystems.default' => 's3']);

    $file = makeFakeTemporaryUpload('screenshot.png');

    Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
    Storage::shouldReceive('writeStream')->once()->withArgs(fn (string $path): bool => str_starts_with($path, EmailImageController::DIRECTORY.'/'))->andReturnTrue();
    Storage::shouldReceive('move')->never();
    Storage::shouldReceive('copy')->never();

    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    $richEditor->saveUploadedFileAttachment($file);
});

test('the body field points a pasted image at a signed email-images url, not a raw storage url', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $path = EmailImageController::DIRECTORY.'/abc123.png';
    Storage::disk('local')->put($path, 'fake-image-bytes');

    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    expect($richEditor->getFileAttachmentUrl($path))
        ->toBe(URL::signedRoute('email-images.show', ['path' => $path]));
});

test('a file that does not actually exist on disk resolves to no url at all, rather than a broken link', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    expect($richEditor->getFileAttachmentUrl(EmailImageController::DIRECTORY.'/never-uploaded.png'))->toBeNull();
});
