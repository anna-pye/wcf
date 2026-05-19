<?php

/**
 * @file
 * Idempotent seed for governed category vocabulary terms.
 *
 * Creates only approved editorial classification terms. Does not delete,
 * rename, or auto-assign categories on content.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-governed-categories.php
 */

declare(strict_types=1);

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

$governed = [
  'Foals',
  'Broodmares',
  'Stallions',
  'ASB Stallions',
  'Show Mares',
];

$vocabulary = 'categories';
$existing = [];
$created = [];

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$tids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', $vocabulary)
  ->execute();

/** @var \Drupal\taxonomy\TermInterface[] $terms */
$terms = $storage->loadMultiple($tids);
$name_map = [];
foreach ($terms as $term) {
  if ($term instanceof TermInterface) {
    $name_map[mb_strtolower(trim($term->label()))] = $term;
  }
}

foreach ($governed as $name) {
  $key = mb_strtolower(trim($name));
  if (isset($name_map[$key])) {
    $existing[] = $name;
    continue;
  }

  $term = Term::create([
    'vid' => $vocabulary,
    'name' => $name,
  ]);
  $term->save();
  $created[] = $name;
}

print "Governed categories:\n";
foreach ($governed as $name) {
  if (in_array($name, $created, TRUE)) {
    print "- created: {$name}\n";
  }
  else {
    print "- existing: {$name}\n";
  }
}

if ($created === []) {
  print "\nNo new terms were created.\n";
}
else {
  print "\nCreated " . count($created) . " term(s).\n";
}
