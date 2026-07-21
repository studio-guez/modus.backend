<?php

/**
 * fix-large-images.php
 *
 * One-off maintenance script: scans the `content/` directory for images that
 * are either oversized or CMYK JPEGs (both known to exhaust PHP's memory
 * limit when Kirby/GD generates thumbnails from them) and fixes them in place.
 *
 * This does NOT touch the `media/` folder (that's just a generated thumbnail
 * cache) - run "rm -rf media/pages media/site" (or clear cache from the
 * Panel) afterwards so Kirby regenerates thumbnails from the fixed originals.
 *
 * Usage (run from the project root, inside the container):
 *   php _utils/fix-large-images.php --dry-run   # list what would change
 *   php _utils/fix-large-images.php             # actually fix the files
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ImageGuard.php';

$contentRoot = realpath(__DIR__ . '/../content');

if ($contentRoot === false) {
    fwrite(STDERR, "content/ directory not found\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($contentRoot, FilesystemIterator::SKIP_DOTS)
);

$changed = 0;
$scanned = 0;

foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() === false) {
        continue;
    }

    $extension = strtolower($fileInfo->getExtension());

    if (in_array($extension, $extensions, true) === false) {
        continue;
    }

    $scanned++;
    $root = $fileInfo->getPathname();

    try {
        $report = ImageGuard::process($root, $dryRun);
    } catch (Throwable $e) {
        echo "ERROR   {$root}: {$e->getMessage()}\n";
        continue;
    }

    if ($report === null) {
        continue;
    }

    $changed++;
    $reasons = [];

    if ($report['wasOversized']) {
        $reasons[] = "oversized ({$report['originalWidth']}x{$report['originalHeight']})";
    }

    if ($report['wasCmyk']) {
        $reasons[] = 'CMYK';
    }

    $prefix = $dryRun ? 'WOULD FIX' : 'FIXED';
    echo "{$prefix}  {$root} - " . implode(', ', $reasons) . "\n";
}

echo "\nScanned {$scanned} image(s), " . ($dryRun ? 'would fix' : 'fixed') . " {$changed}.\n";

if ($dryRun === false && $changed > 0) {
    echo "\nDon't forget to clear the media cache so thumbnails are regenerated from the fixed originals:\n";
    echo "  rm -rf media/pages media/site\n";
}
