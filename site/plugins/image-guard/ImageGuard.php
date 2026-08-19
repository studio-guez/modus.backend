<?php

use claviska\SimpleImage;

/**
 * ImageGuard
 *
 * Prevents oversized / CMYK images from exhausting PHP's memory limit when
 * Kirby (via GD/SimpleImage) decodes them to generate thumbnails.
 *
 * - Downscales any image whose longest edge exceeds MAX_DIMENSION
 * - Converts CMYK JPEGs (common export from Adobe Illustrator/Photoshop) to RGB,
 *   since GD does not reliably support the CMYK color space
 *
 * Used by:
 * - site/plugins/image-guard/index.php (hooks into new uploads)
 * - site/plugins/image-guard/fix-large-images.php (one-off cleanup of existing content)
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 */
class ImageGuard
{
    /** Longest edge (px) a stored original image is allowed to have. */
    public const MAX_DIMENSION = 4000;

    /** Quality used when re-encoding JPEGs. */
    public const JPEG_QUALITY = 85;

    /**
     * Inspects the image at $root and, if needed, downscales it and/or
     * converts it from CMYK to RGB.
     *
     * @param string $root Absolute path to the image file
     * @param bool $dryRun If true, only report what would change, don't write
     * @return array|null Details of the change, or null if the file was left untouched
     */
    public static function process(string $root, bool $dryRun = false): ?array
    {
        if (is_file($root) === false) {
            return null;
        }

        $info = @getimagesize($root);

        if ($info === false) {
            return null;
        }

        [$width, $height, $type] = $info;

        $isJpeg      = $type === IMAGETYPE_JPEG;
        $isCmyk      = $isJpeg && (($info['channels'] ?? 3) === 4);
        $isOversized = $width > static::MAX_DIMENSION || $height > static::MAX_DIMENSION;

        if ($isCmyk === false && $isOversized === false) {
            return null;
        }

        $report = [
            'root'          => $root,
            'originalWidth'  => $width,
            'originalHeight' => $height,
            'wasCmyk'        => $isCmyk,
            'wasOversized'   => $isOversized,
        ];

        if ($dryRun === true) {
            return $report;
        }

        // Give this one-off conversion plenty of headroom regardless of the
        // currently configured memory_limit.
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');

        try {
            $image = new SimpleImage();
            $image->fromFile($root);
            $image->autoOrient();

            if ($isOversized) {
                if ($width >= $height) {
                    $image->resize(static::MAX_DIMENSION, null);
                } else {
                    $image->resize(null, static::MAX_DIMENSION);
                }
            }

            // toFile() re-encodes through GD, which always writes RGB
            // truecolor, so this also strips the CMYK color space.
            $image->toFile($root, null, ['quality' => static::JPEG_QUALITY]);

            $report['newWidth']  = $image->getWidth();
            $report['newHeight'] = $image->getHeight();
        } finally {
            ini_set('memory_limit', $previousLimit);
        }

        return $report;
    }
}
