<?php

/**
 * Unique Shortcode Plugin
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 * @version   1.0.0
 */

Kirby::plugin('oplus/unique-shortcode', [
    'fields' => [
        'unique-shortcode' => [
            'extends' => 'text',
            'props' => [
                'prefix' => function ($prefix = 'ref') {
                    return $prefix;
                }
            ]
        ]
    ]
]);
