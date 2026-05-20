<?php

/**
 * @file
 * Backfill stallion field_foal_year from legacy D7 wcf_product.year.
 *
 * Falls back to a 4-digit year at the start of the node title when legacy
 * data or migrate DB is unavailable.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-backfill-foal-years.php
 *   ddev drush php:script scripts/wcf-backfill-foal-years.php -- --force
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

$force = in_array('--force', $_SERVER['argv'] ?? [], TRUE);
$logger = \Drupal::logger('wcf_backfill_foal_years');

$summary = [
  'status' => 'ok',
  'force' => $force,
  'legacy_rows_checked' => 0,
  'from_legacy' => 0,
  'from_title' => 0,
  'already_set' => 0,
  'skipped' => 0,
  'missing_nodes' => 0,
  'missing_field' => FALSE,
];

$sample = Node::load(320);
if (!$sample instanceof NodeInterface || !$sample->hasField('field_foal_year')) {
  $message = 'Stallion bundle is missing field_foal_year. Import field config first.';
  $logger->error($message);
  $summary['status'] = 'error';
  $summary['message'] = $message;
  $summary['missing_field'] = TRUE;
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(1);
}

/**
 * Parses a 4-digit year from the start of a node title.
 */
$parse_title_year = static function (string $title): ?int {
  if (preg_match('/^\s*(\d{4})\b/', $title, $matches) === 1) {
    return (int) $matches[1];
  }
  return NULL;
};

$legacy_years = [];

try {
  $legacy = Database::getConnection('default', 'migrate');
  $rows = $legacy->select('product', 'p')
    ->fields('p', ['id', 'year', 'title_with_year', 'product_name'])
    ->execute()
    ->fetchAll();
  $summary['legacy_rows_checked'] = count($rows);
  foreach ($rows as $row) {
    $year = trim((string) $row->year);
    if ($year !== '' && ctype_digit($year) && strlen($year) === 4) {
      $legacy_years[(int) $row->id] = (int) $year;
      continue;
    }
    $title = trim((string) $row->title_with_year);
    if ($title === '') {
      $title = trim((string) $row->product_name);
    }
    $parsed = $parse_title_year($title);
    if ($parsed !== NULL) {
      $legacy_years[(int) $row->id] = $parsed;
    }
  }
}
catch (\Exception $e) {
  $summary['legacy_message'] = 'Migrate DB unavailable; using node titles only. ' . $e->getMessage();
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'stallion')
  ->execute();

foreach ($nids as $nid) {
  $nid = (int) $nid;
  $node = $storage->load($nid);
  if (!$node instanceof NodeInterface) {
    $summary['missing_nodes']++;
    continue;
  }

  if (!$node->get('field_foal_year')->isEmpty() && !$force) {
    $summary['already_set']++;
    continue;
  }

  $year = $legacy_years[$nid] ?? $parse_title_year($node->label());
  if ($year === NULL) {
    $summary['skipped']++;
    continue;
  }

  $node->set('field_foal_year', $year);
  $node->save();

  if (isset($legacy_years[$nid])) {
    $summary['from_legacy']++;
  }
  else {
    $summary['from_title']++;
  }
}

$logger->notice('Foal year backfill complete: @summary', [
  '@summary' => json_encode($summary, JSON_UNESCAPED_SLASHES),
]);

print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
