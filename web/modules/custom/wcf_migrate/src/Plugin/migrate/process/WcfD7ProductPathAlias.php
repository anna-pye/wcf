<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a unique /stallions/{alias} path from legacy product alias slugs.
 */
#[MigrateProcess('wcf_d7_product_path_alias')]
class WcfD7ProductPathAlias extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Alias usage counts loaded once per migration run.
   *
   * @var array<string, int>|null
   */
  protected static ?array $aliasCounts = NULL;

  /**
   * Constructs WcfD7ProductPathAlias.
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
    $slug = trim((string) $value);
    $id = (int) $row->getSourceProperty('id');
    if ($slug === '') {
      return NULL;
    }
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($slug)) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
      return NULL;
    }

    if (self::$aliasCounts === NULL) {
      self::$aliasCounts = [];
      $result = $this->migrateDatabase->select('product', 'p')
        ->fields('p', ['alias'])
        ->condition('alias', '', '<>')
        ->execute();
      foreach ($result as $record) {
        $alias = trim((string) $record->alias);
        if ($alias !== '') {
          self::$aliasCounts[$alias] = (self::$aliasCounts[$alias] ?? 0) + 1;
        }
      }
    }

    if ((self::$aliasCounts[$value] ?? 0) > 1) {
      $slug .= '-' . $id;
    }

    return '/stallions/' . $slug;
  }

}
