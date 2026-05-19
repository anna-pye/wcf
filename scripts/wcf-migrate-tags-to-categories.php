<?php

/**
 * @file
 * One-time migration: tags vocabulary/field_tags → categories/field_category.
 *
 * Run after importing config that adds field_category and categories vocabulary,
 * before removing field_tags config.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-migrate-tags-to-categories.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

$database = \Drupal::database();
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$report = [
  'terms_moved' => 0,
  'nodes_updated' => 0,
  'nodes_skipped_already_set' => 0,
  'errors' => [],
];

if ($database->schema()->tableExists('taxonomy_term_field_data')) {
  $report['terms_moved'] = (int) $database->update('taxonomy_term_field_data')
    ->fields(['vid' => 'categories'])
    ->condition('vid', 'tags')
    ->execute();
}

$bundles = ['stallion', 'container_home', 'article'];
foreach ($bundles as $bundle) {
  $nids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->execute();

  foreach (Node::loadMultiple($nids) as $node) {
    if (!$node instanceof NodeInterface) {
      continue;
    }
    if (!$node->hasField('field_tags') || !$node->hasField('field_category')) {
      continue;
    }
    if ($node->get('field_tags')->isEmpty()) {
      continue;
    }
    if (!$node->get('field_category')->isEmpty()) {
      $report['nodes_skipped_already_set']++;
      continue;
    }

    $target_ids = [];
    foreach ($node->get('field_tags') as $item) {
      $tid = (int) $item->target_id;
      if ($tid <= 0) {
        continue;
      }
      $term = $term_storage->load($tid);
      if ($term instanceof TermInterface && $term->bundle() === 'categories') {
        $target_ids[] = ['target_id' => $tid];
      }
    }
    if ($target_ids === []) {
      continue;
    }

    $node->set('field_category', $target_ids);
    $node->setNewRevision(FALSE);
    $node->save();
    $report['nodes_updated']++;
  }
}

print json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
