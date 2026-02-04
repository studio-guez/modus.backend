<?php

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

$json = [];

// Build bibliography reference map (shortcode id => number) and add index to each item
$bibliographyRaw = $page->bibliography()->toStructure()->toArray();
$refMap = [];
$bibliography = [];
$index = 1;
foreach ($bibliographyRaw as $ref) {
  if (!empty($ref['id'])) {
    // Normalize the id by removing [ref:...] wrapper if present
    $id = $ref['id'];
    if (preg_match('/^\[ref:([a-zA-Z0-9]+)\]$/', $id, $matches)) {
      $id = $matches[1];
    }
    $refMap[$id] = $index;
  }
  $ref['index'] = $index;
  $bibliography[] = $ref;
  $index++;
}

/**
 * Replace bibliography shortcodes [ref:xxx] with <b>[number]</b>
 */
function replaceBibliographyRefs(string $text, array $refMap): string
{
  return preg_replace_callback('/\[ref:([a-zA-Z0-9]+)\]/', function ($matches) use ($refMap) {
    $id = $matches[1];
    if (isset($refMap[$id])) {
      return '<b>[' . $refMap[$id] . ']</b>';
    }
    return $matches[0]; // Keep original if not found
  }, $text);
}

$body = $page->body()->toBlocks()->map(function ($item) use ($refMap) {

  $content = $item->toArray();

  // Replace bibliography shortcodes in text content
  if (isset($content['content']['text'])) {
    $content['content']['text'] = replaceBibliographyRefs($content['content']['text'], $refMap);
  }

  return [
    'image'     => array_values(Utils::getImageArrayDataInPage($item->image()->toFiles())),
    'content'   => $content,
  ];
})->data();

// Resolve tag UUIDs to objects with name and slug
// Tags are stored as "page://UUID, page://UUID" format
$tagUuids = array_filter(array_map('trim', explode(',', $page->tags()->value())));
$tagsPage = $site->find('tags');
$resolvedTags = [];
if ($tagsPage) {
  foreach ($tagUuids as $tagUuid) {
    // tagUuid already includes "page://" prefix
    $tagPage = $tagsPage->children()->listed()->findBy('uuid', $tagUuid);
    if ($tagPage) {
      $resolvedTags[] = [
        'name' => $tagPage->title()->value(),
        'slug' => $tagPage->slug(),
      ];
    }
  }
}

$json['options'] = [
  'showInNav'             => $page->showMenu()->toBool(),
  'headerTitle'           => $page->headerTitle()->value(),
  'preview'               => $page->preview()->value(),
  'headerImage'           => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'dateStart'             => $page->dateStart()->value(),
  'tags'                  => $resolvedTags,
];

$json['body'] = $body;
$json['title'] = $page->title();
$json['summary'] = $page->summary()->value();
$json['bibliography'] = $bibliography;

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
