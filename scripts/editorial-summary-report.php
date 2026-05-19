<?php

/**
 * @file
 * Read-only report: nodes with empty body summary (editorial field).
 *
 * Usage:
 *   ddev drush php:script scripts/editorial-summary-report.php
 *   ddev drush php:script scripts/editorial-summary-report.php -- --format=csv
 */

use Drupal\node\Entity\Node;

$bundles = ['stallion', 'container_home', 'article'];
$format = 'markdown';

foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
  if ($arg === '--format=csv') {
    $format = 'csv';
  }
}

$path_alias_manager = \Drupal::service('path_alias.manager');
$rows = [];

foreach ($bundles as $bundle) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundle)
    ->accessCheck(FALSE)
    ->execute();

  foreach (Node::loadMultiple($nids) as $node) {
    if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
      continue;
    }
    $summary = trim((string) $node->get('body')->summary);
    if ($summary !== '') {
      continue;
    }

    $nid = (int) $node->id();
    $rows[] = [
      'nid' => $nid,
      'bundle' => $bundle,
      'title' => $node->label(),
      'status' => $node->isPublished() ? 'published' : 'unpublished',
      'updated' => date('Y-m-d H:i:s', (int) $node->getChangedTime()),
      'alias' => $path_alias_manager->getAliasByPath('/node/' . $nid),
    ];
  }
}

usort($rows, static fn(array $a, array $b): int => [$a['bundle'], $a['nid']] <=> [$b['bundle'], $b['nid']]);

if ($format === 'csv') {
  print "nid,bundle,title,status,updated,alias\n";
  foreach ($rows as $row) {
    print implode(',', [
      $row['nid'],
      $row['bundle'],
      '"' . str_replace('"', '""', $row['title']) . '"',
      $row['status'],
      $row['updated'],
      '"' . str_replace('"', '""', $row['alias']) . '"',
    ]) . "\n";
  }
  exit(0);
}

print "# Editorial summary report\n\n";
print 'Generated: ' . date('c') . "\n\n";
print 'Nodes with **empty body summary** (body may still have value). Read-only; does not modify nodes.' . "\n\n";
print '| nid | bundle | title | status | updated | alias |' . "\n";
print '|-----|--------|-------|--------|---------|-------|' . "\n";
foreach ($rows as $row) {
  print sprintf(
    "| %d | %s | %s | %s | %s | %s |\n",
    $row['nid'],
    $row['bundle'],
    str_replace('|', '\\|', $row['title']),
    $row['status'],
    $row['updated'],
    str_replace('|', '\\|', $row['alias']),
  );
}
print "\n**Total:** " . count($rows) . "\n";
