<?php

/**
 * @file
 * One-off editorial audit for stallion nodes. Run via:
 * ddev drush php:script scripts/editorial-audit.php
 */

use Drupal\node\Entity\Node;

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'stallion')
  ->accessCheck(FALSE)
  ->execute();

$issues = [
  'empty_summary' => [],
  'missing_main_image' => [],
  'broken_alias' => [],
  'duplicate_titles' => [],
  'unpublished' => [],
  'empty_body' => [],
  'placeholder_body' => [],
  'invalid_featured' => [],
  'moderation_mismatch' => [],
  'missing_meta_desc_published' => [],
];

$title_map = [];
$placeholder_patterns = [
  '/lorem ipsum/i',
  '/placeholder/i',
  '/\btodo\b/i',
  '/\btbd\b/i',
  '/coming soon/i',
];

$path_alias_manager = \Drupal::service('path_alias.manager');

foreach (Node::loadMultiple($nids) as $node) {
  $nid = (int) $node->id();
  $title = $node->label();
  $title_key = mb_strtolower(trim($title));
  $title_map[$title_key][] = $nid;

  if (!$node->isPublished()) {
    $issues['unpublished'][] = $nid;
  }

  $body = $node->get('body');
  $summary = (string) ($body->summary ?? '');
  if (trim($summary) === '') {
    $issues['empty_summary'][] = $nid;
  }

  $body_value = (string) ($body->value ?? '');
  if (trim(strip_tags($body_value)) === '') {
    $issues['empty_body'][] = $nid;
  }
  else {
    foreach ($placeholder_patterns as $pattern) {
      if (preg_match($pattern, $body_value) || preg_match($pattern, $summary)) {
        $issues['placeholder_body'][] = $nid;
        break;
      }
    }
  }

  if ($node->get('field_main_image')->isEmpty()) {
    $issues['missing_main_image'][] = $nid;
  }

  $alias = $path_alias_manager->getAliasByPath('/node/' . $nid);
  if ($alias === '/node/' . $nid || $alias === '') {
    $issues['broken_alias'][] = $nid;
  }

  $featured = (bool) $node->get('field_featured')->value;
  if ($featured && !$node->isPublished()) {
    $issues['invalid_featured'][] = $nid;
  }

  if ($node->hasField('moderation_state') && !$node->get('moderation_state')->isEmpty()) {
    $mod = $node->get('moderation_state')->value;
    if ($node->isPublished() && $mod !== 'published') {
      $issues['moderation_mismatch'][] = $nid;
    }
    if (!$node->isPublished() && $mod === 'published') {
      $issues['moderation_mismatch'][] = $nid;
    }
  }

  if ($node->isPublished()) {
    $has_custom_desc = FALSE;
    if ($node->hasField('field_metatag') && !$node->get('field_metatag')->isEmpty()) {
      $serialized = (string) ($node->get('field_metatag')->value ?? '');
      if (str_contains($serialized, 'description')) {
        $has_custom_desc = TRUE;
      }
    }
    if (trim($summary) === '' && !$has_custom_desc) {
      $issues['missing_meta_desc_published'][] = $nid;
    }
  }
}

foreach ($title_map as $key => $ids) {
  if (count($ids) > 1) {
    $issues['duplicate_titles'][$key] = $ids;
  }
}

$status_counts = \Drupal::database()->query("
  SELECT fs.field_status_value AS status, COUNT(*) AS cnt
  FROM {node_field_data} nfd
  LEFT JOIN {node__field_status} fs ON fs.entity_id = nfd.nid AND fs.deleted = 0
  WHERE nfd.type = 'stallion'
  GROUP BY fs.field_status_value
")->fetchAllKeyed();

$featured_counts = \Drupal::database()->query("
  SELECT ff.field_featured_value AS featured, COUNT(*) AS cnt
  FROM {node_field_data} nfd
  LEFT JOIN {node__field_featured} ff ON ff.entity_id = nfd.nid AND ff.deleted = 0
  WHERE nfd.type = 'stallion' AND nfd.status = 1
  GROUP BY ff.field_featured_value
")->fetchAllKeyed();

print json_encode([
  'total' => count($nids),
  'published' => count($nids) - count($issues['unpublished']),
  'unpublished' => count($issues['unpublished']),
  'field_status_distribution' => $status_counts,
  'featured_published_distribution' => $featured_counts,
  'issue_counts' => [
    'empty_summary' => count($issues['empty_summary']),
    'missing_main_image' => count($issues['missing_main_image']),
    'broken_alias' => count($issues['broken_alias']),
    'duplicate_title_groups' => count($issues['duplicate_titles']),
    'empty_body' => count($issues['empty_body']),
    'placeholder_body' => count($issues['placeholder_body']),
    'invalid_featured' => count($issues['invalid_featured']),
    'moderation_mismatch' => count($issues['moderation_mismatch']),
    'missing_meta_desc_published' => count($issues['missing_meta_desc_published']),
  ],
  'issue_nids' => [
    'empty_summary' => $issues['empty_summary'],
    'missing_main_image' => $issues['missing_main_image'],
    'broken_alias' => $issues['broken_alias'],
    'unpublished' => $issues['unpublished'],
    'empty_body' => $issues['empty_body'],
    'placeholder_body' => $issues['placeholder_body'],
    'invalid_featured' => $issues['invalid_featured'],
    'moderation_mismatch' => $issues['moderation_mismatch'],
    'missing_meta_desc_published' => $issues['missing_meta_desc_published'],
    'duplicate_titles' => $issues['duplicate_titles'],
  ],
], JSON_PRETTY_PRINT);
