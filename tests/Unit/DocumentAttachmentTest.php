<?php

use App\Services\Ai\DocumentAttachment;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\LocalDocument;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Writes a real .docx (not a fake with a .docx name) so the PhpWord reader is
 * genuinely exercised — the failure mode being guarded against is a reader
 * that returns empty text rather than throwing.
 */
function writeDocx(string $path, array $lines): string
{
    $word = new PhpWord;
    $section = $word->addSection();

    foreach ($lines as $line) {
        $section->addText($line);
    }

    IOFactory::createWriter($word, 'Word2007')->save($path);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/document-attachment-test-*') ?: [] as $file) {
        @unlink($file);
    }
});

test('a docx is extracted to plain text so the model can read it', function () {
    $path = writeDocx(sys_get_temp_dir().'/document-attachment-test-cv.docx', [
        'Jane Doe',
        'jane@example.com',
        'Teacher at Oakwood Primary',
    ]);

    $attachment = DocumentAttachment::for($path);

    expect($attachment)->toBeInstanceOf(Base64Document::class)
        ->and($attachment->content())->toContain('Jane Doe')
        ->and($attachment->content())->toContain('jane@example.com')
        ->and($attachment->content())->toContain('Teacher at Oakwood Primary');
});

test('a docx extraction is never silently empty for a document that has text', function () {
    $path = writeDocx(sys_get_temp_dir().'/document-attachment-test-nested.docx', ['Nested content']);

    expect(trim(DocumentAttachment::for($path)->content()))->not->toBe('');
});

test('a pdf is attached as-is rather than being run through the word reader', function () {
    $path = sys_get_temp_dir().'/document-attachment-test-cv.pdf';
    file_put_contents($path, '%PDF-1.4 fake pdf bytes');

    expect(DocumentAttachment::for($path))->toBeInstanceOf(LocalDocument::class);
});

test('a docx that cannot be read fails loudly and leaves the original file on disk', function (string $contents) {
    $path = sys_get_temp_dir().'/document-attachment-test-corrupt.docx';
    file_put_contents($path, $contents);

    expect(fn () => DocumentAttachment::for($path))->toThrow(Exception::class);

    // PhpWord deletes the file it is handed when the archive is unreadable,
    // which for a bulk CV upload is the only copy of the document — it has
    // not been attached to a candidate yet.
    expect(file_exists($path))->toBeTrue();
})->with([
    'not a zip container at all' => ['this is not a zip container'],
    'zero-filled, as a truncated upload arrives' => [str_repeat("\0", 4096)],
]);

test('a docx with no extractable text is escalated rather than attached blank', function () {
    $path = writeDocx(sys_get_temp_dir().'/document-attachment-test-empty.docx', []);

    expect(fn () => DocumentAttachment::for($path))
        ->toThrow(RuntimeException::class, 'No text could be extracted');

    expect(file_exists($path))->toBeTrue();
});
