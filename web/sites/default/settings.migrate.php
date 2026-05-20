<?php

/**
 * @file
 * Legacy D7 database and Migrate API settings for local/staging imports.
 *
 * Requires the `legacy` database in DDEV (see .ddev/config.yaml) populated via
 * scripts/wcf-import-legacy-database.sh.
 */

if (getenv('IS_DDEV_PROJECT') !== 'true') {
  return;
}

$databases['migrate']['default'] = [
  'database' => 'legacy',
  'username' => 'db',
  'password' => 'db',
  'host' => 'db',
  'port' => 3306,
  'driver' => 'mysql',
  'prefix' => 'wcf_',
  'collation' => 'utf8mb4_general_ci',
];

$settings['migrate_source_version'] = '7';
$settings['migrate_source_connection'] = 'migrate';
$settings['migrate_file_public_path'] = dirname(DRUPAL_ROOT) . '/legacy';
