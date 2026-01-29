<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];

$body = $page->body()->toBlocks()->map(function ($item) {

  $content = $item->toArray();

  return [
    'image'     => array_values(Utils::getImageArrayDataInPage($item->image()->toFiles())),
    'content'   => $content,
  ];
})->data();

$json['options'] = [
  'showInNav'             => $page->showMenu()->toBool(),
  'headerTitle'           => $page->headerTitle()->value(),
  'preview'               => $page->preview()->value(),
  'headerImage'           => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'dateStart'             => $page->dateStart()->value(),
  'tags'                  => $page->tags()->value(),
];

$json['body'] = $body;
$json['title'] = $page->title();
$json['bibliography'] = $page->bibliography()->toStructure()->toArray();

echo json_encode($json);
