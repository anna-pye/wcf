<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Legacy WCF product rows (stallion / horse profiles).
 *
 * @MigrateSource(
 *   id = "wcf_d7_product",
 *   source_module = "product"
 * )
 */
class WcfD7Product extends DrupalSqlBase {

  /**
   * Gallery image columns on the legacy product table.
   */
  public const GALLERY_COLUMNS = [
    'product_img1',
    'product_img2',
    'product_img3',
    'product_img4',
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('product', 'p')
      ->fields('p');
    if (!empty($this->configuration['only_with_video'])) {
      $or = $query->orConditionGroup();
      $or->condition('youtube_iframe', '', '<>');
      $or->condition('youtube_iframe_2', '', '<>');
      $query->condition($or);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Product ID'),
      'product_name' => $this->t('Name'),
      'title_with_year' => $this->t('Title with year'),
      'alias' => $this->t('URL alias slug'),
      'description' => $this->t('Description'),
      'product_img' => $this->t('Main image filename'),
      'sold' => $this->t('Sold flag'),
      'status' => $this->t('Published flag'),
      'created_on' => $this->t('Created timestamp'),
      'modified_on' => $this->t('Modified timestamp'),
      'youtube_iframe' => $this->t('Primary YouTube embed'),
      'youtube_iframe_2' => $this->t('Secondary YouTube embed'),
      'pdf_file' => $this->t('PDF filename'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'p',
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

    $title = trim((string) $row->getSourceProperty('title_with_year'));
    if ($title === '') {
      $title = trim((string) $row->getSourceProperty('product_name'));
    }
    if ($title === '') {
      return FALSE;
    }
    $row->setSourceProperty('title', $title);

    $main = trim((string) $row->getSourceProperty('product_img'));
    $row->setSourceProperty('main_image_filename', $main);

    $gallery = [];
    foreach (self::GALLERY_COLUMNS as $column) {
      $filename = trim((string) $row->getSourceProperty($column));
      if ($filename === '' || $filename === $main) {
        continue;
      }
      $gallery[$filename] = ['filename' => $filename];
    }
    $row->setSourceProperty('gallery_files', array_values($gallery));

    $pdf = trim((string) $row->getSourceProperty('pdf_file'));
    $row->setSourceProperty('document_filename', $pdf);
    $row->setSourceProperty('document_files', $pdf !== '' ? [['filename' => $pdf]] : []);

    $iframe = trim((string) $row->getSourceProperty('youtube_iframe'));
    if ($iframe === '') {
      $iframe = trim((string) $row->getSourceProperty('youtube_iframe_2'));
    }
    $row->setSourceProperty('youtube_embed', $iframe);
    $row->setSourceProperty('youtube_url', self::extractYoutubeUrl($iframe));

    if (!empty($this->configuration['only_with_video']) && $row->getSourceProperty('youtube_url') === NULL) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Extracts a canonical YouTube watch URL from legacy iframe markup.
   */
  public static function extractYoutubeUrl(string $embed): ?string {
    $embed = trim($embed);
    if ($embed === '') {
      return NULL;
    }
    if (preg_match('#(?:youtube\.com/embed/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    if (preg_match('#[?&]v=([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    return NULL;
  }

}
