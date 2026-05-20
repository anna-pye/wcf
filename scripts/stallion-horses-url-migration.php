<?php

/**
 * @file
 * Move stallion canonical aliases from /stallions/* to /horses/* and add 301 redirects.
 *
 * Usage:
 *   ddev drush php:script scripts/stallion-horses-url-migration.php
 *   ddev drush php:script scripts/stallion-horses-url-migration.php -- --apply
 */

use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$database = \Drupal::database();
$langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
$old_prefix = '/stallions/';
$new_prefix = '/horses/';

$nids = $database->query(
  'SELECT nid FROM {node_field_data} WHERE type = :type AND status = 1',
  [':type' => 'stallion']
)->fetchCol();

$plan = [];
foreach ($nids as $nid) {
  $path = '/node/' . $nid;
  $aliases = $database->query(
    'SELECT id, alias FROM {path_alias} WHERE path = :path AND status = 1 ORDER BY id ASC',
    [':path' => $path]
  )->fetchAll();

  $canonical = NULL;
  $legacy_stallions = [];
  foreach ($aliases as $row) {
    if (str_starts_with($row->alias, $old_prefix)) {
      $legacy_stallions[] = $row;
      if ($canonical === NULL) {
        $canonical = $row;
      }
    }
  }

  if ($canonical === NULL) {
    continue;
  }

  $new_alias = $new_prefix . substr($canonical->alias, strlen($old_prefix));
  $plan[] = [
    'nid' => (int) $nid,
    'old' => $canonical->alias,
    'new' => $new_alias,
    'alias_id' => (int) $canonical->id,
  ];
}

if (!$apply) {
  print json_encode(['dry_run' => TRUE, 'count' => count($plan), 'sample' => array_slice($plan, 0, 5)], JSON_PRETTY_PRINT) . "\n";
  print "Pass --apply to update aliases and create 301 redirects from /stallions/* to /horses/*.\n";
  return;
}

$updated = 0;
$redirects = 0;
foreach ($plan as $row) {
  $alias = PathAlias::load($row['alias_id']);
  if ($alias === NULL) {
    continue;
  }
  $alias->set('alias', $row['new']);
  $alias->save();
  $updated++;

  $source = ltrim($row['old'], '/');
  $existing = Redirect::loadMultiple(NULL, [
    'redirect_source__path' => $source,
    'language' => $langcode,
  ]);
  if ($existing === []) {
    Redirect::create([
      'redirect_source' => $source,
      'redirect_redirect' => 'internal:' . $row['new'],
      'language' => $langcode,
      'status_code' => 301,
    ])->save();
    $redirects++;
  }
}

\Drupal::service('path_alias.manager')->cacheClear();
print json_encode(['updated_aliases' => $updated, 'redirects_created' => $redirects], JSON_PRETTY_PRINT) . "\n";
