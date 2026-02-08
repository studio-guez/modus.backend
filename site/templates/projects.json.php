<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];

$children = $page->children()->listed()->sort()->sortBy('dateStart')->map(function ($item) {

  $content = $item->content();

  return [
    'headerImage' => array_values(Utils::getImageArrayDataInPage($content->headerimage()->toFiles())),
    'slug'        => $item->slug(),
    'content'     => $content->toArray(),
    'modified'    => $item->modified('c'),
  ];
})->data();

$json['options'] = [
  'showInNav'       => $page->showMenu()->toBool(),
  'headerTitle'     => $page->headerTitle()->value(),
  'headerImage'     => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'preview'               => $page->preview()->value(),
];

$json['children'] = $children;

echo json_encode($json);
