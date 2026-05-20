<?php

/**
 * @file
 * Import legacy D7 showcase rows (wcf_showcase) into wcf_showcase_item nodes.
 *
 * Copies gallery images from the D7 showcase module directory (imgN_647_* derivatives)
 * into Media entities, then creates/updates nodes keyed by field_showcase_legacy_id.
 *
 * Prerequisites:
 *   - config imported (node type wcf_showcase_item, view wcf_showcase at /showcase)
 *   - D7 project available at ../drupal7-legacy with DDEV running
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-import-showcase.php
 *   ddev drush php:script scripts/wcf-import-showcase.php -- --dry-run
 */

declare(strict_types=1);

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$dry_run = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$project_root = dirname(__DIR__);
$d7_root = dirname($project_root) . '/drupal7-legacy';
$d7_images = getenv('WCF_D7_SHOWCASE_IMAGES') ?: $project_root . '/tmp/showcase-images';
if (!is_dir($d7_images)) {
  $fallback = $d7_root . '/sites/all/modules/showcase/files/showcase_images';
  if (is_dir($fallback)) {
    $d7_images = $fallback;
  }
}

if (!is_dir($d7_images)) {
  throw new \RuntimeException(
    "Showcase images not found. Copy D7 files to tmp/showcase-images or set WCF_D7_SHOWCASE_IMAGES. Tried: {$d7_images}"
  );
}

/**
 * Loads showcase rows from exported TSV (tmp/showcase-data.tsv).
 *
 * Export on host:
 *   cd ../drupal7-legacy && ddev mysql -N -B -e "SELECT id, title, HEX(description), ..."
 *
 * @return list<list<string>>
 */
function wcf_showcase_load_rows(string $tsv_path): array {
  if (!is_readable($tsv_path)) {
    return [];
  }
  $rows = [];
  $handle = fopen($tsv_path, 'r');
  if ($handle === FALSE) {
    return [];
  }
  while (($line = fgets($handle)) !== FALSE) {
    $line = rtrim($line, "\r\n");
    if ($line === '') {
      continue;
    }
    $rows[] = explode("\t", $line);
  }
  fclose($handle);
  return $rows;
}

$tsv_path = $project_root . '/tmp/showcase-data.tsv';
$raw = wcf_showcase_load_rows($tsv_path);
if ($raw === []) {
  throw new \RuntimeException(
    "No showcase data at {$tsv_path}. Export from D7 wcf_showcase first (see script docblock)."
  );
}

$file_system = \Drupal::service('file_system');
$destination_dir = 'public://showcase';
if (!$dry_run) {
  $file_system->prepareDirectory(
    $destination_dir,
    \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
  );
}

$image_columns = [
  'product_img1',
  'product_img2',
  'product_img3',
  'product_img4',
  'product_img5',
  'product_img6',
  'product_img7',
  'product_img8',
];

/**
 * Resolves a legacy derivative file path for one image slot.
 */
function wcf_showcase_normalize_filename(string $filename): string {
  $filename = trim($filename);
  if ($filename === '' || strtoupper($filename) === 'NULL') {
    return '';
  }
  return $filename;
}

/**
 * Resolves a legacy derivative file path for one image slot.
 */
function wcf_showcase_resolve_image(string $images_dir, int $slot, string $filename): ?string {
  $filename = wcf_showcase_normalize_filename($filename);
  if ($filename === '') {
    return NULL;
  }
  $candidates = [
    sprintf('img%d_647_%s', $slot, $filename),
    sprintf('img%d_278_%s', $slot, $filename),
    $filename,
  ];
  foreach ($candidates as $candidate) {
    $path = $images_dir . '/' . $candidate;
    if (is_readable($path)) {
      return $path;
    }
  }
  return NULL;
}

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($raw as $index => $parts) {
  [
    $legacy_id,
    $title,
    $description_hex,
    $status,
    $created_on,
    $modified_on,
    $img1,
    $img2,
    $img3,
    $img4,
    $img5,
    $img6,
    $img7,
    $img8,
  ] = array_pad($parts, 14, '');

  $legacy_id = (int) $legacy_id;
  $description = $description_hex !== '' ? (string) hex2bin($description_hex) : '';
  $filenames = [$img1, $img2, $img3, $img4, $img5, $img6, $img7, $img8];

  $existing_ids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'wcf_showcase_item')
    ->condition('field_showcase_legacy_id', $legacy_id)
    ->range(0, 1)
    ->execute();

  $node = $existing_ids ? Node::load((int) reset($existing_ids)) : NULL;
  $action = $node ? 'update' : 'create';

  print ($dry_run ? '[dry-run] ' : '') . "{$action} legacy #{$legacy_id}: {$title}\n";

  $media_ids = [];
  foreach ($image_columns as $slot => $column) {
    $filename = wcf_showcase_normalize_filename($filenames[$slot] ?? '');
    if ($filename === '') {
      continue;
    }
    $source = wcf_showcase_resolve_image($d7_images, $slot + 1, $filename);
    if ($source === NULL) {
      print "  - missing file for {$column}: {$filename}\n";
      continue;
    }
    if ($dry_run) {
      print "  - would import: " . basename($source) . "\n";
      continue;
    }

    $safe_name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($source)) ?? 'image.jpg';
    $uri = $file_system->copy(
      $source,
      $destination_dir . '/' . $legacy_id . '_' . ($slot + 1) . '_' . $safe_name,
      \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE
    );
    if ($uri === FALSE) {
      print "  - failed copy: {$source}\n";
      continue;
    }

    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $title . ' — image ' . ($slot + 1),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $title,
      ],
    ]);
    $media->setPublished(TRUE);
    $media->save();
    $media_ids[] = ['target_id' => $media->id()];
    print "  - media {$media->id()} from " . basename($source) . "\n";
  }

  if ($dry_run) {
    continue;
  }

  $description = str_replace("\\'", "'", (string) $description);
  $values = [
    'type' => 'wcf_showcase_item',
    'title' => $title,
    'status' => 1,
    'created' => (int) $created_on,
    'changed' => (int) $modified_on,
    'body' => [
      'value' => $description,
      'format' => 'basic_html',
    ],
    'field_showcase_legacy_id' => $legacy_id,
    'field_showcase_weight' => $legacy_id,
  ];
  if ($media_ids !== []) {
    $values['field_gallery'] = $media_ids;
  }

  if ($node) {
    foreach ($values as $key => $value) {
      $node->set($key, $value);
    }
    $node->save();
    $updated++;
  }
  else {
    $node = Node::create($values);
    $node->save();
    $created++;
  }
}

print "\nDone. created={$created} updated={$updated} skipped={$skipped}" . ($dry_run ? ' (dry-run)' : '') . "\n";
