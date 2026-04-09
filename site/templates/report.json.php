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
 * Replace bibliography shortcodes [ref:xxx] with <b class="bib-ref" data-ref="number">[number]</b>
 */
function replaceBibliographyRefs(string $text, array $refMap): string
{
  return preg_replace_callback('/\[ref:([a-zA-Z0-9]+)\]/', function ($matches) use ($refMap) {
    $id = $matches[1];
    if (isset($refMap[$id])) {
      $num = $refMap[$id];
      return '<b class="bib-ref" data-ref="' . $num . '">[' . $num . ']</b>';
    }
    return $matches[0]; // Keep original if not found
  }, $text);
}

/**
 * Replace figure shortcodes [figure:xxx] with <mark class="figure-ref" data-figure="number">(Figure number)</mark>
 */
function replaceFigureRefs(string $text, array $figureMap): string
{
  return preg_replace_callback('/\[figure:([a-zA-Z0-9]+)\]/', function ($matches) use ($figureMap) {
    $id = $matches[1];
    if (isset($figureMap[$id])) {
      $num = $figureMap[$id];
      return '<mark class="figure-ref" data-figure="' . $num . '">(Figure ' . $num . ')</mark>';
    }
    return $matches[0];
  }, $text);
}

// Build figure reference map by scanning body blocks for mdreportimage with id shortcodes
$blocks = $page->body()->toBlocks();
$figureMap = [];
$figureIndex = 1;
foreach ($blocks as $block) {
  if ($block->type() === 'mdreportimage') {
    $blockContent = $block->toArray();
    $figId = $blockContent['content']['id'] ?? '';
    if (!empty($figId)) {
      if (preg_match('/^\[figure:([a-zA-Z0-9]+)\]$/', $figId, $matches)) {
        $figId = $matches[1];
      }
      $figureMap[$figId] = $figureIndex;
    }
    $figureIndex++;
  }
}

$body = $page->body()->toBlocks()->map(function ($item) use ($refMap, $figureMap) {

  $content = $item->toArray();

  // Replace bibliography and figure shortcodes in text content
  if (isset($content['content']['text'])) {
    $content['content']['text'] = replaceBibliographyRefs($content['content']['text'], $refMap);
    $content['content']['text'] = replaceFigureRefs($content['content']['text'], $figureMap);
  }

  // Add figure number to mdreportimage blocks
  $figureNumber = null;
  if ($item->type() === 'mdreportimage') {
    $figId = $content['content']['id'] ?? '';
    if (!empty($figId)) {
      $normalizedId = $figId;
      if (preg_match('/^\[figure:([a-zA-Z0-9]+)\]$/', $figId, $matches)) {
        $normalizedId = $matches[1];
      }
      $figureNumber = $figureMap[$normalizedId] ?? null;
    }
  }

  return [
    'image'     => array_values(Utils::getImageArrayDataInPage($item->image()->toFiles())),
    'content'   => $content,
    'figureNumber' => $figureNumber,
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
