<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Legacy WCF showcase rows (custom showcase module table).
 *
 * Table in D7 DB: wcf_showcase ({showcase} with site prefix).
 * Images live under sites/all/modules/showcase/files/showcase_images/;
 * use scripts/wcf-import-showcase.php for file + node import.
 *
 * @MigrateSource(
 *   id = "wcf_d7_showcase",
 *   source_module = "showcase"
 * )
 */
class WcfD7Showcase extends DrupalSqlBase {

  /**
   * Image filename columns (legacy product_img1–8).
   */
  public const IMAGE_COLUMNS = [
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
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('showcase', 's')
      ->fields('s')
      ->condition('s.status', 1);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Showcase ID'),
      'title' => $this->t('Title'),
      'description' => $this->t('Description HTML'),
      'status' => $this->t('Published flag'),
      'created_on' => $this->t('Created timestamp'),
      'modified_on' => $this->t('Modified timestamp'),
      'product_img1' => $this->t('Image 1 filename'),
      'product_img2' => $this->t('Image 2 filename'),
      'product_img3' => $this->t('Image 3 filename'),
      'product_img4' => $this->t('Image 4 filename'),
      'product_img5' => $this->t('Image 5 filename'),
      'product_img6' => $this->t('Image 6 filename'),
      'product_img7' => $this->t('Image 7 filename'),
      'product_img8' => $this->t('Image 8 filename'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 's',
      ],
    ];
  }

}
