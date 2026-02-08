<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];
$allChildren = [];
$globalTags = [];

// Current tag's UUID for filtering
$currentTagUuid = $page->uuid()->toString();

/**
 * Process children from a parent page, filtering by tag and adding pageType
 * Also collects ALL tags for navigation (not just from matching items)
 */
function processChildrenByTag(Page $parentPage, string $tagUuid, Site $site, string $pageType, array &$globalTags): array {
    $children = [];
    
    foreach ($parentPage->children()->listed() as $item) {
        $content = $item->content();
        $tagsValue = $content->tags()->value();
        
        if (!$tagsValue) continue;
        
        // Resolve tags and collect for global navigation
        $resolvedTags = Utils::resolveTagsFromUuids($tagsValue, $site);
        $globalTags[] = $resolvedTags;
        
        // Check if this item has the current tag
        $tagUuids = array_filter(array_map('trim', explode(',', $tagsValue)));
        $hasTag = in_array($tagUuid, $tagUuids);
        
        if (!$hasTag) continue;
        
        $contentArray = $content->toArray();
        $contentArray['tags'] = $resolvedTags;
        $contentArray['pageType'] = $pageType;
        
        $childData = [
            'headerImage' => array_values(Utils::getImageArrayDataInPage($content->headerimage()->toFiles())),
            'slug'        => $item->slug(),
            'content'     => $contentArray,
        ];
        
        // Add modified date for projects (for sorting)
        if ($pageType === 'project') {
            $childData['modified'] = $item->modified('c');
        }
        
        $children[] = $childData;
    }
    
    return $children;
}

// Get all content from projects
$projectsPage = $site->find('projects');
if ($projectsPage) {
    $projectChildren = processChildrenByTag($projectsPage, $currentTagUuid, $site, 'project', $globalTags);
    $allChildren = array_merge($allChildren, $projectChildren);
}

// Get all content from medias
$mediasPage = $site->find('medias');
if ($mediasPage) {
    $mediaChildren = processChildrenByTag($mediasPage, $currentTagUuid, $site, 'media', $globalTags);
    $allChildren = array_merge($allChildren, $mediaChildren);
}

// Get all content from bibliotheque (reports)
$bibliotequePage = $site->find('bibliotheque');
if ($bibliotequePage) {
    $reportChildren = processChildrenByTag($bibliotequePage, $currentTagUuid, $site, 'report', $globalTags);
    $allChildren = array_merge($allChildren, $reportChildren);
}

// Sort all children by dateStart desc, then dateEnd desc
usort($allChildren, function($a, $b) {
    $dateStartA = $a['content']['datestart'] ?? '';
    $dateStartB = $b['content']['datestart'] ?? '';
    $cmp = strcmp($dateStartB, $dateStartA);
    if ($cmp !== 0) return $cmp;
    
    $dateEndA = $a['content']['dateend'] ?? '';
    $dateEndB = $b['content']['dateend'] ?? '';
    return strcmp($dateEndB, $dateEndA);
});

// Get tag's own header image
$headerImage = $page->headerImage()->toFile();

$json['options'] = [
    'showInNav'     => false,
    'headerTitle'   => $page->title()->value(),
    'headerImage'   => $headerImage ? Utils::getJsonEncodeImageData($headerImage) : null,
    'preview'       => $page->description()->value(),
    'availableTags' => Utils::collectUniqueTags($globalTags),
];

$json['children'] = $allChildren;

echo json_encode($json);
