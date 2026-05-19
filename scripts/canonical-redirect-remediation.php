<?php

/**
 * @file
 * Create Redirect module entries from legacy short aliases to Pathauto canonicals.
 *
 * Canonical for stallions: /stallions/* (pathauto.pattern.node_stallion).
 * Legacy short aliases (e.g. /vegas) are preserved as 301 sources only.
 *
 * Usage:
 *   ddev drush php:script scripts/canonical-redirect-remediation.php
 *   ddev drush php:script scripts/canonical-redirect-remediation.php -- --apply
 */

use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$database = \Drupal::database();
/** @var \Drupal\redirect\RedirectRepository $redirect_repository */
$redirect_repository = \Drupal::service('redirect.repository');

$default_langcode = \Drupal::languageManager()
  ->getDefaultLanguage()
  ->getId();

if ($default_langcode === '' || $default_langcode === NULL) {
  throw new \RuntimeException('Default language could not be resolved.');
}

$canonical_prefix = '/stallions/';
$nids = $database->query(
  'SELECT nid FROM {node_field_data} WHERE type = :type AND status = 1',
  [':type' => 'stallion']
)->fetchCol();

$plan = [];
$created = 0;
$skipped = 0;
$errors = [];

foreach ($nids as $nid) {
  $path = '/node/' . $nid;
  $aliases = $database->query(
    'SELECT alias FROM {path_alias} WHERE path = :path AND status = 1 ORDER BY id ASC',
    [':path' => $path]
  )->fetchCol();

  if (count($aliases) < 2) {
    continue;
  }

  $canonical = NULL;
  $legacy = [];
  foreach ($aliases as $alias) {
    if (str_starts_with($alias, $canonical_prefix)) {
      $canonical = $canonical ?? $alias;
    }
    else {
      $legacy[] = $alias;
    }
  }

  if (!$canonical || !$legacy) {
    $skipped++;
    continue;
  }

  foreach ($legacy as $source) {
    $source_path = ltrim($source, '/');
    $existing = $redirect_repository->findMatchingRedirect(
      $source_path,
      [],
      $default_langcode
    );
    $entry = [
      'nid' => (int) $nid,
      'source' => $source,
      'target' => $canonical,
      'status' => $existing ? 'exists' : 'planned',
    ];
    $plan[] = $entry;

    if ($apply && !$existing) {
      try {
        $redirect = Redirect::create();
        $redirect->setSource($source_path);
        $redirect->setRedirect($canonical);
        $redirect->setStatusCode(301);
        $redirect->setLanguage($default_langcode);
        $redirect->setPublished();
        $redirect->save();
        $created++;
        $entry['status'] = 'created';
      }
      catch (\Exception $exception) {
        $errors[] = [
          'nid' => (int) $nid,
          'source' => $source,
          'message' => $exception->getMessage(),
        ];
        $entry['status'] = 'error';
      }
    }
  }
}

$report = [
  'apply' => $apply,
  'default_langcode' => $default_langcode,
  'published_stallions' => count($nids),
  'redirects_planned' => count(array_filter($plan, fn(array $r) => $r['status'] === 'planned')),
  'redirects_existing' => count(array_filter($plan, fn(array $r) => $r['status'] === 'exists')),
  'stallions_skipped' => $skipped,
  'redirects_created' => $created,
  'errors' => $errors,
  'plan' => $plan,
];

if (!$apply) {
  $report['message'] = 'Dry run. Pass --apply to create 301 redirects (legacy short alias → /stallions/* canonical).';
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
