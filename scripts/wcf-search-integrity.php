<?php

/**
 * @file
 * Search API tracker integrity check and remediation.
 *
 * Detects datasource/tracker drift (orphaned items) and rebuilds tracking when
 * needed. Use after migration, failed indexing, or `search-api:reset-tracker`
 * when items were previously removed by delete_on_fail.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-search-integrity.php
 *   ddev drush php:script scripts/wcf-search-integrity.php -- --apply
 *   ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
 *   ddev drush php:script scripts/wcf-search-integrity.php -- --cron
 */

declare(strict_types=1);

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$reindex = in_array('--reindex', $_SERVER['argv'] ?? [], TRUE);
$cron = in_array('--cron', $_SERVER['argv'] ?? [], TRUE);
$index_id = 'stallion_content';

$logger = \Drupal::logger('wcf_search_integrity');

$index = Index::load($index_id);
if (!$index) {
  $message = "Search index '$index_id' not found.";
  $logger->error($message);
  if ($cron) {
    print json_encode(['status' => 'error', 'message' => $message], JSON_PRETTY_PRINT) . "\n";
    return;
  }
  throw new \RuntimeException($message);
}

$database = \Drupal::database();
$expected = [];
$datasource_reports = [];

foreach ($index->getDatasources() as $datasource_id => $datasource) {
  $plugin_id = $datasource->getPluginId();
  $raw_ids = $datasource->getItemIds() ?? [];
  $datasource_expected = [];
  foreach ($raw_ids as $raw_id) {
    $combined = Utility::createCombinedId($datasource_id, $raw_id);
    $expected[] = $combined;
    $datasource_expected[] = $combined;
  }
  $datasource_reports[$datasource_id] = [
    'plugin' => $plugin_id,
    'expected_items' => count($datasource_expected),
    'sample_ids' => array_slice($datasource_expected, 0, 5),
  ];
  if ($datasource_expected === []) {
    $datasource_reports[$datasource_id]['warning'] = 'Datasource returned zero item IDs — check bundle selection, publish state, and index dependencies.';
  }
}

$expected = array_values(array_unique($expected));

$tracked = $database->select('search_api_item', 'sai')
  ->fields('sai', ['item_id'])
  ->condition('index_id', $index_id)
  ->execute()
  ->fetchCol();

$missing_from_tracker = array_values(array_diff($expected, $tracked));
$orphan_tracker = array_values(array_diff($tracked, $expected));

$backend_count = 0;
if ($database->schema()->tableExists('search_api_db_stallion_content')) {
  $backend_count = (int) $database->select('search_api_db_stallion_content', 'b')
    ->countQuery()
    ->execute()
    ->fetchField();
}

$healthy = $missing_from_tracker === [] && $orphan_tracker === [] && count($tracked) === count($expected);

$report = [
  'index' => $index_id,
  'status' => $healthy ? 'ok' : 'drift',
  'expected_items' => count($expected),
  'tracked_items' => count($tracked),
  'backend_items' => $backend_count,
  'missing_from_tracker' => count($missing_from_tracker),
  'orphan_tracker_rows' => count($orphan_tracker),
  'missing_sample' => array_slice($missing_from_tracker, 0, 20),
  'orphan_sample' => array_slice($orphan_tracker, 0, 20),
  'datasources' => $datasource_reports,
  'apply' => $apply,
  'reindex' => $reindex,
  'cron' => $cron,
];

if ($healthy) {
  $logger->info('Search index @index integrity OK (@count items).', [
    '@index' => $index_id,
    '@count' => count($expected),
  ]);
}
else {
  $logger->warning('Search index @index drift: @missing missing from tracker, @orphan orphan tracker rows.', [
    '@index' => $index_id,
    '@missing' => count($missing_from_tracker),
    '@orphan' => count($orphan_tracker),
  ]);
}

if (!$apply) {
  $report['message'] = $healthy
    ? 'Integrity OK. Pass --apply to rebuild tracker when drift is detected.'
    : 'Drift detected. Pass --apply to rebuild tracker; add --reindex to index pending items.';
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  return;
}

if (!$healthy) {
  $index->rebuildTracker();
  \Drupal::service('search_api.index_task_manager')->addItemsAll($index);
  $report['tracker_rebuilt'] = TRUE;
}
else {
  $report['tracker_rebuilt'] = FALSE;
}

$tracked_after = (int) $database->select('search_api_item', 'sai')
  ->condition('index_id', $index_id)
  ->countQuery()
  ->execute()
  ->fetchField();
$report['tracked_items_after'] = $tracked_after;

if ($reindex) {
  $indexed_total = 0;
  $remaining = 1;
  while ($remaining > 0) {
    $batch = $index->indexItems(50);
    if ($batch === 0) {
      break;
    }
    $indexed_total += $batch;
    $remaining = $index->getTrackerInstance()->getTotalItemsCount() - $index->getTrackerInstance()->getIndexedItemsCount();
  }
  $report['items_indexed'] = $indexed_total;
  if ($database->schema()->tableExists('search_api_db_stallion_content')) {
    $report['backend_items_after'] = (int) $database->select('search_api_db_stallion_content', 'b')
      ->countQuery()
      ->execute()
      ->fetchField();
  }
}

$report['message'] = 'Integrity remediation complete.';
$logger->info('Search integrity remediation finished for @index (tracked after: @count).', [
  '@index' => $index_id,
  '@count' => $tracked_after,
]);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
