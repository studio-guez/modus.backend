<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];
$allTags = [];

$children = $page->children()->listed()->sortBy('dateStart', 'desc', 'dateEnd', 'desc')->map(function ($item) use ($site, &$allTags) {

  $content = $item->content();
  $contentArray = $content->toArray();

  // Resolve tags from UUIDs
  $resolvedTags = Utils::resolveTagsFromUuids($content->tags()->value(), $site);
  $contentArray['tags'] = $resolvedTags;
  $contentArray['projecttype'] = $content->projecttype()->value() ?: 'modus'; // Default to 'modus' if not set
  $allTags[] = $resolvedTags;

  return [
    'headerImage' => array_values(Utils::getImageArrayDataInPage($content->headerimage()->toFiles())),
    'slug'        => $item->slug(),
    'content'     => $contentArray,
    'modified'    => $item->modified('c'),
  ];
})->data();

$json['options'] = [
  'headerTitle'     => $page->headerTitle()->value(),
  'headerImage'     => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'preview'               => $page->preview()->value(),
  'availableTags'   => Utils::collectUniqueTags($allTags),
];

$json['children'] = $children;

echo json_encode($json);
