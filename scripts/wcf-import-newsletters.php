<?php

/**
 * @file
 * Import legacy D7 newsletter rows (wcf_news) into wcf_newsletter nodes + document media.
 *
 * Copies PDFs from D7 public://event_file/ into Media document entities.
 * Creates/updates nodes keyed by field_newsletter_legacy_id.
 * Public listing is View wcf_news at /news (intro in view header config).
 *
 * Prerequisites:
 *   - config imported (node type wcf_newsletter, view wcf_news at /news)
 *   - D7 project at ../drupal7-legacy with DDEV running
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-import-newsletters.php
 *   ddev drush php:script scripts/wcf-import-newsletters.php -- --dry-run
 */

declare(strict_types=1);

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
$dry_run = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$project_root = dirname(__DIR__);
$d7_root = dirname($project_root) . '/drupal7-legacy';
$d7_files = getenv('WCF_D7_NEWSLETTER_FILES') ?: $project_root . '/tmp/newsletter-files';
if (!is_dir($d7_files)) {
  $fallback = $d7_root . '/sites/default/files/event_file';
  if (is_dir($fallback)) {
    $d7_files = $fallback;
  }
}

if (!is_dir($d7_files)) {
  throw new \RuntimeException(
    "Newsletter PDFs not found. Copy D7 event_file into tmp/newsletter-files:\n" .
    "  mkdir -p tmp/newsletter-files && cp ../drupal7-legacy/sites/default/files/event_file/* tmp/newsletter-files/\n" .
    "Or set WCF_D7_NEWSLETTER_FILES to a readable directory."
  );
}

/**
 * Loads newsletter rows from TSV or exports from D7 MySQL.
 *
 * @return list<array{id: int, year: string, month: string, title: string, upload_file: string, status: int, timestamp: int}>
 */
function wcf_newsletter_load_rows(string $project_root, string $d7_root): array {
  $tsv_path = $project_root . '/tmp/newsletter-data.tsv';
  if (is_readable($tsv_path)) {
    $rows = [];
    $handle = fopen($tsv_path, 'r');
    if ($handle !== FALSE) {
      while (($line = fgets($handle)) !== FALSE) {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
          continue;
        }
        $parts = explode("\t", $line);
        $rows[] = [
          'id' => (int) ($parts[0] ?? 0),
          'year' => (string) ($parts[1] ?? ''),
          'month' => (string) ($parts[2] ?? ''),
          'title' => (string) ($parts[3] ?? ''),
          'upload_file' => (string) ($parts[4] ?? ''),
          'status' => (int) ($parts[5] ?? 0),
          'timestamp' => (int) ($parts[6] ?? 0),
        ];
      }
      fclose($handle);
    }
    if ($rows !== []) {
      return $rows;
    }
  }

  if (!is_dir($d7_root . '/.ddev')) {
    throw new \RuntimeException(
      "No data at {$tsv_path} and D7 project not found. Export:\n" .
      "  cd ../drupal7-legacy && ddev mysql -N -B -e \"SELECT id,year,month,title,upload_file,status,timestamp FROM wcf_news WHERE status=1 ORDER BY year DESC,id DESC\" > ../drupal11-upgrade/tmp/newsletter-data.tsv"
    );
  }

  $sql = 'SELECT id,year,month,title,upload_file,status,timestamp FROM wcf_news WHERE status=1 ORDER BY year DESC,id DESC';
  $cmd = sprintf(
    'cd %s && ddev mysql -N -B -e %s',
    escapeshellarg($d7_root),
    escapeshellarg($sql)
  );
  exec($cmd, $output, $exit_code);
  if ($exit_code !== 0 || $output === []) {
    throw new \RuntimeException("Failed to export wcf_news from D7. Run manually and save to tmp/newsletter-data.tsv");
  }

  $rows = [];
  foreach ($output as $line) {
    $parts = explode("\t", $line);
    $rows[] = [
      'id' => (int) ($parts[0] ?? 0),
      'year' => (string) ($parts[1] ?? ''),
      'month' => (string) ($parts[2] ?? ''),
      'title' => (string) ($parts[3] ?? ''),
      'upload_file' => (string) ($parts[4] ?? ''),
      'status' => (int) ($parts[5] ?? 0),
      'timestamp' => (int) ($parts[6] ?? 0),
    ];
  }
  return $rows;
}

$raw = wcf_newsletter_load_rows($project_root, $d7_root);
if ($raw === []) {
  throw new \RuntimeException('No active newsletter rows to import.');
}

$file_system = \Drupal::service('file_system');
$destination_dir = 'public://newsletters';
if (!$dry_run) {
  $file_system->prepareDirectory(
    $destination_dir,
    \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
  );
}

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($raw as $row) {
  $legacy_id = (int) $row['id'];
  $year = (int) $row['year'];
  $month = trim($row['month']);
  $title = trim($row['title']) !== '' ? trim($row['title']) : "{$month} {$year}";
  $upload_file = trim($row['upload_file']);
  $timestamp = (int) $row['timestamp'];

  if ($legacy_id <= 0 || $year <= 0 || $month === '' || $upload_file === '') {
    print "Skip legacy #{$legacy_id}: missing required data\n";
    $skipped++;
    continue;
  }

  $source = $d7_files . '/' . $upload_file;
  if (!is_readable($source)) {
    print "Skip legacy #{$legacy_id}: file not found {$upload_file}\n";
    $skipped++;
    continue;
  }

  $existing_ids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'wcf_newsletter')
    ->condition('field_newsletter_legacy_id', $legacy_id)
    ->range(0, 1)
    ->execute();

  $node = $existing_ids ? Node::load((int) reset($existing_ids)) : NULL;
  $action = $node ? 'update' : 'create';
  print ($dry_run ? '[dry-run] ' : '') . "{$action} legacy #{$legacy_id}: {$month} {$year}\n";

  if ($dry_run) {
    continue;
  }

  $safe_name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($upload_file)) ?? 'newsletter.pdf';
  $uri = $file_system->copy(
    $source,
    $destination_dir . '/' . $legacy_id . '_' . $safe_name,
    \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE
  );
  if ($uri === FALSE) {
    print "  - failed copy: {$source}\n";
    $skipped++;
    continue;
  }

  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();

  $media = Media::create([
    'bundle' => 'document',
    'name' => $month . ' ' . $year . ' newsletter',
    'field_media_document' => [
      'target_id' => $file->id(),
    ],
  ]);
  $media->setPublished(TRUE);
  $media->save();

  $values = [
    'type' => 'wcf_newsletter',
    'title' => $title,
    'status' => 1,
    'created' => $timestamp > 0 ? $timestamp : time(),
    'changed' => $timestamp > 0 ? $timestamp : time(),
    'field_newsletter_year' => $year,
    'field_newsletter_month' => $month,
    'field_newsletter_file' => ['target_id' => $media->id()],
    'field_newsletter_legacy_id' => $legacy_id,
    'field_newsletter_weight' => $legacy_id,
  ];

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
  print "  - node {$node->id()}, media {$media->id()}\n";
}

print "\nDone. created={$created} updated={$updated} skipped={$skipped}" . ($dry_run ? ' (dry-run)' : '') . "\n";
