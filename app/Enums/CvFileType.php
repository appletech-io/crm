<?php

namespace App\Enums;

/**
 * The document formats a CV (or sample profile) may be uploaded in, keyed by
 * the MIME type the browser reports. Each format needs its own handling once
 * it reaches the AI parser (see the DocumentAttachment service), so the
 * accepted list and the extension mapping are kept together here rather than
 * duplicated across the upload fields and the parser that use them.
 */
enum CvFileType: string
{
    case Pdf = 'application/pdf';
    case Docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Docx => 'docx',
        };
    }

    /**
     * The MIME types to hand to a FileUpload's acceptedFileTypes().
     *
     * @return array<int, string>
     */
    public static function mimeTypes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * An extension => MIME type map for a FileUpload's mimeTypeMap(), so a
     * browser that reports a .docx as octet-stream still passes validation.
     *
     * @return array<string, string>
     */
    public static function mimeTypeMap(): array
    {
        $map = [];

        foreach (self::cases() as $type) {
            $map[$type->extension()] = $type->value;
        }

        return $map;
    }

    public static function tryFromPath(string $path): ?self
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        foreach (self::cases() as $type) {
            if ($type->extension() === $extension) {
                return $type;
            }
        }

        return null;
    }
}
