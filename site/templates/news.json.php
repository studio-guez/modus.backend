<?php

/** @var Kirby\Cms\App $kirby */
/** @var Kirby\Cms\Site $site */
/** @var Kirby\Cms\Page $page */

$response = [
  'actualite1title' => $site->actualite1title()->value() ?: null,
  'actualite1link'  => $site->actualite1link()->value() ?: null,
  'actualite1color' => $site->actualite1color()->value() ?: 'teal',
  'actualite2title' => $site->actualite2title()->value() ?: null,
  'actualite2link'  => $site->actualite2link()->value() ?: null,
  'actualite2color' => $site->actualite2color()->value() ?: 'sage',
];

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
