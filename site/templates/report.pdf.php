<?php

/**
 * Report PDF Template
 * Generates a PDF version of the report using mPDF
 * Uses two-phase rendering to build TOC with page numbers
 */

require_once '_utils/Utils.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Mpdf\Mpdf;

/** @global Kirby\Cms\App $kirby */
/** @global Kirby\Cms\Site $site */
/** @global Kirby\Cms\Page $page */

// Create temp directory for mPDF in Kirby's cache folder
$tempDir = $kirby->root('cache') . '/mpdf';
if (!is_dir($tempDir)) {
  mkdir($tempDir, 0755, true);
}

// Font directory
$fontDir = __DIR__ . '/../../assets/fonts/';

// mPDF configuration
$mpdfConfig = [
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_left' => 22,
  'margin_right' => 22,
  'margin_top' => 22,
  'margin_bottom' => 26,
  'margin_header' => 0,
  'margin_footer' => 18,
  'default_font' => 'wallop',
  'tempDir' => $tempDir,
  'fontDir' => [$fontDir],
  'fontdata' => [
    'wallop' => [
      'R' => 'Wallop-Regular.ttf',
      'I' => 'Wallop-RegularItalic.ttf',
    ],
    'wallop-thin' => [
      'R' => 'Wallop-Thin.ttf',
      'I' => 'Wallop-ThinItalic.ttf',
    ],
    'wallop-light' => [
      'R' => 'Wallop-Light.ttf',
      'I' => 'Wallop-LightItalic.ttf',
    ],
    'wallop-medium' => [
      'R' => 'Wallop-Medium.ttf',
      'I' => 'Wallop-MediumItalic.ttf',
    ],
    'wallop-semibold' => [
      'R' => 'Wallop-SemiBold.ttf',
      'I' => 'Wallop-SemiBoldItalic.ttf',
    ],
    'wallop-bold' => [
      'R' => 'Wallop-Bold.ttf',
      'I' => 'Wallop-BoldItalic.ttf',
    ],
    'wallop-heavy' => [
      'R' => 'Wallop-Heavy.ttf',
      'I' => 'Wallop-HeavyItalic.ttf',
    ],
  ],
];

// Get page data
$title = $page->title()->value();
$headerImage = $page->headerImage()->toFile();
$dateStart = $page->dateStart()->value();
$summary = $page->summary()->value();
$bibliography = $page->bibliography()->toStructure()->toArray();
$bodyBlocks = $page->body()->toBlocks();

// Format date
$dateFormatted = '';
if ($dateStart) {
  $date = new DateTime($dateStart);
  $dateFormatted = $date->format('F Y');
}

// Load CSS
$cssFile = __DIR__ . '/report-pdf.css';
$css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

/**
 * Render a block to HTML
 * @param object $block The block to render
 * @return string The HTML output
 */
function renderBlockToHtml($block): string
{
  $type = $block->type();
  $content = $block->content();
  $html = '';

  switch ($type) {
    case 'mdheading':
      $level = $content->level()->value() ?: 'h2';
      $text = $content->text()->value();

      if ($level === 'h2') {
        $html = '<h2 class="section-heading">' . $text . '</h2>';
      } else {
        $html = '<h3 class="section-subheading">' . $text . '</h3>';
      }
      break;

    case 'reportbody':
      $text = $content->text()->value();
      $html = '<div class="body-text">' . $text . '</div>';
      break;

    case 'mdreportimage':
      $imageFile = $block->image()->toFile();
      if ($imageFile) {
        $imagePath = $imageFile->root();
        $caption = $content->caption()->value();
        $alt = $content->alt()->value() ?: 'Image';
        $size = $content->size()->value() ?: 'large';

        // mPDF doesn't handle compound CSS selectors well, so use inline styles
        $widthMap = [
          'full' => '100%',
          'large' => '80%',
          'small' => '60%',
        ];
        $imgWidth = $widthMap[$size] ?? '100%';

        $html = '<div class="report-image">';
        $html .= '<img src="' . $imagePath . '" alt="' . htmlspecialchars($alt) . '" style="width: ' . $imgWidth . '; max-width: ' . $imgWidth . ';" />';
        if ($caption) {
          $html .= '<div class="image-caption">' . $caption . '</div>';
        }
        $html .= '</div>';
      }
      break;
  }

  return $html;
}

/**
 * Process blocks and render them to mPDF
 * In phase 1, also collects TOC entries with page numbers
 * 
 * @param Mpdf $mpdf The mPDF instance
 * @param iterable $blocks The blocks to process
 * @param bool $collectToc Whether to collect TOC entries (phase 1)
 * @param int $pageOffset Page offset for TOC calculation
 * @param array &$tocEntries Reference to TOC entries array (only used in phase 1)
 */
function processBlocks(Mpdf $mpdf, iterable $blocks, bool $collectToc = false, int $pageOffset = 0, array &$tocEntries = []): void
{
  foreach ($blocks as $block) {
    $type = $block->type();
    $content = $block->content();

    // Handle TOC collection for phase 1
    if ($collectToc) {
      if ($type === 'mdheading') {
        $level = $content->level()->value() ?: 'h2';
        if ($level === 'h2') {
          $text = $content->text()->value();
          $tocEntries[] = [
            'title' => strip_tags($text),
            'page' => $mpdf->page + $pageOffset,
          ];
        }
      } elseif ($type === 'reportbody') {
        $text = $content->text()->value();
        // Extract h2 for TOC before rendering
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/si', $text, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
          // Process text in segments, capturing page number before each h2
          $lastPos = 0;
          foreach ($matches[0] as $idx => $match) {
            $h2Pos = $match[1];
            $h2Tag = $match[0];
            $h2Text = strip_tags($matches[1][$idx][0]);

            // Write content before this h2
            $beforeH2 = substr($text, $lastPos, $h2Pos - $lastPos);
            if ($beforeH2) {
              $mpdf->WriteHTML('<div class="body-text">' . $beforeH2 . '</div>', \Mpdf\HTMLParserMode::HTML_BODY);
            }

            // Record page number for this h2
            $tocEntries[] = [
              'title' => $h2Text,
              'page' => $mpdf->page + $pageOffset,
            ];

            // Write the h2
            $mpdf->WriteHTML('<h2 class="section-heading">' . $matches[1][$idx][0] . '</h2>', \Mpdf\HTMLParserMode::HTML_BODY);

            $lastPos = $h2Pos + strlen($h2Tag);
          }

          // Write remaining content after last h2
          $remaining = substr($text, $lastPos);
          if ($remaining) {
            $mpdf->WriteHTML('<div class="body-text">' . $remaining . '</div>', \Mpdf\HTMLParserMode::HTML_BODY);
          }
          continue; // Skip normal rendering for this block
        }
      }
    }

    // Render the block
    $html = renderBlockToHtml($block);
    if ($html) {
      $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    }
  }
}

// ============================================================
// PHASE 1: Render body content to calculate page numbers
// ============================================================
$mpdfPhase1 = new Mpdf($mpdfConfig);
$mpdfPhase1->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// We need to account for cover page (1) + TOC page (1) = content starts at page 3
$pageOffset = 2;

$tocEntries = [];

// Start on first "content" page for simulation
$mpdfPhase1->AddPage();

// Process blocks and collect TOC entries
processBlocks($mpdfPhase1, $bodyBlocks, true, $pageOffset, $tocEntries);

// Add bibliography page number
if (!empty($bibliography)) {
  $mpdfPhase1->AddPage();
  $tocEntries[] = [
    'title' => 'Bibliographie',
    'page' => $mpdfPhase1->page + $pageOffset,
  ];
}

// Clean up phase 1
unset($mpdfPhase1);

// ============================================================
// PHASE 2: Build final PDF with correct page numbers
// ============================================================
$mpdf = new Mpdf($mpdfConfig);
$mpdf->SetTitle($title);
$mpdf->SetAuthor('Modus');
$mpdf->SetCreator('Modus - modus-ge.ch');
$mpdf->defaultfooterline = 0;
$mpdf->setFooter('<div class="pdf-footer"><span class="page-number">{PAGENO}</span></div>');
$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// === COVER PAGE ===
$html = '<div class="cover-page">';
$html .= '<div class="cover-overtitle"><span class="cover-author">Modus</span>';
if ($dateFormatted) {
  $html .= ' - <span class="cover-date">' . $dateFormatted . '</span>';
}
$html .= '</div>';
$html .= '<h1 class="cover-title">' . htmlspecialchars($title) . '</h1>';
if ($headerImage) {
  $imagePath = $headerImage->root();
  $html .= '<div class="cover-image">';
  $html .= '<img src="' . $imagePath . '" alt="' . htmlspecialchars($title) . '" />';
  $html .= '</div>';
}
$html .= '</div>';

$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

// === TABLE OF CONTENTS with page numbers ===
$mpdf->AddPage();
$tocHtml = '<div class="toc-page-container">';
$tocHtml .= '<h2 class="toc-title">Sommaire</h2>';
$tocHtml .= '<table class="toc-table" cellpadding="0" cellspacing="0">';

foreach ($tocEntries as $entry) {
  $tocHtml .= '<tr>';
  $tocHtml .= '<td class="toc-text">' . htmlspecialchars($entry['title']) . '</td>';
  $tocHtml .= '<td class="toc-page">' . $entry['page'] . '</td>';
  $tocHtml .= '</tr>';
}

$tocHtml .= '</table>';
$tocHtml .= '</div>';
$mpdf->WriteHTML($tocHtml, \Mpdf\HTMLParserMode::HTML_BODY);

// === BODY CONTENT ===
$mpdf->AddPage();

// Process blocks without TOC collection
processBlocks($mpdf, $bodyBlocks);

// === BIBLIOGRAPHY ===
if (!empty($bibliography)) {
  $mpdf->AddPage();
  $bibHtml = '<h2 class="section-heading">Bibliographie</h2>';
  $bibHtml .= '<div class="bibliography">';

  $index = 1;
  foreach ($bibliography as $ref) {
    $bibHtml .= '<p class="bibliography-item">';
    $bibHtml .= '<span class="bibliography-number">[' . $index . ']</span>';
    if (!empty($ref['text'])) {
      $bibHtml .= ' <span class="bibliography-text">' . htmlspecialchars($ref['text']) . '</span>';
    }
    if (!empty($ref['url'])) {
      $bibHtml .= ' <a href="' . htmlspecialchars($ref['url']) . '" class="bibliography-url">' . htmlspecialchars($ref['url']) . '</a>';
    }
    $bibHtml .= '</p>';
    $index++;
  }

  $bibHtml .= '</div>';
  $mpdf->WriteHTML($bibHtml, \Mpdf\HTMLParserMode::HTML_BODY);
}

// Generate filename and output
$filename = $page->slug() . '-rapport.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
