<?php

/**
 * The config file is optional. It accepts a return array with config options
 * Note: Never include more than one return statement, all options go within this single return array
 * In this example, we set debugging to true, so that errors are displayed onscreen.
 * This setting must be set to false in production.
 * All config options: https://getkirby.com/docs/reference/system/options
 */
return [
    'debug' => true,
    'hooks' => [
        'page.render:before' => function ($event) {
            header("Access-Control-Allow-Origin: *");
        },
        'page.delete:before' => function ($page) {
            // When a tag is deleted, remove its reference from all pages
            if ($page->intendedTemplate()->name() === 'tag') {
                $tagUuid = 'page://' . $page->uuid()->id();

                // Find all pages that have a tags field
                $allPages = site()->index();
                foreach ($allPages as $p) {
                    $tagsField = $p->tags();
                    if ($tagsField->isNotEmpty()) {
                        $currentTags = $tagsField->value();
                        // Check if this tag is referenced
                        if (strpos($currentTags, $tagUuid) !== false) {
                            // Remove the tag UUID from the list
                            $tagsArray = array_map('trim', explode(',', $currentTags));
                            $tagsArray = array_filter($tagsArray, function ($t) use ($tagUuid) {
                                return trim($t) !== $tagUuid;
                            });
                            $newTags = implode(', ', $tagsArray);

                            // Update the page
                            $p->update(['tags' => $newTags]);
                        }
                    }
                }
            }
        }
    ],
    'routes' => [
        [
            'pattern' => '/',
            'action'  => function () {
                return go('/panel');
            }
        ],
        [
            'pattern' => '/links-tree/formulaire_inscription_202502',
            'action'  => function () {
                return go('https://modus-ge.ch/forms/declic-mobilite');
            }
        ],
        [
            'pattern' => '/contact',
            'method' => 'GET|POST',
            'action' => function () {
                header("Access-Control-Allow-Origin: *");
                return Page::factory([
                    'template'  => 'contact',
                    'slug'      => 'contact',
                ]);
            }
        ],
        [
            'pattern' => '/pages-info.json',
            'method' => 'GET',
            'action' => function () {
                header("Access-Control-Allow-Origin: *");
                return Page::factory([
                    'template'  => 'pages-info',
                    'slug'      => 'pages-info',
                ]);
            }
        ],
    ]
];
