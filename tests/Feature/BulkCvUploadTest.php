<?php

use App\Ai\Agents\CvParser;
use App\Enums\DocumentType;
use App\Filament\Resources\EducationCandidates\Pages\BulkUploadCvs;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Jobs\ProcessBulkCvUpload;
use App\Jobs\SendApplicationEmail;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use App\Services\Ai\CvParserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->status = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('the bulk upload page renders with a submit button wired to the upload action', function () {
    $html = Livewire::test(BulkUploadCvs::class)
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('wire:click="processCvUploads"');

    $skillsPos = strpos($html, 'No skills selected yet.');
    $buttonPos = strpos($html, 'Upload &amp; Process');

    expect($skillsPos)->not->toBeFalse()
        ->and($buttonPos)->not->toBeFalse()
        ->and($buttonPos)->toBeGreaterThan($skillsPos);
});

test('the candidate list has a link to the bulk upload page', function () {
    Livewire::test(ListEducationCandidates::class)
        ->assertActionExists('bulkUploadCvs');
});

test('submitting the form dispatches a job per uploaded CV and resets the form', function () {
    Queue::fake();

    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $files = [
        UploadedFile::fake()->create('candidate-one.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('candidate-two.pdf', 100, 'application/pdf'),
    ];

    Livewire::test(BulkUploadCvs::class)
        ->fillForm([
            'cvs' => $files,
            'candidate_status_id' => $this->status->id,
            'send_application_email' => false,
        ])
        ->set('skillIds', [$skill->id])
        ->call('processCvUploads')
        ->assertHasNoFormErrors()
        ->assertFormSet(['cvs' => []])
        ->assertSet('skillIds', []);

    Queue::assertPushed(ProcessBulkCvUpload::class, 2);

    Queue::assertPushed(ProcessBulkCvUpload::class, function (ProcessBulkCvUpload $job) use ($skill) {
        return $job->companyId === $this->user->company_id
            && $job->industrySlug === 'education'
            && $job->candidateStatusId === $this->status->id
            && $job->skillIds === [$skill->id]
            && $job->sendApplicationEmail === false;
    });
});

test('the job creates a candidate from the parsed CV and assigns status, skills, and the CV document', function () {
    Queue::fake([SendApplicationEmail::class]);

    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $path = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    (new ProcessBulkCvUpload(
        filePath: $path,
        companyId: $this->user->company_id,
        industrySlug: 'education',
        candidateStatusId: $this->status->id,
        skillIds: [$skill->id],
        sendApplicationEmail: true,
    ))->handle(app(CvParserService::class));

    $candidate = EducationCandidate::first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->first_name)->toBe('Jane')
        ->and($candidate->last_name)->toBe('Doe')
        ->and($candidate->email)->toBe('jane@example.com')
        ->and($candidate->statuses()->where('candidate_status_id', $this->status->id)->exists())->toBeTrue()
        ->and($candidate->skills()->where('candidate_skills.id', $skill->id)->exists())->toBeTrue()
        ->and($candidate->documents()->where('document_type', DocumentType::Cv)->exists())->toBeTrue();

    Queue::assertPushed(SendApplicationEmail::class);
});

test('the job populates as much candidate detail as the CV provides and creates employment histories', function () {
    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'middleName' => 'Marie',
        'lastName' => 'Doe',
        'email' => 'jane@example.com',
        'dateOfBirth' => '1990-05-15',
        'address' => '10 Downing Street',
        'city' => 'London',
        'county' => 'Greater London',
        'country' => 'United Kingdom',
        'postcode' => 'SW1A 2AA',
        'phone' => '02079460000',
        'mobile' => '07700900000',
        'gender' => 'Female',
        'nationality' => 'British',
        'educationAndQualification' => 'BA Education',
        'summary' => 'Experienced primary school teacher.',
        'skills' => 'Classroom management, Phonics',
        'employmentHistory' => [
            [
                'companyName' => 'Oakwood Primary',
                'jobTitle' => 'Teacher',
                'workedFrom' => '2020-09-01',
                'workedTo' => null,
            ],
            [
                'companyName' => 'Elm Secondary',
                'jobTitle' => 'Teaching Assistant',
                'workedFrom' => '2018-01-01',
                'workedTo' => '2020-08-31',
            ],
            [
                'companyName' => null,
                'jobTitle' => null,
                'workedFrom' => null,
                'workedTo' => null,
            ],
        ],
    ]);

    $path = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    (new ProcessBulkCvUpload(
        filePath: $path,
        companyId: $this->user->company_id,
        industrySlug: 'education',
        candidateStatusId: $this->status->id,
        skillIds: [],
        sendApplicationEmail: false,
    ))->handle(app(CvParserService::class));

    $candidate = EducationCandidate::first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->middle_name)->toBe('Marie')
        ->and($candidate->date_of_birth->toDateString())->toBe('1990-05-15')
        ->and($candidate->address)->toBe('10 Downing Street')
        ->and($candidate->city)->toBe('London')
        ->and($candidate->county)->toBe('Greater London')
        ->and($candidate->country)->toBe('United Kingdom')
        ->and($candidate->postcode)->toBe('SW1A 2AA')
        ->and($candidate->phone)->toBe('02079460000')
        ->and($candidate->mobile)->toBe('07700900000')
        ->and($candidate->gender)->toBe('Female')
        ->and($candidate->nationality)->toBe('British')
        ->and($candidate->education_and_qualification)->toBe('BA Education')
        ->and($candidate->notes)->toContain('Experienced primary school teacher.')
        ->and($candidate->notes)->toContain('Classroom management, Phonics');

    expect($candidate->employmentHistories()->count())->toBe(2);

    expect($candidate->employmentHistories()->where('company_name', 'Oakwood Primary')->first())
        ->job_title->toBe('Teacher');

    expect($candidate->employmentHistories()->where('company_name', 'Elm Secondary')->first())
        ->job_title->toBe('Teaching Assistant');
});

test('the job skips creating a candidate when one already exists for that email in the company', function () {
    Queue::fake([SendApplicationEmail::class]);

    $existing = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $path = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    (new ProcessBulkCvUpload(
        filePath: $path,
        companyId: $this->user->company_id,
        industrySlug: 'education',
        candidateStatusId: $this->status->id,
        skillIds: [],
        sendApplicationEmail: true,
    ))->handle(app(CvParserService::class));

    expect(EducationCandidate::where('email', 'jane@example.com')->count())->toBe(1)
        ->and(EducationCandidate::first()->id)->toBe($existing->id);

    Queue::assertNotPushed(SendApplicationEmail::class);
    Storage::disk('local')->assertMissing($path);
});

test('the job gracefully skips instead of crashing when a duplicate is created between the pre-check and the insert', function () {
    Queue::fake([SendApplicationEmail::class]);

    $existing = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'jane@example.com',
    ]);

    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $path = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    // Simulates the race: the pre-check reports "no existing candidate" (as it would if
    // another job in the same batch hadn't committed yet), but a real duplicate already
    // exists in the database, so the insert itself must fail and be handled gracefully.
    $job = new class(filePath: $path, companyId: $this->user->company_id, industrySlug: 'education', candidateStatusId: $this->status->id, skillIds: [], sendApplicationEmail: true) extends ProcessBulkCvUpload
    {
        protected function candidateExistsForEmail(string $modelClass, string $email): bool
        {
            return false;
        }
    };

    $job->handle(app(CvParserService::class));

    expect(EducationCandidate::where('email', 'jane@example.com')->count())->toBe(1)
        ->and(EducationCandidate::first()->id)->toBe($existing->id);

    Queue::assertNotPushed(SendApplicationEmail::class);
    Storage::disk('local')->assertMissing($path);
});

test('the job does not send an application email when disabled', function () {
    Queue::fake([SendApplicationEmail::class]);

    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $path = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    (new ProcessBulkCvUpload(
        filePath: $path,
        companyId: $this->user->company_id,
        industrySlug: 'education',
        candidateStatusId: $this->status->id,
        skillIds: [],
        sendApplicationEmail: false,
    ))->handle(app(CvParserService::class));

    Queue::assertNotPushed(SendApplicationEmail::class);
});

test('the job falls back to the filename and leaves email null when the CV has no parseable name or email', function () {
    CvParser::fake(fn () => []);

    $path = UploadedFile::fake()->create('unparseable-cv.pdf', 100, 'application/pdf')->store('bulk-cv-uploads', 'local');

    (new ProcessBulkCvUpload(
        filePath: $path,
        companyId: $this->user->company_id,
        industrySlug: 'education',
        candidateStatusId: $this->status->id,
        skillIds: [],
        sendApplicationEmail: false,
    ))->handle(app(CvParserService::class));

    $candidate = EducationCandidate::first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->email)->toBeNull()
        ->and($candidate->first_name)->toBe(pathinfo($path, PATHINFO_FILENAME));
});
