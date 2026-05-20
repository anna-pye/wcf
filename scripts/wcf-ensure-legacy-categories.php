<?php

/**
 * @file
 * Idempotent seed for legacy WCF product/navigation category terms.
 *
 * Ensures the full D7-era category label set exists in vocabulary `categories`.
 * Creates only missing terms (case-insensitive name match). Never duplicates.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-ensure-legacy-categories.php
 */

declare(strict_types=1);

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;

$required = [
  'Foals',
  'For Sale',
  'Broodmares',
  'Showcase',
  'Show mares',
  'Stallions',
  'ASB Stallions',
  'Agistment',
  'Sold',
  'Sold Stallions',
  'Compliant Container Homes',
];

$vocabulary_id = 'categories';

if (!Vocabulary::load($vocabulary_id)) {
  throw new \RuntimeException("Vocabulary \"{$vocabulary_id}\" is missing. Import config/sync first.");
}

/** @var \Drupal\taxonomy\TermStorageInterface $storage */
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$tids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', $vocabulary_id)
  ->execute();

/** @var \Drupal\taxonomy\TermInterface[] $terms */
$terms = $storage->loadMultiple($tids);
$name_map = [];
foreach ($terms as $term) {
  if ($term instanceof TermInterface) {
    $name_map[mb_strtolower(trim($term->label()))] = $term;
  }
}

foreach ($required as $name) {
  $key = mb_strtolower(trim($name));
  if (isset($name_map[$key])) {
    $term = $name_map[$key];
    print sprintf(
      "EXISTS: %s (tid %s)\n",
      $term->label(),
      $term->id(),
    );
    continue;
  }

  $values = [
    'vid' => $vocabulary_id,
    'name' => $name,
  ];
  if ($storage->getEntityType()->hasKey('status')) {
    $values['status'] = TRUE;
  }

  $term = Term::create($values);
  $term->save();
  $name_map[$key] = $term;
  print sprintf("CREATED: %s (tid %s)\n", $name, $term->id());
}

print "\nDone. Terms are content (taxonomy_term entities), not config export.\n";
