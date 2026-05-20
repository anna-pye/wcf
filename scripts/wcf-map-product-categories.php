<?php

/**
 * @file
 * Post-migrate mapping: D7 wcf_product.category_id → stallion field_category.
 *
 * Idempotent by default: sets field_category only when empty. Does not re-import
 * stallions or alter migration maps.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-ensure-legacy-categories.php
 *   ddev drush php:script scripts/wcf-map-product-categories.php
 *   ddev drush php:script scripts/wcf-map-product-categories.php -- --force
 *
 * Options:
 *   --force  Overwrite existing field_category values (use with care).
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

$force = in_array('--force', $_SERVER['argv'] ?? [], TRUE);

/** @var array<int, string> D7 category_id → governed D11 term name */
$category_map = [
  1 => 'Foals',
  2 => 'For Sale',
  4 => 'Broodmares',
  5 => 'Show mares',
  6 => 'Stallions',
  7 => 'ASB Stallions',
];

$required_terms = array_values(array_unique($category_map));
$logger = \Drupal::logger('wcf_map_product_categories');

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$term_ids_by_name = [];
$missing_terms = [];

$tids = $term_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'categories')
  ->execute();

/** @var \Drupal\taxonomy\TermInterface[] $terms */
$terms = $term_storage->loadMultiple($tids);
foreach ($terms as $term) {
  if ($term instanceof TermInterface) {
    $term_ids_by_name[mb_strtolower(trim($term->label()))] = (int) $term->id();
  }
}

foreach ($required_terms as $name) {
  $key = mb_strtolower(trim($name));
  if (!isset($term_ids_by_name[$key])) {
    $missing_terms[] = $name;
  }
}

$summary = [
  'status' => 'ok',
  'force' => $force,
  'legacy_rows_checked' => 0,
  'nodes_found' => 0,
  'categories_set' => 0,
  'categories_corrected' => 0,
  'already_correct' => 0,
  'missing_nodes' => 0,
  'missing_terms' => $missing_terms,
  'unmapped_category_ids' => (object) [],
  'skipped_no_category' => 0,
  'missing_node_ids' => [],
  'unmapped_rows' => 0,
];

$missing_node_ids = [];

if ($missing_terms !== []) {
  $message = 'Required governed category terms are missing: ' . implode(', ', $missing_terms)
    . '. Run scripts/wcf-ensure-legacy-categories.php first.';
  $logger->error($message);
  $summary['status'] = 'error';
  $summary['message'] = $message;
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(1);
}

$tid_by_d7_category = [];
foreach ($category_map as $d7_id => $term_name) {
  $tid_by_d7_category[$d7_id] = $term_ids_by_name[mb_strtolower(trim($term_name))];
}

try {
  $legacy = Database::getConnection('default', 'migrate');
}
catch (\Exception $e) {
  $message = 'Migrate database connection is not available: ' . $e->getMessage();
  $logger->error($message);
  $summary['status'] = 'error';
  $summary['message'] = $message;
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(1);
}

$rows = $legacy->select('product', 'p')
  ->fields('p', ['id', 'category_id'])
  ->execute()
  ->fetchAllAssoc('id');

$summary['legacy_rows_checked'] = count($rows);
$unmapped_counts = [];

foreach ($rows as $product_id => $row) {
  $nid = (int) $product_id;
  $category_id = (int) $row->category_id;

  if ($category_id === 0) {
    $summary['skipped_no_category']++;
    continue;
  }

  if (!isset($category_map[$category_id])) {
    $unmapped_counts[$category_id] = ($unmapped_counts[$category_id] ?? 0) + 1;
    $summary['unmapped_rows']++;
    continue;
  }

  $node = Node::load($nid);
  if (!$node instanceof NodeInterface || $node->bundle() !== 'stallion') {
    $summary['missing_nodes']++;
    $missing_node_ids[] = $nid;
    continue;
  }

  $summary['nodes_found']++;

  if (!$node->hasField('field_category')) {
    $logger->error('Stallion node @nid has no field_category field.', ['@nid' => $nid]);
    continue;
  }

  $expected_tid = $tid_by_d7_category[$category_id];
  $current_tid = NULL;
  if (!$node->get('field_category')->isEmpty()) {
    $current_tid = (int) $node->get('field_category')->target_id;
    $current_term = $term_storage->load($current_tid);
    if (
      !$force
      && $current_term instanceof TermInterface
      && $current_term->bundle() === 'categories'
      && $current_tid === $expected_tid
    ) {
      $summary['already_correct']++;
      continue;
    }
  }

  $node->set('field_category', ['target_id' => $expected_tid]);
  $node->setNewRevision(FALSE);
  $node->save();

  if ($current_tid === NULL) {
    $summary['categories_set']++;
  }
  else {
    $summary['categories_corrected']++;
  }
}

if ($unmapped_counts !== []) {
  ksort($unmapped_counts);
  $unmapped_object = new \stdClass();
  foreach ($unmapped_counts as $category_id => $count) {
    $unmapped_object->{(string) $category_id} = $count;
  }
  $summary['unmapped_category_ids'] = $unmapped_object;
}

if (count($missing_node_ids) > 20) {
  $summary['missing_node_ids_sample'] = array_slice($missing_node_ids, 0, 20);
}
else {
  $summary['missing_node_ids'] = $missing_node_ids;
}

$logger->info('Product category mapping complete. @summary', [
  '@summary' => json_encode([
    'legacy_rows_checked' => $summary['legacy_rows_checked'],
    'nodes_found' => $summary['nodes_found'],
    'categories_set' => $summary['categories_set'],
    'categories_corrected' => $summary['categories_corrected'],
    'already_correct' => $summary['already_correct'],
    'missing_nodes' => is_int($summary['missing_nodes']) ? $summary['missing_nodes'] : count($summary['missing_node_ids'] ?? []),
    'skipped_no_category' => $summary['skipped_no_category'],
    'unmapped_rows' => $summary['unmapped_rows'],
  ]),
]);

print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($summary['unmapped_rows'] > 0) {
  exit(1);
}
