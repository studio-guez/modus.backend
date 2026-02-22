<?php

use Kirby\Cms;

/** @var Cms\App $kirby */
/** @var Cms\Site $site */
/** @var Cms\Page $page */

/**
 * Helper function to transform a menu item into API format
 */
function transformMenuItem($item, $site): array
{
  $result = [
    'type' => $item->itemType()->value() ?? 'page',
    'url' => '',
    'title' => '',
    'svgUrl' => null,
    'openInNewTab' => $item->openInNewTab()->toBool(),
  ];

  $itemType = $item->itemType()->value();

  // Determine URL based on item type
  switch ($itemType) {
    case 'page':
      $linkedPage = $item->linkedPage()->toPage();
      if ($linkedPage) {
        $result['url'] = '/' . $linkedPage->slug();
        $result['title'] = $linkedPage->title()->value();
        // Handle home page special case
        if ($linkedPage->slug() === 'home') {
          $result['url'] = '/';
        }

        // Append URL parameters if set
        $urlParams = $item->urlParams()->value();
        if (!empty($urlParams)) {
          $result['url'] .= '?' . $urlParams;
        }
      }
      break;

    case 'external':
      $result['url'] = $item->externalUrl()->value() ?? '';
      $result['title'] = $item->customTitle()->value() ?? $result['url'];
      $result['openInNewTab'] = true; // Force new tab for external links
      break;
  }

  // Override title if custom title is set
  $customTitle = $item->customTitle()->value();
  if (!empty($customTitle)) {
    $result['title'] = $customTitle;
  }

  // Handle SVG icon file
  $svgFile = $item->svgIcon()->toFile();
  if ($svgFile) {
    $result['svgUrl'] = $svgFile->url();
  }

  return $result;
}

/**
 * Helper function to transform menu items with children (for main menu)
 */
function transformMenuItemWithChildren($item, $site): array
{
  $result = transformMenuItem($item, $site);

  // Process children if they exist
  $children = $item->children();
  if ($children && $children->isNotEmpty()) {
    $result['children'] = [];
    foreach ($children->toStructure() as $child) {
      $result['children'][] = transformMenuItem($child, $site);
    }
  }

  return $result;
}

/**
 * Helper function to transform a menu structure field
 */
function transformMenu($menuField, $site, $withChildren = false): array
{
  $items = [];

  if ($menuField && $menuField->isNotEmpty()) {
    foreach ($menuField->toStructure() as $item) {
      if ($withChildren) {
        $items[] = transformMenuItemWithChildren($item, $site);
      } else {
        $items[] = transformMenuItem($item, $site);
      }
    }
  }

  return $items;
}

// Build the menus response
$response = [
  'topMenu' => transformMenu($site->topMenu(), $site),
  'mainMenu' => transformMenu($site->mainMenu(), $site, true), // with children
  'bottomMenu' => transformMenu($site->bottomMenu(), $site),
  'footerMenu' => transformMenu($site->footerMenu(), $site),
];

header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
