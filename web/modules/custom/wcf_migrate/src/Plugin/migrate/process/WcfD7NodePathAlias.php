<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads a Drupal 7 URL alias for a node source row.
 */
#[MigrateProcess('wcf_d7_node_path_alias')]
class WcfD7NodePathAlias extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a WcfD7NodePathAlias process plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $migrateDatabase,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      Database::getConnection('default', 'migrate'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $nid = $row->getSourceProperty('nid');
    if (!$nid) {
      return NULL;
    }
    $alias = $this->migrateDatabase->select('url_alias', 'ua')
      ->fields('ua', ['alias'])
      ->condition('source', 'node/' . $nid)
      ->orderBy('pid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($alias === FALSE || $alias === '') {
      return NULL;
    }
    return '/' . ltrim((string) $alias, '/');
  }

}
