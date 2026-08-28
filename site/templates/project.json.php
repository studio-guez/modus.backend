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
  'headerTitle'           => $page->headerTitle()->value(),
  'preview'               => $page->preview()->value(),
  'headerImage'           => $page->headerImage()->toFile() ? Utils::getJsonEncodeImageData($page->headerImage()->toFile()) : null,
  'dateStart'             => $page->dateStart()->value(),
  'isProjectWithDuration' => $page->isProjectWithDuration()->value(),
  'dateEnd'               => $page->dateEnd()->value(),
  'projectStatus'         => $page->projectStatus()->value(),
  'tags'                  => $page->tags()->value(),
  'subpages'              => array_values($page->children()->toArray()),
  'projectType'           => $page->projectType()->value(),
];

// Reverse lookup: find reports that link to this project via their linkedProjects field
$library = $site->find('bibliotheque');
$linkedReports = [];
if ($library) {
  $currentId = $page->id();
  foreach ($library->children()->listed() as $report) {
    foreach ($report->linkedProjects()->toPages() as $linkedPage) {
      if ($linkedPage->id() === $currentId) {
        $linkedReports[] = [
          'name' => $report->title()->value(),
          'url' => '/rapport/' . $report->slug(),
        ];
        break;
      }
    }
  }
}

// Convert body to a plain indexed array and append linked reports at the end
$body = array_values($body);
if (count($linkedReports) > 0) {
  $body[] = [
    'image' => [],
    'content' => [
      'type' => 'linksSection',
      'content' => [
        'title' => 'Rapports en lien',
        'collapsible' => 'false',
        'openbydefault' => 'false',
        'links' => $linkedReports,
      ],
    ],
  ];
}

$json['body'] = $body;
$json['title'] = $page->title();

echo json_encode($json);
