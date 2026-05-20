<?php

/**
 * @file
 * Post-migrate mapping: D7 wcf_product.product_name → stallion field_display_name.
 *
 * Idempotent by default: sets field_display_name only when empty. Does not
 * change node titles (title_with_year remains on the node title field).
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-map-product-display-names.php
 *   ddev drush php:script scripts/wcf-map-product-display-names.php -- --force
 *
 * Options:
 *   --force  Overwrite existing field_display_name values (use with care).
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\wcf_migrate\Plugin\migrate\source\WcfD7Product;

$force = in_array('--force', $_SERVER['argv'] ?? [], TRUE);
$logger = \Drupal::logger('wcf_map_product_display_names');

$summary = [
  'status' => 'ok',
  'force' => $force,
  'legacy_rows_checked' => 0,
  'nodes_found' => 0,
  'display_names_set' => 0,
  'already_populated' => 0,
  'skipped_empty_name' => 0,
  'missing_nodes' => 0,
  'missing_field' => FALSE,
  'missing_node_ids' => [],
];

$sample = Node::load(320);
if (!$sample instanceof NodeInterface || !$sample->hasField('field_display_name')) {
  $message = 'Stallion bundle is missing field_display_name. Import field config first.';
  $logger->error($message);
  $summary['status'] = 'error';
  $summary['message'] = $message;
  $summary['missing_field'] = TRUE;
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(1);
}

try {
  $legacy = Database::getConnection('default', 'migrate');
}
catch (\Exception $e) {
  $message = 'Migrate database connection is not available: ' . $e->getMessage();
  $help = 'Configure web/sites/default/settings.migrate.php, import the D7 DB into DDEV database `legacy` '
    . '(./scripts/wcf-import-legacy-database.sh), then run: ddev drush cr';
  $logger->error($message . ' ' . $help);
  $summary['status'] = 'error';
  $summary['message'] = $message;
  $summary['help'] = $help;
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(1);
}

$rows = $legacy->select('product', 'p')
  ->fields('p', ['id', 'product_name'])
  ->execute()
  ->fetchAllAssoc('id');

$summary['legacy_rows_checked'] = count($rows);
$missing_node_ids = [];

foreach ($rows as $product_id => $row) {
  $nid = (int) $product_id;
  $display_name = WcfD7Product::normalizeDisplayName($row->product_name);

  if ($display_name === '') {
    $summary['skipped_empty_name']++;
    continue;
  }

  $node = Node::load($nid);
  if (!$node instanceof NodeInterface || $node->bundle() !== 'stallion') {
    $summary['missing_nodes']++;
    $missing_node_ids[] = $nid;
    continue;
  }

  $summary['nodes_found']++;

  if (!$node->get('field_display_name')->isEmpty() && !$force) {
    $summary['already_populated']++;
    continue;
  }

  $node->set('field_display_name', $display_name);
  $node->setNewRevision(FALSE);
  $node->save();
  $summary['display_names_set']++;
}

if (count($missing_node_ids) > 20) {
  $summary['missing_node_ids_sample'] = array_slice($missing_node_ids, 0, 20);
}
else {
  $summary['missing_node_ids'] = $missing_node_ids;
}

$logger->info('Product display name mapping complete. @summary', [
  '@summary' => json_encode([
    'legacy_rows_checked' => $summary['legacy_rows_checked'],
    'nodes_found' => $summary['nodes_found'],
    'display_names_set' => $summary['display_names_set'],
    'already_populated' => $summary['already_populated'],
    'skipped_empty_name' => $summary['skipped_empty_name'],
    'missing_nodes' => $summary['missing_nodes'],
  ]),
]);

print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
