<?php

namespace App\Services\Ai;

use App\Enums\CvFileType;
use Laravel\Ai\Files\Document;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

/**
 * Builds an AI attachment from a local file path — shared between CvParser
 * and CandidateProfileWriter, both of which take PDF/docx uploads as
 * style/content references. A PDF is attached as-is: the model's document
 * input is built around rendering PDF pages directly. A .docx (a binary
 * zip/XML container) sent the same way would not be understood — it
 * wouldn't error, it would just silently extract nothing — so it's
 * extracted to plain text first instead.
 */
class DocumentAttachment
{
    public static function for(string $filePath): Document
    {
        if (CvFileType::tryFromPath($filePath) === CvFileType::Docx) {
            return Document::fromString(self::extractDocxText($filePath), 'text/plain');
        }

        return Document::fromPath($filePath);
    }

    /**
     * PhpWord's reader deletes the file it is handed when the archive turns
     * out to be unreadable, and reports the failure by returning a document
     * with no content rather than by throwing. The caller's path is often the
     * only copy of the upload — a bulk-uploaded CV has not been attached to a
     * candidate yet — so the reader is always given a disposable copy, and an
     * empty extraction is escalated to an exception so it is reported instead
     * of silently reaching the model as a blank attachment.
     */
    private static function extractDocxText(string $filePath): string
    {
        $workingCopy = tempnam(sys_get_temp_dir(), 'docx-extract-');

        if ($workingCopy === false || ! copy($filePath, $workingCopy)) {
            throw new RuntimeException("Could not stage a readable copy of the .docx at [{$filePath}].");
        }

        try {
            $document = IOFactory::load($workingCopy, 'Word2007');

            $text = '';

            foreach ($document->getSections() as $section) {
                $text .= self::extractContainerText($section);
            }
        } finally {
            @unlink($workingCopy);
        }

        if (trim($text) === '') {
            throw new RuntimeException("No text could be extracted from the .docx at [{$filePath}].");
        }

        return $text;
    }

    private static function extractContainerText(AbstractContainer $container): string
    {
        $text = '';

        foreach ($container->getElements() as $element) {
            if (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_string($value) ? $value : '')."\n";
            } elseif ($element instanceof AbstractContainer) {
                $text .= self::extractContainerText($element);
            }
        }

        return $text;
    }
}
