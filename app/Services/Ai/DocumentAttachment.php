<?php

namespace App\Services\Ai;

use Laravel\Ai\Files\Document;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\IOFactory;

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
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'docx') {
            return Document::fromString(self::extractDocxText($filePath), 'text/plain');
        }

        return Document::fromPath($filePath);
    }

    private static function extractDocxText(string $filePath): string
    {
        $document = IOFactory::load($filePath, 'Word2007');

        $text = '';

        foreach ($document->getSections() as $section) {
            $text .= self::extractContainerText($section);
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
