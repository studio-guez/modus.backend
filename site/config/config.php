<?php

use Kirby\Toolkit\V;

// Custom validators for conditional URL validation
V::$validators['spotifyUrl'] = function ($value, $mediaType) {
    if ($mediaType !== 'podcast') return true;
    if (empty($value)) return true; // Let required handle empty values
    return str_starts_with($value, 'https://open.spotify.com/episode/');
};

V::$validators['youtubeUrl'] = function ($value, $mediaType) {
    if ($mediaType !== 'video') return true;
    if (empty($value)) return true;
    return str_starts_with($value, 'https://youtube.com/watch?v=');
};

/**
 * The config file is optional. It accepts a return array with config options
 * Note: Never include more than one return statement, all options go within this single return array
 * In this example, we set debugging to true, so that errors are displayed onscreen.
 * This setting must be set to false in production.
 * All config options: https://getkirby.com/docs/reference/system/options
 */
return [
    'url' => getenv('CMS_URL') ?: 'http://localhost:8080',
    'panel' => [
        'install' => true
    ],
    'debug' => true,
    'hooks' => [
        'page.render:before' => function ($event) {
            header("Access-Control-Allow-Origin: *");
        },
        'page.update:before' => function ($page, $values, $strings) {
            // Validate media URLs conditionally (only when page is listed)
            if ($page->status() === 'listed' && $page->intendedTemplate()->name() === 'media') {
                $mediaType = $values['mediaType'] ?? $page->mediaType()->value();

                if ($mediaType === 'podcast') {
                    $spotifyUrl = $values['spotifyUrl'] ?? $page->spotifyUrl()->value();
                    if (!empty($spotifyUrl) && !str_starts_with($spotifyUrl, 'https://open.spotify.com/episode/')) {
                        throw new Exception('Le lien Spotify doit commencer par https://open.spotify.com/episode/');
                    }
                }

                if ($mediaType === 'video') {
                    $youtubeUrl = $values['youtubeUrl'] ?? $page->youtubeUrl()->value();
                    if (!empty($youtubeUrl) && !preg_match('/^https:\/\/(www\.)?youtube\.com\/(watch\?v=|shorts\/)/', $youtubeUrl)) {
                        throw new Exception('Le lien YouTube doit commencer par https://youtube.com/watch?v=');
                    }
                }
            }
        },
        'page.changeStatus:before' => function ($page, $status) {
            // Validate before publishing
            if ($status === 'listed' && $page->intendedTemplate()->name() === 'media') {
                $mediaType = $page->mediaType()->value();

                if ($mediaType === 'podcast') {
                    $spotifyUrl = $page->spotifyUrl()->value();
                    if (!empty($spotifyUrl) && !str_starts_with($spotifyUrl, 'https://open.spotify.com/episode/')) {
                        throw new Exception('Le lien Spotify doit commencer par https://open.spotify.com/episode/');
                    }
                }

                if ($mediaType === 'video') {
                    $youtubeUrl = $page->youtubeUrl()->value();
                    if (!empty($youtubeUrl) && !preg_match('/^https:\/\/(www\.)?youtube\.com\/(watch\?v=|shorts\/)/', $youtubeUrl)) {
                        throw new Exception('Le lien YouTube doit commencer par https://youtube.com/watch?v=');
                    }
                }
            }
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
        [
            'pattern' => '/menus.json',
            'method' => 'GET',
            'action' => function () {
                header("Access-Control-Allow-Origin: *");
                return Page::factory([
                    'template'  => 'menus.json',
                    'slug'      => 'menus',
                ]);
            }
        ],
        [
            'pattern' => 'rapport/(:any)/pdf',
            'method' => 'GET',
            'action' => function ($slug) {
                header("Access-Control-Allow-Origin: *");

                // Find the report page
                $page = site()->find('bibliotheque/' . $slug);

                if (!$page || $page->intendedTemplate()->name() !== 'report') {
                    return site()->errorPage();
                }

                // Render using the PDF content representation (report.pdf.php)
                return $page->render([], 'pdf');
            }
        ],
    ]
];
