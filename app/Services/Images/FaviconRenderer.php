<?php

namespace App\Services\Images;

class FaviconRenderer
{
    private const int SIZE = 256;

    /**
     * Pads an arbitrary (usually wide, non-square) logo onto a transparent
     * square canvas, scaled to fit and centered — the raster equivalent of
     * an SVG's `preserveAspectRatio="xMidYMid meet"`. Browsers stretch a
     * non-square raster favicon to fill their (square) tab-icon slot rather
     * than letterboxing it themselves, so that has to be done here instead.
     * Returns the original bytes unchanged if they can't be decoded as an
     * image, rather than failing the whole favicon.
     */
    public static function render(string $contents): string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return $contents;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(self::SIZE / $sourceWidth, self::SIZE / $sourceHeight);
        $targetWidth = (int) round($sourceWidth * $scale);
        $targetHeight = (int) round($sourceHeight * $scale);
        $offsetX = intdiv(self::SIZE - $targetWidth, 2);
        $offsetY = intdiv(self::SIZE - $targetHeight, 2);

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($canvas);
        $output = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $output;
    }
}
