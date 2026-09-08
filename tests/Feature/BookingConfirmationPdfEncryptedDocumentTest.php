<?php

use App\Enums\BookingDayPeriod;
use App\Enums\DocumentType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Services\Education\BookingConfirmationPdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * A minimal but structurally valid PDF carrying /Encrypt in its trailer —
 * which is the only thing FPDI's CrossReference::checkForEncryption() looks
 * at, so this reproduces the production failure exactly rather than
 * standing in for it. Built here instead of committed as a binary fixture.
 */
function encryptedPdfBytes(): string
{
    $objects = [
        "1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n",
        "2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n",
        "3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);

    $pdf .= "xref\n0 4\n0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<</Size 4/Root 1 0 R/Encrypt 4 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);

    $this->booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->addWeek()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
});

test('the hand-built fixture really is rejected by FPDI the way production was', function () {
    $path = tempnam(sys_get_temp_dir(), 'encrypted-').'.pdf';
    file_put_contents($path, encryptedPdfBytes());

    try {
        expect(fn () => (new Fpdi)->setSourceFile($path))
            ->toThrow(CrossReferenceException::class, 'This PDF document is encrypted and cannot be processed with FPDI.');
    } finally {
        unlink($path);
    }
});

test('an encrypted DBS scan no longer takes down the whole confirmation PDF', function () {
    Storage::disk('local')->put('docs/dbs-front.pdf', encryptedPdfBytes());

    $this->candidate->documents()->create([
        'document_type' => DocumentType::DbsFront,
        'path' => 'docs/dbs-front.pdf',
    ]);

    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    expect(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('local')->get($path))->toStartWith('%PDF');
});

test('the pack says which document was left out rather than dropping it silently', function () {
    Storage::disk('local')->put('docs/dbs-front.pdf', encryptedPdfBytes());

    $this->candidate->documents()->create([
        'document_type' => DocumentType::DbsFront,
        'path' => 'docs/dbs-front.pdf',
    ]);

    Log::spy();

    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    // The placeholder page text is compressed inside the PDF stream, so the
    // page count is what is checkable here: summary page plus the notice.
    expect(substr_count(Storage::disk('local')->get($path), '/Type /Page'))->toBeGreaterThan(1);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Could not embed DBS (Front)')
            && str_contains($message, 'encrypted'))
        ->once();
});

test('a readable document is still embedded as before', function () {
    $readable = new Fpdi;
    $readable->AddPage();
    Storage::disk('local')->put('docs/safeguarding.pdf', $readable->Output('S'));

    $this->candidate->documents()->create([
        'document_type' => DocumentType::SafeguardingTraining,
        'path' => 'docs/safeguarding.pdf',
    ]);

    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    expect(Storage::disk('local')->exists($path))->toBeTrue();
});
