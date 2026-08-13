<?php

use App\Ai\Agents\CvParser;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\FormattedCv;
use App\Models\Industry;
use App\Services\Candidates\FormattedCvGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::factory()->create(['name' => 'Applebough']);
    Industry::factory()->create(['slug' => 'education']);

    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    Storage::disk('local')->put('test-cvs/jane-doe-cv.pdf', 'fake pdf contents');

    $this->cvDocument = $this->candidate->documents()->create([
        'document_type' => DocumentType::Cv,
        'path' => 'test-cvs/jane-doe-cv.pdf',
    ]);
});

test('generation preserves the full CV content faithfully, excluding contact details', function () {
    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane.doe@example.com',
        'phone' => '01234 567890',
        'mobile' => '07700 900000',
        'address' => '123 Fake Street',
        'bodyHtml' => '<h2>Profile</h2><p>An experienced teacher with a decade of classroom experience.</p>'
            .'<h2>Employment History</h2><h3>Year 3 Teacher, Ashlawn School (2018–2023)</h3>'
            .'<ul><li>Planned and delivered the KS2 curriculum</li><li>Led the school\'s phonics programme</li></ul>'
            .'<h2>Education</h2><p>BA Hons Education, University of Warwick</p>'
            .'<h2>Skills</h2><p>Classroom management, phonics, SEN support</p>',
    ]);

    $formattedCv = app(FormattedCvGenerator::class)->generate($this->candidate, $this->cvDocument);

    expect($formattedCv)->toBeInstanceOf(FormattedCv::class)
        ->and($formattedCv->content)->toContain('Jane Doe')
        ->and($formattedCv->content)->toContain('An experienced teacher')
        ->and($formattedCv->content)->toContain('Ashlawn School')
        ->and($formattedCv->content)->toContain('Year 3 Teacher')
        ->and($formattedCv->content)->toContain('Planned and delivered the KS2 curriculum')
        ->and($formattedCv->content)->toContain('Led the school\'s phonics programme')
        ->and($formattedCv->content)->toContain('University of Warwick')
        ->and($formattedCv->content)->toContain('Classroom management');

    expect($formattedCv->content)->not->toContain('jane.doe@example.com')
        ->and($formattedCv->content)->not->toContain('01234 567890')
        ->and($formattedCv->content)->not->toContain('07700 900000')
        ->and($formattedCv->content)->not->toContain('123 Fake Street');

    expect($formattedCv->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($formattedCv->pdf_path);
});

test('the generated pdf filename is postfixed with the company name, derived from the original cv filename', function () {
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    $formattedCv = app(FormattedCvGenerator::class)->generate($this->candidate, $this->cvDocument);

    expect($formattedCv->pdf_path)->toContain('jane-doe-cv-applebough.pdf');
});

test('running generation again updates the same row rather than creating a duplicate', function () {
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    app(FormattedCvGenerator::class)->generate($this->candidate, $this->cvDocument);
    app(FormattedCvGenerator::class)->generate($this->candidate, $this->cvDocument);

    expect(FormattedCv::where('candidate_type', EducationCandidate::class)->where('candidate_id', $this->candidate->id)->count())->toBe(1);
});

test('regeneratePdf re-renders the pdf from the currently saved content without re-parsing', function () {
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    $formattedCv = app(FormattedCvGenerator::class)->generate($this->candidate, $this->cvDocument);
    $originalPdfPath = $formattedCv->pdf_path;

    $formattedCv->update(['content' => '<h1>Hand-edited content</h1>']);

    app(FormattedCvGenerator::class)->regeneratePdf($this->candidate, $formattedCv);

    expect($formattedCv->fresh()->pdf_path)->toBe($originalPdfPath);
    Storage::disk('local')->assertExists($formattedCv->fresh()->pdf_path);
});
