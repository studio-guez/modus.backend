<?php

use Kirby\Cms;

/** @var Cms\App $kirby */
/** @var Cms\Site $site */
/** @var Cms\Page $page */
/** @var Cms\User $user */


echo json_encode(value: [
  'title' => $site->title(),
  'children' => array_values($site->children()->map(callback: fn($item) => [
    //      'global' => $item->toArray(),
    'children' => $item->children()->listed()->sortBy('dateStart'),
    'title' => $item->title(),
    'headerimage' => $item->headerimage(),
    'showinnav' => $item->showinnav(),
    'headertitle' => $item->headertitle(),
    'id' => $item->id(),
    'mediaUrl' => $item->mediaUrl(),
    'mediaRoot' => $item->mediaRoot(),
    'num' => $item->num(),
    'parent' => $item->parent(),
    'slug' => $item->slug(),
    'template' => $item->template(),
    'translations' => $item->translations(),
    'uid' => $item->uid(),
    'uri' => $item->uri(),
    'url' => $item->url(),
  ])->data()),
]);
