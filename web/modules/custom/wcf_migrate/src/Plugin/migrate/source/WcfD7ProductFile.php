<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\source;

use Drupal\Core\Site\Settings;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\wcf_migrate\Plugin\migrate\source\WcfD7Product;

/**
 * Unique image and document files referenced by legacy product rows.
 *
 * @MigrateSource(
 *   id = "wcf_d7_product_file",
 *   source_module = "product"
 * )
 */
class WcfD7ProductFile extends DrupalSqlBase {

  /**
   * Maps legacy gallery columns to D7 thumbnail slot numbers.
   */
  private const GALLERY_SLOT_MAP = [
    'product_img1' => 1,
    'product_img2' => 2,
    'product_img3' => 3,
    'product_img4' => 4,
  ];

  /**
   * D7 gallery derivative sizes (product_view.tpl.php uses 645 for lightbox).
   */
  private const GALLERY_SIZE_SUFFIXES = ['645', '263', '100'];

  /**
   * D7 main image derivative prefixes.
   */
  private const MAIN_IMAGE_PREFIXES = ['thumb_350_', 'thumb_405_', 'thumb_100_'];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $database = $this->getDatabase();
    $queries = [];
    $image_columns = array_merge(['product_img'], WcfD7Product::GALLERY_COLUMNS);
    foreach ($image_columns as $column) {
      $sub = $database->select('product', 'p');
      $sub->addField('p', $column, 'filename');
      $sub->addExpression("'image'", 'file_type');
      $sub->isNotNull($column);
      $sub->condition($column, '', '<>');
      $queries[] = $sub;
    }
    $pdf = $database->select('product', 'p');
    $pdf->addField('p', 'pdf_file', 'filename');
    $pdf->addExpression("'document'", 'file_type');
    $pdf->isNotNull('pdf_file');
    $pdf->condition('pdf_file', '', '<>');
    $queries[] = $pdf;

    $query = array_shift($queries);
    foreach ($queries as $sub) {
      $query->union($sub);
    }
    $outer = $database->select($query, 'u');
    $outer->distinct();
    $outer->fields('u', ['filename', 'file_type']);
    if (!empty($this->configuration['file_type'])) {
      $outer->condition('u.file_type', $this->configuration['file_type']);
    }
    return $outer;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'file_key' => $this->t('Stable file migration ID'),
      'filename' => $this->t('Legacy filename'),
      'file_type' => $this->t('image or document'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'filename' => [
        'type' => 'string',
        'alias' => 'u',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    $filename = trim((string) $row->getSourceProperty('filename'));
    $type = (string) $row->getSourceProperty('file_type');
    if ($filename === '' || !in_array($type, ['image', 'document'], TRUE)) {
      return FALSE;
    }

    $row->setSourceProperty('filename', $filename);

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_map = [
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'pdf' => 'application/pdf',
    ];
    $row->setSourceProperty('filemime', $mime_map[$extension] ?? 'application/octet-stream');

    $row->setSourceProperty('alt', $this->lookupAltText($filename, $type));

    $timestamp = $this->lookupTimestamp($filename);
    $row->setSourceProperty('timestamp', $timestamp);

    if ($type === 'document') {
      $relative = 'sites/all/modules/product/files/pdf_files/' . $filename;
      $row->setSourceProperty('source_relative_path', $relative);
      $base_path = Settings::get('migrate_file_public_path');
      if ($base_path) {
        $full_path = rtrim($base_path, '/') . '/' . $relative;
        if (!is_readable($full_path)) {
          return FALSE;
        }
        $row->setSourceProperty('source_full_path', $full_path);
      }
    }
    else {
      $resolved = $this->resolveReadableImagePath($filename);
      if ($resolved === NULL) {
        return FALSE;
      }
      [$relative, $full_path] = $resolved;
      $row->setSourceProperty('source_relative_path', $relative);
      if ($full_path !== '') {
        $row->setSourceProperty('source_full_path', $full_path);
      }
    }

    $row->setSourceProperty('uri', 'public://wcf_product/' . $filename);

    return TRUE;
  }

  /**
   * Resolves a readable legacy image path (bare, gallery slot, or main thumb).
   *
   * @return array{0: string, 1: string}|null
   *   Relative path and full path, or NULL when no readable file exists.
   */
  protected function resolveReadableImagePath(string $filename): ?array {
    $base_path = Settings::get('migrate_file_public_path');
    $dir = 'sites/all/modules/product/files/product_images/';
    $candidates = [];
    $slot = $this->lookupGallerySlot($filename);
    if ($slot !== NULL) {
      foreach (self::GALLERY_SIZE_SUFFIXES as $size) {
        $candidates[] = 'img' . $slot . '_' . $size . '_' . $filename;
      }
    }
    $candidates[] = $filename;
    foreach (self::MAIN_IMAGE_PREFIXES as $prefix) {
      $candidates[] = $prefix . $filename;
    }
    if ($slot === NULL) {
      for ($n = 1; $n <= 4; $n++) {
        foreach (self::GALLERY_SIZE_SUFFIXES as $size) {
          $candidates[] = 'img' . $n . '_' . $size . '_' . $filename;
        }
      }
    }

    if (!$base_path) {
      $relative = $dir . $candidates[0];
      return [$relative, ''];
    }

    $base_path = rtrim($base_path, '/');
    foreach (array_unique($candidates) as $candidate) {
      $relative = $dir . $candidate;
      $full_path = $base_path . '/' . $relative;
      if (is_readable($full_path)) {
        return [$relative, $full_path];
      }
    }
    return NULL;
  }

  /**
   * Returns the gallery slot (1–4) when the filename is stored on product_imgN.
   */
  protected function lookupGallerySlot(string $filename): ?int {
    $database = $this->getDatabase();
    foreach (self::GALLERY_SLOT_MAP as $column => $slot) {
      $match = $database->select('product', 'p')
        ->fields('p', ['id'])
        ->condition($column, $filename)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($match !== FALSE) {
        return $slot;
      }
    }
    return NULL;
  }

  /**
   * Uses the first matching product name as alt text for images.
   */
  protected function lookupAltText(string $filename, string $type): string {
    if ($type !== 'image') {
      return $filename;
    }
    $database = $this->getDatabase();
    foreach (array_merge(['product_img'], WcfD7Product::GALLERY_COLUMNS) as $column) {
      $name = $database->select('product', 'p')
        ->fields('p', ['product_name'])
        ->condition($column, $filename)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($name) {
        return (string) $name;
      }
    }
    return $filename;
  }

  /**
   * Uses modified_on from the first product referencing this file.
   */
  protected function lookupTimestamp(string $filename): int {
    $database = $this->getDatabase();
    $columns = array_merge(['product_img'], WcfD7Product::GALLERY_COLUMNS, ['pdf_file']);
    foreach ($columns as $column) {
      $modified = $database->select('product', 'p')
        ->fields('p', ['modified_on'])
        ->condition($column, $filename)
        ->orderBy('modified_on', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($modified) {
        return (int) $modified;
      }
    }
    return time();
  }

}
