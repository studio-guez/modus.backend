<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

// Slugs that have dedicated frontend routes (handled as static paths)
$dedicatedRouteSlugs = [
  'home',
  'projects',
  'proposer-un-projet',
  'boite-a-outils',
  'medias',
  'bibliotheque',
  'tags',
];

// Returns ['slug' => ..., 'modified' => 'YYYY-MM-DD'] for a page
$entry = function ($p) {
  return [
    'slug'     => $p->slug(),
    'modified' => $p->modified('Y-m-d') ?: null,
  ];
};

// Top-level listed pages rendered via /[slug].vue, excluding dedicated routes
// and Google verification pages
$genericPages = array_values(site()->children()->listed()->filter(function ($p) use ($dedicatedRouteSlugs) {
  $slug = $p->slug();
  if (in_array($slug, $dedicatedRouteSlugs)) return false;
  if (str_starts_with($slug, 'google')) return false;
  return true;
})->map($entry)->data());

// Individual projects → /project/[slug]
$projects = site()->find('projects');
$projectPages = $projects
  ? array_values($projects->children()->listed()->map($entry)->data())
  : [];

// Individual reports → /rapport/[slug]
$bibliotheque = site()->find('bibliotheque');
$reportPages = $bibliotheque
  ? array_values($bibliotheque->children()->listed()->map($entry)->data())
  : [];

// Toolkit tools → /boite-a-outils/[slug]
$toolkit = site()->find('boite-a-outils');
$toolPages = $toolkit
  ? array_values($toolkit->children()->listed()->map($entry)->data())
  : [];

// Tags → /tag/[slug]
$tagsPage = site()->find('tags');
$tagPages = $tagsPage
  ? array_values($tagsPage->children()->listed()->map($entry)->data())
  : [];

echo json_encode([
  'genericPages' => $genericPages,
  'projects'     => $projectPages,
  'reports'      => $reportPages,
  'tools'        => $toolPages,
  'tags'         => $tagPages,
]);
