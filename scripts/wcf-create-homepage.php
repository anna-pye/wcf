<?php

/**
 * @file
 * Creates the WCF homepage node with hero slides from migrated D7 content_slider data.
 *
 * Run: ddev drush php:script scripts/wcf-create-homepage.php
 *
 * Idempotent: skips creation if a published homepage node already exists.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityStorageException;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Loads a published image media entity or throws.
 */
function wcf_load_hero_media(int $mid): MediaInterface {
  $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
  if (!$media instanceof MediaInterface || $media->bundle() !== 'image' || !$media->isPublished()) {
    throw new \RuntimeException(sprintf('Media %d is missing or not a published image.', $mid));
  }
  return $media;
}

/**
 * Returns an existing published homepage node if present.
 */
function wcf_find_homepage(): ?NodeInterface {
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'type' => 'homepage',
      'status' => NodeInterface::PUBLISHED,
    ]);
  $node = reset($nodes);
  return $node instanceof NodeInterface ? $node : NULL;
}

// Mapped from D7 content_slider (nid order by created ASC).
// field_content → field_description; field_slider_image → field_image (via migrate_map).
$slides = [
  [
    'title' => 'MOONLARK (USA)',
    'description' => 'CALL - 0411826965',
    'media_id' => 68,
    'd7_nid' => 26,
  ],
  [
    'title' => 'Thomas The Tank',
    'description' => 'CALL - 0411826965',
    'media_id' => 72,
    'd7_nid' => 36,
  ],
  [
    'title' => 'Mega Charge',
    'description' => 'CALL - 0411826965',
    'media_id' => 82,
    'd7_nid' => 38,
  ],
];

$existing = wcf_find_homepage();
if ($existing !== NULL) {
  \Drupal::logger('wcf')->notice('Homepage already exists (nid @nid). Skipping creation.', [
    '@nid' => $existing->id(),
  ]);
  print sprintf("Homepage already exists: node/%s\n", $existing->id());
  return;
}

$paragraph_refs = [];
foreach ($slides as $slide) {
  wcf_load_hero_media($slide['media_id']);

  $paragraph = Paragraph::create([
    'type' => 'hero_slide',
    'field_title' => $slide['title'],
    'field_description' => [
      'value' => $slide['description'],
      'format' => 'plain_text',
    ],
    'field_image' => [
      'target_id' => $slide['media_id'],
    ],
    'field_cta_text' => 'Call us',
    'field_cta_url' => [
      'uri' => 'tel:+61411826965',
      'title' => '',
      'options' => [],
    ],
  ]);
  $paragraph->save();
  $paragraph_refs[] = [
    'target_id' => $paragraph->id(),
    'target_revision_id' => $paragraph->getRevisionId(),
  ];
  print sprintf(
    "Created hero_slide %d: %s (D7 nid %d, media %d)\n",
    $paragraph->id(),
    $slide['title'],
    $slide['d7_nid'],
    $slide['media_id'],
  );
}

try {
  $homepage = Node::create([
    'type' => 'homepage',
    'title' => 'Winning Colours Farm',
    'status' => NodeInterface::PUBLISHED,
    'field_hero_slides' => $paragraph_refs,
  ]);
  $homepage->save();
}
catch (EntityStorageException $e) {
  throw new \RuntimeException('Failed to save homepage node: ' . $e->getMessage(), 0, $e);
}

\Drupal::logger('wcf')->notice('Created homepage node @nid with @count hero slides.', [
  '@nid' => $homepage->id(),
  '@count' => count($paragraph_refs),
]);

print sprintf("\nHomepage created: node/%s (%s)\n", $homepage->id(), $homepage->toUrl()->toString());
