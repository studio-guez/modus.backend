<?php

require_once '_utils/Utils.php';

use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$allTags = [];

// Collect tags from all listed projects
$projectsPage = $site->find('projects');
if ($projectsPage) {
    foreach ($projectsPage->children()->listed() as $item) {
        $tagsValue = $item->content()->tags()->value();
        if (!$tagsValue) continue;
        
        $resolvedTags = Utils::resolveTagsFromUuids($tagsValue, $site);
        $allTags[] = $resolvedTags;
    }
}

$json = [
    'tags' => Utils::collectUniqueTags($allTags),
];

echo json_encode($json);
