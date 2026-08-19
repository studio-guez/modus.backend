<?php

/**
 * Image Guard Plugin
 *
 * Prevents oversized / CMYK images from exhausting PHP's memory limit when
 * Kirby later generates thumbnails from them (GdLib/SimpleImage decodes the
 * full-size original into memory before any resizing happens).
 *
 * On every new upload or file replacement, this:
 * - Downscales images whose longest edge exceeds ImageGuard::MAX_DIMENSION
 * - Converts CMYK JPEGs to RGB
 *
 * See ImageGuard.php (in this plugin directory) for the actual processing
 * logic, which is also shared with _utils/fix-large-images.php.
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 * @version   1.0.0
 */

use Kirby\Cms\File;

require_once __DIR__ . '/ImageGuard.php';

Kirby::plugin('oplus/image-guard', [
    'hooks' => [
        'file.create:after' => function (File $file) {
            if ($file->isResizable() === false) {
                return;
            }

            try {
                ImageGuard::process($file->root());
            } catch (Throwable $e) {
                error_log('[image-guard] Failed to process ' . $file->root() . ': ' . $e->getMessage());
            }
        },
        'file.replace:after' => function (File $newFile) {
            if ($newFile->isResizable() === false) {
                return;
            }

            try {
                ImageGuard::process($newFile->root());
            } catch (Throwable $e) {
                error_log('[image-guard] Failed to process ' . $newFile->root() . ': ' . $e->getMessage());
            }
        },
    ],
]);
