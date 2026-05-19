<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\source;

use Drupal\file\Plugin\migrate\source\d7\File;
use Drupal\migrate\Row;

/**
 * Drupal 7 image files referenced by WCF legacy image fields.
 *
 * @MigrateSource(
 *   id = "wcf_d7_image_file",
 *   source_module = "file"
 * )
 */
class WcfD7ImageFile extends File {

  /**
   * Legacy image fields that feed the file → media pipeline.
   */
  protected const IMAGE_FIELDS = [
    'field_image',
    'field_main_image',
    'field_slider_image',
    'field_containerimage',
    'field_additional_images',
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $fids = $this->getImageFieldFileIds();
    if ($fids === []) {
      $query->condition('f.fid', 0);
    }
    else {
      $query->condition('f.fid', $fids, 'IN');
    }
    return $query;
  }

  /**
   * Collects unique file IDs from configured legacy image field tables.
   *
   * @return int[]
   *   File IDs.
   */
  protected function getImageFieldFileIds(): array {
    $database = $this->getDatabase();
    $fids = [];
    foreach (self::IMAGE_FIELDS as $field_name) {
      $table = 'field_data_' . $field_name;
      if (!$database->schema()->tableExists($table)) {
        continue;
      }
      $fid_column = $field_name . '_fid';
      $result = $database->select($table, 't')
        ->fields('t', [$fid_column])
        ->condition('deleted', 0)
        ->isNotNull($fid_column)
        ->condition($fid_column, 0, '>')
        ->execute();
      foreach ($result as $row) {
        $fid = (int) $row->{$fid_column};
        $fids[$fid] = $fid;
      }
    }
    return array_values($fids);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }
    $fid = (int) $row->getSourceProperty('fid');
    $row->setSourceProperty('alt', $this->getImagePropertyForFile($fid, 'alt'));
    $row->setSourceProperty('title', $this->getImagePropertyForFile($fid, 'title'));
    return TRUE;
  }

  /**
   * Returns the first non-empty image field property for a file ID.
   */
  protected function getImagePropertyForFile(int $fid, string $property): string {
    $database = $this->getDatabase();
    foreach (self::IMAGE_FIELDS as $field_name) {
      $table = 'field_data_' . $field_name;
      if (!$database->schema()->tableExists($table)) {
        continue;
      }
      $column = $field_name . '_' . $property;
      if (!$database->schema()->fieldExists($table, $column)) {
        continue;
      }
      $fid_column = $field_name . '_fid';
      $value = $database->select($table, 't')
        ->fields('t', [$column])
        ->condition('deleted', 0)
        ->condition($fid_column, $fid)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($value !== FALSE && $value !== NULL && $value !== '') {
        return (string) $value;
      }
    }
    return '';
  }

}
