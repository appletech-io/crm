<?php

use App\Ai\Agents\CandidateProfileWriter;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\SampleProfile;
use App\Services\Candidates\CandidateProfileGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::factory()->create(['name' => 'Applebough']);
    $this->educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $this->healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    Storage::disk('local')->put('sample-profiles/sample-one.pdf', 'fake pdf contents');

    SampleProfile::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->educationIndustry->id,
        'path' => 'sample-profiles/sample-one.pdf',
    ]);
});

test('generation produces a profile from the candidate\'s qualification, skills, and employment history', function () {
    $qualification = Qualification::factory()->create(['company_id' => $this->company->id, 'name' => 'PGCE']);
    $skill = CandidateSkill::factory()->create(['company_id' => $this->company->id, 'name' => 'Phonics']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'qualification_id' => $qualification->id,
    ]);
    $candidate->skills()->attach($skill);
    $candidate->employmentHistories()->create([
        'company_name' => 'Ashlawn School',
        'job_title' => 'Year 3 Teacher',
        'worked_from' => '2018-09-01',
        'worked_to' => '2023-07-01',
    ]);

    CandidateProfileWriter::fake(fn () => [
        'summary' => 'An experienced primary school teacher.',
        'experienceHtml' => '<h3>Year 3 Teacher, Ashlawn School</h3><p>Led the KS2 curriculum.</p>',
        'qualificationsHtml' => '<p>PGCE</p>',
        'skillsHtml' => '<ul><li>Phonics</li></ul>',
    ]);

    $profile = app(CandidateProfileGenerator::class)->generate($candidate->fresh());

    expect($profile)->toBeInstanceOf(CandidateProfile::class)
        ->and($profile->content)->toContain('Jane Doe')
        ->and($profile->content)->toContain('An experienced primary school teacher')
        ->and($profile->content)->toContain('Ashlawn School')
        ->and($profile->content)->toContain('Led the KS2 curriculum')
        ->and($profile->content)->toContain('PGCE')
        ->and($profile->content)->toContain('Phonics');

    expect($profile->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($profile->pdf_path);
});

test('the sample profile for the candidate\'s own sector is attached to the prompt', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateProfileWriter::fake(function (string $prompt, Collection $attachments) {
        expect($attachments)->toHaveCount(1);

        return ['summary' => 'Summary'];
    });

    app(CandidateProfileGenerator::class)->generate($candidate);
});

test('a candidate in a sector with no sample profiles still generates, with no attachments', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateProfileWriter::fake(function (string $prompt, Collection $attachments) {
        expect($attachments)->toHaveCount(0);

        return ['summary' => 'Summary'];
    });

    $profile = app(CandidateProfileGenerator::class)->generate($candidate);

    expect($profile->content)->toContain('Summary');
});

test('a healthcare candidate\'s structured employment history is used, since it has no raw employment_history column', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Sam',
        'last_name' => 'Nurse',
    ]);
    $candidate->employmentHistories()->create([
        'company_name' => 'City Hospital',
        'job_title' => 'Staff Nurse',
        'worked_from' => '2019-01-01',
        'worked_to' => null,
    ]);

    CandidateProfileWriter::fake(function (string $prompt) {
        expect($prompt)->toContain('City Hospital')
            ->and($prompt)->toContain('Staff Nurse');

        return ['summary' => 'Summary'];
    });

    app(CandidateProfileGenerator::class)->generate($candidate);
});

test('running generation again updates the same row rather than creating a duplicate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateProfileWriter::fake(fn () => ['summary' => 'Summary']);

    app(CandidateProfileGenerator::class)->generate($candidate);
    app(CandidateProfileGenerator::class)->generate($candidate->fresh());

    expect(CandidateProfile::where('candidate_type', EducationCandidate::class)->where('candidate_id', $candidate->id)->count())->toBe(1);
});

test('regeneratePdf re-renders the pdf from the currently saved content without calling the ai again', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    CandidateProfileWriter::fake(fn () => ['summary' => 'Summary']);

    $profile = app(CandidateProfileGenerator::class)->generate($candidate);
    $originalPdfPath = $profile->pdf_path;

    $profile->update(['content' => '<h1>Hand-edited content</h1>']);

    CandidateProfileWriter::fake(function () {
        throw new RuntimeException('The AI should not be called by regeneratePdf().');
    });

    app(CandidateProfileGenerator::class)->regeneratePdf($candidate, $profile);

    expect($profile->fresh()->pdf_path)->toBe($originalPdfPath);
    Storage::disk('local')->assertExists($profile->fresh()->pdf_path);
});
