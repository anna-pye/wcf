<?php

/**
 * @file
 * Populate empty body summaries from trimmed body text (editorial remediation).
 *
 * Only updates nodes where the summary field is empty. Manual summaries are
 * never overwritten. Revisions are preserved (default revision updated only).
 *
 * Usage:
 *   ddev drush php:script scripts/remediate-empty-summaries.php
 *   ddev drush php:script scripts/remediate-empty-summaries.php -- --apply
 *   ddev drush php:script scripts/remediate-empty-summaries.php -- --apply --bundle=stallion
 *   ddev drush php:script scripts/remediate-empty-summaries.php -- --apply --trim=300
 *   ddev drush php:script scripts/remediate-empty-summaries.php -- --apply --limit=25
 *   ddev drush php:script scripts/remediate-empty-summaries.php -- --apply --published-only
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$published_only = in_array('--published-only', $_SERVER['argv'] ?? [], TRUE);
$bundle = 'stallion';
$trim_length = 300;
$limit = 0;

foreach ($_SERVER['argv'] ?? [] as $arg) {
  if (str_starts_with($arg, '--bundle=')) {
    $bundle = substr($arg, 9);
  }
  if (str_starts_with($arg, '--trim=')) {
    $trim_length = max(80, (int) substr($arg, 7));
  }
  if (str_starts_with($arg, '--limit=')) {
    $limit = max(1, (int) substr($arg, 8));
  }
}

$query = \Drupal::entityQuery('node')
  ->accessCheck(FALSE)
  ->condition('type', $bundle);

if ($published_only) {
  $query->condition('status', 1);
}

$nids = array_values($query->execute());
$stats = [
  'bundle' => $bundle,
  'trim_length' => $trim_length,
  'published_only' => $published_only,
  'limit' => $limit > 0 ? $limit : NULL,
  'scanned' => 0,
  'empty_summary' => 0,
  'skipped_no_body' => 0,
  'updated' => 0,
  'apply' => $apply,
  'sample_updates' => [],
];

foreach (Node::loadMultiple($nids) as $node) {
  if (!$node instanceof NodeInterface) {
    continue;
  }
  $stats['scanned']++;

  $body = $node->get('body');
  if ($body->isEmpty()) {
    $stats['skipped_no_body']++;
    continue;
  }

  $summary = trim((string) ($body->summary ?? ''));
  if ($summary !== '') {
    continue;
  }

  $stats['empty_summary']++;
  $raw_value = (string) ($body->value ?? '');
  $plain = trim(strip_tags($raw_value));
  if ($plain === '') {
    $stats['skipped_no_body']++;
    continue;
  }

  $format = $body->format ?: filter_default_format();
  $generated = text_summary($plain, $format, $trim_length);
  $generated = trim(strip_tags($generated));
  if ($generated === '') {
    $stats['skipped_no_body']++;
    continue;
  }

  if (mb_strlen($generated) > $trim_length) {
    $generated = mb_substr($generated, 0, $trim_length);
    $generated = preg_replace('/\s+\S*$/u', '', $generated) ?: $generated;
    $generated = rtrim($generated, ".,;:!?") . '…';
  }

  if (count($stats['sample_updates']) < 5) {
    $stats['sample_updates'][] = [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'summary_preview' => mb_substr($generated, 0, 120) . (mb_strlen($generated) > 120 ? '…' : ''),
    ];
  }

  if (!$apply) {
    if ($limit > 0 && $stats['empty_summary'] >= $limit) {
      break;
    }
    continue;
  }

  if ($limit > 0 && $stats['updated'] >= $limit) {
    break;
  }

  $body->summary = $generated;
  $node->setNewRevision(FALSE);
  $node->save();
  $stats['updated']++;
}

if (!$apply) {
  $stats['message'] = 'Dry run. Pass --apply to write summaries for empty-summary nodes only.';
}
else {
  $stats['message'] = 'Remediation complete. Reindex search and clear caches if summaries affect discovery.';
}

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
