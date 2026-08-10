<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Support\EmailAttachmentUpload;
use App\Http\Controllers\EmailImageController;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

/**
 * The saveUploadedFileUsing closure only ever actually runs via
 * BaseFileUpload::saveUploadedFiles() during real form dehydration — there's
 * no public method that invokes it directly (unlike RichEditor's
 * saveUploadedFileAttachment()) — so these lower-level regression tests
 * pull the closure out via reflection to call it in isolation.
 */
function callAttachmentSaveClosure(TemporaryUploadedFile $file): ?string
{
    $fileUpload = EmailAttachmentUpload::configure(FileUpload::make('test'));

    $property = new ReflectionProperty($fileUpload, 'saveUploadedFileUsing');
    $property->setAccessible(true);
    $callback = $property->getValue($fileUpload);

    return $fileUpload->evaluate($callback, ['file' => $file]);
}

function makeFakeTemporaryAttachment(string $filename, string $contents = 'fake-attachment-bytes'): TemporaryUploadedFile
{
    $path = FileUploadConfiguration::path($filename, false);
    FileUploadConfiguration::storage()->put($path, $contents);
    // getClientOriginalName() reads this companion metadata file rather
    // than the temp path itself — real (browser) uploads always write one.
    FileUploadConfiguration::storage()->put("{$path}.json", json_encode(['name' => $filename]));

    return TemporaryUploadedFile::createFromLivewire($filename);
}

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('an attached file is stored under the ad-hoc directory with its original filename preserved, and passed through to the job', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'email' => 'jane@example.com']);
    $file = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->callTableAction('sendEmail', $candidate, data: [
            'mode' => 'adhoc',
            'adhoc_subject' => 'Quick note',
            'adhoc_body' => 'Just checking in.',
            'adhoc_attachments' => [$file],
        ])
        ->assertHasNoTableActionErrors();

    Bus::assertDispatched(SendCustomTemplateEmail::class, function (SendCustomTemplateEmail $job): bool {
        expect($job->adHocAttachments)->toHaveCount(1);

        $path = $job->adHocAttachments[0];

        expect($path)->toStartWith(EmailImageController::ADHOC_DIRECTORY.'/')
            ->and($path)->toEndWith('/invoice.pdf')
            ->and(Storage::disk('local')->exists($path))->toBeTrue();

        return true;
    });
});

test('an attached file is written directly to the ad-hoc directory with its original filename, and the temp copy is cleaned up', function () {
    $file = makeFakeTemporaryAttachment('invoice.pdf', 'the actual pdf bytes');
    $tempPath = $file->path();

    $path = callAttachmentSaveClosure($file);

    expect($path)->toStartWith(EmailImageController::ADHOC_DIRECTORY.'/')
        ->and($path)->toEndWith('/invoice.pdf')
        ->and(Storage::disk('local')->get($path))->toBe('the actual pdf bytes')
        ->and(FileUploadConfiguration::storage()->exists($tempPath))->toBeFalse();
});

/**
 * Same regression this app already guards against for pasted RichEditor
 * images (see EmailImageAttachmentsTest): $file->store() would move/copy
 * across the same S3 disk and silently fail on the missing
 * s3:GetObjectAcl permission.
 */
test('saving an attachment writes the file content directly rather than moving or copying it', function () {
    config(['filesystems.default' => 's3']);

    $file = makeFakeTemporaryAttachment('invoice.pdf');

    Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
    Storage::shouldReceive('writeStream')->once()->withArgs(fn (string $path): bool => str_starts_with($path, EmailImageController::ADHOC_DIRECTORY.'/'))->andReturnTrue();
    Storage::shouldReceive('move')->never();
    Storage::shouldReceive('copy')->never();

    callAttachmentSaveClosure($file);
});
