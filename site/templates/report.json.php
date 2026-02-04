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
$json['summary'] = $page->summary()->value();
$json['bibliography'] = $page->bibliography()->toStructure()->toArray();

// Get related reports by shared tags
$currentTags = array_filter(array_map('trim', explode(',', $page->tags()->value())));
$library = $site->find('bibliotheque');

$relatedReports = [];
if ($library && count($currentTags) > 0) {
  $relatedReports = $library->children()
    ->listed()
    ->filter(function ($report) use ($currentTags, $page) {
      if ($report->id() === $page->id()) return false;
      $reportTags = array_filter(array_map('trim', explode(',', $report->tags()->value())));
      return count(array_intersect($currentTags, $reportTags)) > 0;
    })
    ->sortBy('dateStart', 'desc')
    ->map(function ($item) {
      return [
        'slug' => $item->slug(),
        'title' => $item->title()->value(),
        'headerImage' => $item->headerImage()->toFile() ? Utils::getJsonEncodeImageData($item->headerImage()->toFile()) : null,
        'tags' => $item->tags()->value(),
        'dateStart' => $item->dateStart()->value(),
        'preview' => $item->preview()->value(),
      ];
    })->data();
}

$json['relatedReports'] = array_values($relatedReports);

echo json_encode($json);
