<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];

$json['options'] = [
  'headerTitle'           => $page->headerTitle()->value(),
  'preview'               => $page->preview()->value(),
  'headerImage'           => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'dateStart'             => $page->dateStart()->value(),
  'tags'                  => $page->tags()->value(),
  'mediaType'             => $page->mediaType()->value(),
  'spotifyUrl'            => $page->spotifyUrl()->value(),
  'youtubeUrl'            => $page->youtubeUrl()->value(),
];

$json['title'] = $page->title();

echo json_encode($json);
