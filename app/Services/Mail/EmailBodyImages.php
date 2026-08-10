<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailBodyImages
{
    /**
     * Rewrites any <img> tag pointing at our signed email-images proxy so
     * the image travels as a CID-embedded attachment on the outgoing email
     * instead of a link the recipient's client has to fetch live — more
     * reliable (most clients block or delay loading remote images by
     * default) and, for ad-hoc images, means the S3 copy doesn't need to be
     * kept reachable after the email has gone out.
     *
     * @return array{body: string, attachments: array<int, array{name: string, mimeType: string, inline: bool, contentId: string, content: string}>}
     */
    public static function embedInline(string $body): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        $attachments = [];

        $newBody = preg_replace_callback(
            '/<img\s[^>]*src="([^"]*\/email-images\/([^"?]+)[^"]*)"[^>]*>/i',
            function (array $matches) use ($disk, &$attachments): string {
                $path = urldecode($matches[2]);

                if (! $disk->exists($path)) {
                    return $matches[0];
                }

                $contentId = (string) Str::uuid();

                $attachments[] = [
                    'name' => basename($path),
                    'mimeType' => $disk->mimeType($path) ?: 'application/octet-stream',
                    'inline' => true,
                    'contentId' => $contentId,
                    'content' => $disk->get($path),
                ];

                return str_replace($matches[1], "cid:{$contentId}", $matches[0]);
            },
            $body,
        );

        return ['body' => $newBody ?? $body, 'attachments' => $attachments];
    }
}
