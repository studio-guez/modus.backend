<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];

$body = $page->body()->toBlocks()->map(function ($item) use ($site) {

  $content = $item->toArray();

  $result = [
    'image'     => array_values(Utils::getImageArrayDataInPage($item->image()->toFiles())),
    'content'   => $content,
  ];

  // Resolve highlights items from page:// UUIDs
  if ($content['type'] === 'highlights' && !empty($content['content']['highlightsitems'])) {
    $result['highlightsItems'] = Utils::resolveHighlightsItems($content['content']['highlightsitems'], $site);
  }

  return $result;
})->data();

$json['options'] = [
  'headerTitle'     => $page->headerTitle()->value(),
  'headerImage'     => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'actualite1title' => $page->actualite1title()->value() ?: null,
  'actualite1link'  => $page->actualite1link()->value() ?: null,
  'actualite2title' => $page->actualite2title()->value() ?: null,
  'actualite2link'  => $page->actualite2link()->value() ?: null,
];

$json['body'] = $body;

echo json_encode($json);
