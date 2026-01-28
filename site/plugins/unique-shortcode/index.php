<?php

Kirby::plugin('modus/unique-shortcode', [
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
