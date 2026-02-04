<?php

/**
 * Custom Marks Plugin
 * Adds a highlight mark (<mark>) to the Kirby writer field
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 * @version   1.0.0
 */

use Kirby\Sane\Html;

// Add 'mark' to allowed HTML tags so it's not stripped during sanitization
Html::$allowedTags['mark'] = true;

Kirby::plugin('oplus/custom-marks', []);
