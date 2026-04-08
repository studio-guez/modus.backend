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
    'debug' => true,
    'emailFrom' => [
        'name'    => getenv('EMAIL_FROM_NAME') ?: 'Modus',
        'address' => getenv('EMAIL_FROM_ADDRESS') ?: 'webmaster@cms.modus-ge.ch',
    ],
    'email' => [
        'transport' => [
            'type'     => 'smtp',
            'host'     => getenv('SMTP_HOST') ?: 'smtp.example.com',
            'port'     => (int)(getenv('SMTP_PORT') ?: 587),
            'security' => getenv('SMTP_SECURITY') ?: 'tls',
            'auth'     => true,
            'username' => getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_PASSWORD') ?: '',
        ]
    ],
    'hooks' => [
        'page.render:before' => function ($event) {
            header("Access-Control-Allow-Origin: *");
        },
        // Store the creator on new project/report pages
        'page.create:after' => function ($page) {
            $user = kirby()->user();
            if ($user && $user->role()->name() === 'contributeur') {
                $template = $page->intendedTemplate()->name();
                if (in_array($template, ['project', 'report'])) {
                    kirby()->impersonate('kirby', function () use ($page, $user) {
                        $page->update(['createdBy' => $user->email()]);
                    });
                }
            }
        },
        // Restrict contributeur: only create project/report pages
        'page.create:before' => function ($page, $input) {
            $user = kirby()->user();
            if ($user && $user->role()->name() === 'contributeur') {
                $template = $page->intendedTemplate()->name();
                if (!in_array($template, ['project', 'report'])) {
                    throw new Exception('Vous n\'êtes pas autorisé·e à créer ce type de page.');
                }
            }
        },
        'page.update:before' => function ($page, $values, $strings) {
            $user = kirby()->user();
            // Contributeur: can only edit own draft project/report pages
            if ($user && $user->role()->name() === 'contributeur') {
                $template = $page->intendedTemplate()->name();
                if (in_array($template, ['project', 'report'])) {
                    if ($page->status() !== 'draft') {
                        throw new Exception('Vous ne pouvez modifier que les brouillons.');
                    }
                    $createdBy = $page->content()->get('createdBy')->value();
                    if (!empty($createdBy) && $createdBy !== $user->email()) {
                        throw new Exception('Vous ne pouvez modifier que vos propres pages.');
                    }
                }
            }

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
            // Contributeur: cannot change status at all
            $user = kirby()->user();
            if ($user && $user->role()->name() === 'contributeur') {
                throw new Exception('Vous n\'êtes pas autorisé·e à publier ou modifier le statut des pages.');
            }

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
            // Contributeur: can only delete own draft project/report pages
            $user = kirby()->user();
            if ($user && $user->role()->name() === 'contributeur') {
                $template = $page->intendedTemplate()->name();
                if (!in_array($template, ['project', 'report'])) {
                    throw new Exception('Vous n\'êtes pas autorisé·e à supprimer cette page.');
                }
                if ($page->status() !== 'draft') {
                    throw new Exception('Vous ne pouvez supprimer que les brouillons.');
                }
                $createdBy = $page->content()->get('createdBy')->value();
                if (!empty($createdBy) && $createdBy !== $user->email()) {
                    throw new Exception('Vous ne pouvez supprimer que vos propres pages.');
                }
            }

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
            'pattern' => '/news.json',
            'method' => 'GET',
            'action' => function () {
                header("Access-Control-Allow-Origin: *");
                return Page::factory([
                    'template'  => 'news.json',
                    'slug'      => 'news',
                ]);
            }
        ],
        [
            'pattern' => '/project-tags.json',
            'method' => 'GET',
            'action' => function () {
                header("Access-Control-Allow-Origin: *");
                return Page::factory([
                    'template'  => 'project-tags.json',
                    'slug'      => 'project-tags',
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
