<?php

/**
 * @file
 * Remove conflicting legacy short path aliases after canonical governance checks.
 *
 * Prerequisite: run scripts/canonical-redirect-remediation.php -- --apply so
 * Redirect module entries exist from legacy short alias → /horses/* canonical.
 *
 * While a legacy short alias remains in path_alias, Drupal resolves it directly
 * to the node (HTTP 200). After safe removal, inbound /vegas requests fall through
 * to Redirect module (HTTP 301 → canonical).
 *
 * Usage:
 *   ddev drush php:script scripts/canonical-alias-governance.php
 *   ddev drush php:script scripts/canonical-alias-governance.php -- --apply
 */

use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\RedirectRepository;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$database = \Drupal::database();
/** @var \Drupal\redirect\RedirectRepository $redirect_repository */
$redirect_repository = \Drupal::service('redirect.repository');
/** @var \Drupal\Core\Entity\EntityStorageInterface $path_alias_storage */
$path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

$default_langcode = \Drupal::languageManager()
  ->getDefaultLanguage()
  ->getId();

if (empty($default_langcode)) {
  throw new \RuntimeException('Default language could not be resolved.');
}

$canonical_prefix = '/horses/';

$nids = $database->query(
  'SELECT nid FROM {node_field_data} WHERE type = :type AND status = 1',
  [':type' => 'stallion']
)->fetchCol();

$plan = [];
$conflicts = [];
$canonical_aliases_verified = 0;
$redirects_verified = 0;
$aliases_planned_for_removal = 0;
$aliases_removed = 0;
$aliases_skipped = 0;

foreach ($nids as $nid) {
  $nid = (int) $nid;
  $path = '/node/' . $nid;

  $alias_ids = \Drupal::entityQuery('path_alias')
    ->condition('path', $path)
    ->condition('status', 1)
    ->accessCheck(FALSE)
    ->execute();

  if (count($alias_ids) < 2) {
    continue;
  }

  /** @var \Drupal\path_alias\PathAliasInterface[] $path_aliases */
  $path_aliases = $path_alias_storage->loadMultiple($alias_ids);

  $canonical_aliases = [];
  $legacy_entries = [];

  foreach ($path_aliases as $path_alias) {
    if ($path_alias->getPath() !== $path) {
      $conflicts[] = [
        'nid' => $nid,
        'reason' => 'alias_path_mismatch',
        'alias_id' => $path_alias->id(),
        'path' => $path_alias->getPath(),
      ];
      continue;
    }

    $alias = $path_alias->getAlias();
    if (str_starts_with($alias, $canonical_prefix)) {
      $canonical_aliases[] = [
        'alias' => $alias,
        'id' => (int) $path_alias->id(),
        'langcode' => $path_alias->language()->getId(),
      ];
    }
    else {
      $legacy_entries[] = [
        'alias' => $alias,
        'id' => (int) $path_alias->id(),
        'langcode' => $path_alias->language()->getId(),
      ];
    }
  }

  if (!$legacy_entries) {
    continue;
  }

  if (!$canonical_aliases) {
    foreach ($legacy_entries as $legacy) {
      $aliases_skipped++;
      $plan[] = [
        'nid' => $nid,
        'removed_alias' => $legacy['alias'],
        'canonical_alias' => NULL,
        'redirect_verified' => FALSE,
        'status' => 'skipped',
        'reason' => 'no_canonical_alias',
      ];
    }
    continue;
  }

  if (count($canonical_aliases) > 1) {
    $aliases_skipped += count($legacy_entries);
    $conflicts[] = [
      'nid' => $nid,
      'reason' => 'multiple_canonical_aliases',
      'canonical_aliases' => array_column($canonical_aliases, 'alias'),
    ];
    foreach ($legacy_entries as $legacy) {
      $plan[] = [
        'nid' => $nid,
        'removed_alias' => $legacy['alias'],
        'canonical_alias' => NULL,
        'redirect_verified' => FALSE,
        'status' => 'skipped',
        'reason' => 'multiple_canonical_aliases',
      ];
    }
    continue;
  }

  $canonical = $canonical_aliases[0]['alias'];
  $canonical_aliases_verified++;

  foreach ($legacy_entries as $legacy) {
    $legacy_alias = $legacy['alias'];
    $source_path = ltrim($legacy_alias, '/');

    $redirect = $redirect_repository->findMatchingRedirect(
      $source_path,
      [],
      $default_langcode
    );

    $redirect_verified = FALSE;
    $status = 'skipped';
    $reason = NULL;

    if (!$redirect) {
      $reason = 'redirect_missing';
      $aliases_skipped++;
      $conflicts[] = [
        'nid' => $nid,
        'reason' => $reason,
        'removed_alias' => $legacy_alias,
        'canonical_alias' => $canonical,
      ];
    }
    else {
      $redirect_data = $redirect->getRedirect();
      $target_uri = $redirect_data['uri'] ?? '';
      $expected_uri = 'internal:' . $canonical;

      if ($target_uri !== $expected_uri) {
        $reason = 'redirect_target_mismatch';
        $aliases_skipped++;
        $conflicts[] = [
          'nid' => $nid,
          'reason' => $reason,
          'removed_alias' => $legacy_alias,
          'canonical_alias' => $canonical,
          'redirect_target' => $target_uri,
          'expected_target' => $expected_uri,
        ];
      }
      elseif (!$redirect->isPublished()) {
        $reason = 'redirect_disabled';
        $aliases_skipped++;
        $conflicts[] = [
          'nid' => $nid,
          'reason' => $reason,
          'removed_alias' => $legacy_alias,
          'canonical_alias' => $canonical,
        ];
      }
      else {
        $redirect_verified = TRUE;
        $redirects_verified++;
        $status = 'planned';
        $aliases_planned_for_removal++;
      }
    }

    $entry = [
      'nid' => $nid,
      'removed_alias' => $legacy_alias,
      'canonical_alias' => $canonical,
      'redirect_verified' => $redirect_verified,
      'status' => $status,
    ];
    if ($reason) {
      $entry['reason'] = $reason;
    }

    if ($apply && $redirect_verified) {
      $entity = $path_alias_storage->load($legacy['id']);
      if ($entity instanceof PathAlias) {
        if ($entity->getPath() !== $path || $entity->getAlias() !== $legacy_alias) {
          $aliases_skipped++;
          $entry['status'] = 'skipped';
          $entry['reason'] = 'alias_changed_before_delete';
          $conflicts[] = [
            'nid' => $nid,
            'reason' => 'alias_changed_before_delete',
            'alias_id' => $legacy['id'],
          ];
        }
        else {
          $entity->delete();
          $aliases_removed++;
          $entry['status'] = 'removed';
        }
      }
      else {
        $entry['status'] = 'already_removed';
      }
    }

    $plan[] = $entry;
  }
}

$report = [
  'apply' => $apply,
  'published_stallions' => count($nids),
  'canonical_aliases_verified' => $canonical_aliases_verified,
  'redirects_verified' => $redirects_verified,
  'aliases_planned_for_removal' => $aliases_planned_for_removal,
  'aliases_removed' => $aliases_removed,
  'aliases_skipped' => $aliases_skipped,
  'conflicts' => $conflicts,
  'plan' => $plan,
];

if (!$apply) {
  $report['message'] = 'Dry run. Pass --apply to delete verified legacy short path_alias rows (canonical + redirect preserved).';
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
