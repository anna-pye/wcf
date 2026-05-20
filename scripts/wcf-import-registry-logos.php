<?php

/**
 * @file
 * One-off import of client-supplied registry logos into Media + nodes.
 *
 * Usage: ddev drush php:script scripts/wcf-import-registry-logos.php
 */

declare(strict_types=1);

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$project_root = dirname(__DIR__);
$source_dir = $project_root . '/tmp/registry-logos';

$items = [
  [
    'file' => 'australian-studbook.png',
    'title' => 'Australian Studbook',
    'name' => 'Australian Studbook',
    'short' => NULL,
    'colour' => 'purple',
    'weight' => 0,
    'link' => NULL,
  ],
  [
    'file' => 'ahsa.png',
    'title' => 'Arabian Horse Society of Australia',
    'name' => 'Arabian Horse Society of Australia',
    'short' => 'AHSA',
    'colour' => 'coral',
    'weight' => 1,
    'link' => 'https://www.ahsa.asn.au/',
  ],
  [
    'file' => 'apsb.png',
    'title' => 'Australian Pony Stud Book Society',
    'name' => 'Australian Pony Stud Book Society',
    'short' => 'APSB',
    'colour' => 'sky',
    'weight' => 2,
    'link' => 'https://apsb.asn.au/',
  ],
  [
    'file' => 'rpsbs.png',
    'title' => 'Riding Pony Stud Book Society',
    'name' => 'Riding Pony Stud Book Society',
    'short' => 'RPSBS',
    'colour' => 'lilac',
    'weight' => 3,
    'link' => 'https://www.rpsbs.com.au/',
  ],
  [
    'file' => 'awha.png',
    'title' => 'Australian Warmblood Horse Association',
    'name' => 'Australian Warmblood Horse Association',
    'short' => 'AWHA',
    'colour' => 'gold',
    'weight' => 4,
    'link' => 'https://www.awha.com.au/',
  ],
];

$file_system = \Drupal::service('file_system');
$destination_dir = 'public://registry-logos';
$file_system->prepareDirectory($destination_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

$created = 0;
foreach ($items as $item) {
  $source = $source_dir . '/' . $item['file'];
  if (!is_readable($source)) {
    print "Skip (missing file): {$item['file']}\n";
    continue;
  }

  $destination = $destination_dir . '/' . $item['file'];
  $uri = $file_system->copy($source, $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
  if ($uri === FALSE) {
    print "Failed to copy: {$item['file']}\n";
    continue;
  }

  $file = File::create([
    'uri' => $uri,
    'status' => 1,
  ]);
  $file->save();

  $media = Media::create([
    'bundle' => 'image',
    'name' => $item['name'] . ' logo',
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => $item['name'],
    ],
  ]);
  $media->setPublished(TRUE);
  $media->save();

  $values = [
    'type' => 'wcf_registry_logo',
    'title' => $item['title'],
    'status' => 1,
    'field_registry_name' => $item['name'],
    'field_registry_logo' => ['target_id' => $media->id()],
    'field_registry_colour' => $item['colour'],
    'field_registry_weight' => $item['weight'],
  ];
  if ($item['short'] !== NULL) {
    $values['field_registry_short_name'] = $item['short'];
  }
  if ($item['link'] !== NULL) {
    $values['field_registry_link'] = [
      'uri' => $item['link'],
      'title' => '',
    ];
  }

  $node = Node::create($values);
  $node->save();
  $created++;
  print "Created registry logo node {$node->id()}: {$item['title']}\n";
}

print "Done. Created {$created} registry logo nodes.\n";
