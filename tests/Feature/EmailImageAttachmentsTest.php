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

test('a pasted image is stored under the email-attachments directory on the default disk', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $filename = 'screenshot.png';
    FileUploadConfiguration::storage()->put(
        FileUploadConfiguration::path($filename, false),
        'fake-image-bytes',
    );

    $file = TemporaryUploadedFile::createFromLivewire($filename);

    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    $path = $richEditor->saveUploadedFileAttachment($file);

    expect($path)->toStartWith(EmailImageController::DIRECTORY.'/');
    expect(Storage::disk('local')->exists($path))->toBeTrue();
});

test('the body field points a pasted image at a signed email-images url, not a raw storage url', function () {
    $richEditor = Livewire::test(CreateEmailTemplate::class)
        ->instance()
        ->form
        ->getComponent('body');

    $path = EmailImageController::DIRECTORY.'/abc123.png';

    expect($richEditor->getFileAttachmentUrl($path))
        ->toBe(URL::signedRoute('email-images.show', ['path' => $path]));
});
