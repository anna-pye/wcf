<?php

/**
 * @file
 * Media governance audit for stallion main images and gallery.
 * ddev drush php:script scripts/media-audit.php
 */

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'stallion')
  ->accessCheck(FALSE)
  ->execute();

$issues = [
  'missing_main_image' => [],
  'broken_media_ref' => [],
  'missing_alt' => [],
  'low_resolution' => [],
  'duplicate_file_hash' => [],
  'oversized_bytes' => [],
];

$file_hashes = [];
$LOW_RES_THRESHOLD = 400;
$OVERSIZE_BYTES = 2 * 1024 * 1024;

foreach (Node::loadMultiple($nids) as $node) {
  $nid = (int) $node->id();

  if ($node->get('field_main_image')->isEmpty()) {
    $issues['missing_main_image'][] = $nid;
    continue;
  }

  $media = $node->get('field_main_image')->entity;
  if (!$media instanceof Media) {
    $issues['broken_media_ref'][] = $nid;
    continue;
  }

  $image_field = $media->get('field_media_image');
  if ($image_field->isEmpty()) {
    $issues['broken_media_ref'][] = $nid;
    continue;
  }

  $file = $image_field->entity;
  if (!$file) {
    $issues['broken_media_ref'][] = $nid;
    continue;
  }

  $uri = $file->getFileUri();
  if (!file_exists($uri)) {
    $issues['broken_media_ref'][] = $nid;
    continue;
  }

  $alt = (string) ($image_field->alt ?? '');
  if (trim($alt) === '') {
    $issues['missing_alt'][] = ['nid' => $nid, 'mid' => (int) $media->id()];
  }

  $size = (int) @filesize($uri);
  if ($size > $OVERSIZE_BYTES) {
    $issues['oversized_bytes'][] = [
      'nid' => $nid,
      'mid' => (int) $media->id(),
      'bytes' => $size,
      'filename' => $file->getFilename(),
    ];
  }

  $image_info = @getimagesize($uri);
  if ($image_info && ($image_info[0] < $LOW_RES_THRESHOLD || $image_info[1] < $LOW_RES_THRESHOLD)) {
    $issues['low_resolution'][] = [
      'nid' => $nid,
      'mid' => (int) $media->id(),
      'width' => $image_info[0],
      'height' => $image_info[1],
      'filename' => $file->getFilename(),
    ];
  }

  $hash = hash_file('sha256', $uri);
  $file_hashes[$hash] = $file_hashes[$hash] ?? [];
  $file_hashes[$hash][] = ['nid' => $nid, 'mid' => (int) $media->id(), 'filename' => $file->getFilename()];
}

foreach ($file_hashes as $hash => $entries) {
  if (count($entries) > 1) {
    $issues['duplicate_file_hash'][] = $entries;
  }
}

// Thumb-only detection: filenames containing thumb_ prefix on main image.
$thumb_fallback = [];
foreach (Node::loadMultiple($nids) as $node) {
  if ($node->get('field_main_image')->isEmpty()) {
    continue;
  }
  $media = $node->get('field_main_image')->entity;
  if (!$media) {
    continue;
  }
  $file = $media->get('field_media_image')->entity;
  if ($file && preg_match('/thumb_\d+_/i', $file->getFilename())) {
    $thumb_fallback[] = [
      'nid' => (int) $node->id(),
      'mid' => (int) $media->id(),
      'filename' => $file->getFilename(),
    ];
  }
}

print json_encode([
  'stallion_count' => count($nids),
  'issue_counts' => array_map('count', array_merge($issues, ['thumb_fallback_main' => $thumb_fallback])),
  'thumb_fallback_main' => $thumb_fallback,
  'issues' => $issues,
], JSON_PRETTY_PRINT);
