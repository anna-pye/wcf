<?php

/**
 * @file
 * Read-only report: duplicate node titles within each bundle.
 *
 * Usage:
 *   ddev drush php:script scripts/duplicate-title-report.php
 *   ddev drush php:script scripts/duplicate-title-report.php -- --format=csv
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
$title_map = [];

foreach ($bundles as $bundle) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundle)
    ->accessCheck(FALSE)
    ->execute();

  foreach (Node::loadMultiple($nids) as $node) {
    $key = $bundle . '::' . mb_strtolower(trim($node->label()));
    $title_map[$key][] = $node;
  }
}

$rows = [];
foreach ($title_map as $nodes) {
  if (count($nodes) < 2) {
    continue;
  }
  foreach ($nodes as $node) {
    $nid = (int) $node->id();
    $rows[] = [
      'nid' => $nid,
      'bundle' => $node->bundle(),
      'title' => $node->label(),
      'status' => $node->isPublished() ? 'published' : 'unpublished',
      'updated' => date('Y-m-d H:i:s', (int) $node->getChangedTime()),
      'alias' => $path_alias_manager->getAliasByPath('/node/' . $nid),
      'duplicate_group' => mb_strtolower(trim($node->label())),
    ];
  }
}

usort($rows, static fn(array $a, array $b): int => [$a['bundle'], $a['duplicate_group'], $a['nid']] <=> [$b['bundle'], $b['duplicate_group'], $b['nid']]);

if ($format === 'csv') {
  print "nid,bundle,title,status,updated,alias,duplicate_group\n";
  foreach ($rows as $row) {
    print implode(',', [
      $row['nid'],
      $row['bundle'],
      '"' . str_replace('"', '""', $row['title']) . '"',
      $row['status'],
      $row['updated'],
      '"' . str_replace('"', '""', $row['alias']) . '"',
      '"' . str_replace('"', '""', $row['duplicate_group']) . '"',
    ]) . "\n";
  }
  exit(0);
}

print "# Duplicate title report\n\n";
print 'Generated: ' . date('c') . "\n\n";
print 'Read-only. Does **not** rename titles.' . "\n\n";
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
print "\n**Rows in duplicate groups:** " . count($rows) . "\n";
