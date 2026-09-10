<?php

use App\Enums\CvFileType;

test('the accepted mime types cover pdf and docx', function () {
    expect(CvFileType::mimeTypes())->toBe([
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
});

test('the mime type map lets a browser-misreported extension fall back to the right type', function () {
    expect(CvFileType::mimeTypeMap())->toBe([
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
});

test('a path is resolved to its type regardless of extension casing', function (string $path, ?CvFileType $expected) {
    expect(CvFileType::tryFromPath($path))->toBe($expected);
})->with([
    'pdf' => ['bulk-cv-uploads/jane.pdf', CvFileType::Pdf],
    'uppercase pdf' => ['bulk-cv-uploads/jane.PDF', CvFileType::Pdf],
    'docx' => ['bulk-cv-uploads/jane.docx', CvFileType::Docx],
    'mixed case docx' => ['bulk-cv-uploads/jane.DocX', CvFileType::Docx],
    'legacy doc is not accepted' => ['bulk-cv-uploads/jane.doc', null],
    'no extension' => ['bulk-cv-uploads/jane', null],
]);
