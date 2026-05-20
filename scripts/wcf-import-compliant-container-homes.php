<?php

/**
 * @file
 * Import legacy Compliant Container Homes page (D7 node/31 + gallery nodes).
 *
 * D7 evidence:
 * - Page node 31 (bundle page) alias compliant_container_homes — primary copy/images.
 * - Nodes 33–35 (compliant_container_homes) — supplementary gallery only.
 *
 * Usage:
 *   ddev drush php:script scripts/wcf-import-compliant-container-homes.php
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;

const WCF_CCH_NODE_TITLE = 'Compliant Container Homes';
const WCF_CCH_ALIAS = '/compliant-container-homes';
const WCF_CCH_LEGACY_REDIRECT_SOURCE = 'compliant_container_homes';

$source_dir = dirname(__DIR__) . '/tmp/container-homes';
$destination_dir = 'public://container-homes';

$main_image_file = '167125137_549219019389040_1984582395460846941_n.jpg';
$gallery_files = [
  '167096130_230861625206172_765457184139337024_n.jpg',
  '167522032_493406201841703_4007857204555284434_n.jpg',
  '169085645_483851099637875_8859980500380252051_n.jpg',
  'd43e8ab08e5752ffae0378a7b88e25af.jpg',
  '170677003_1613655892161817_6799594773281270352_n_2.jpg',
  '5abe3f238f68efbbbb8989ad72090d7e.jpg',
  '3cfd6948bb01cef5e6b5dd22e3a7a005.jpg',
  'd43e8ab08e5752ffae0378a7b88e25af (1).jpg',
];

$body = <<<HTML
<h4>Finally, an expandable portable container home that is Engineered to Australian Standards and achieves BASIX and Council Approval.</h4>

<p>I wanted one of these fabulous expandable container homes for a workers cottage, but I wanted to be able to get Council Approval. Like all new developments, they need to be customized for the locality, aspect and many other factors to comply with Council requirements, AU standards and for a BASIX certificate (NSW) which every new dwelling in NSW is required to comply for permanent occupancy. Therefore, I had to have one custom made.</p>

<p>With many months of to-ing and fro-ing on design due to compliance requirements, and with many different companies, and re-engineering with much modification, finally 'I' have 'built' my perfect Cottage that passes along with the BASIX Certificate in the second coldest climate zone in Australia.</p>

<p>The great thing about getting a Council approved development means you can get a bank loan for them depending on your credit history and financial situation of course, and rent them out, or use them as granny flats. (And your neighbours can't complain or anything untoward!).</p>

<p>Just like importing the first coloured Thoroughbreds into Australia with full registration and compliance (I knew if I wanted one there would be others that wanted one) So too I believe if I want one of these fabulous expandable portable container homes that has full approval with the Authorities, other savvy people would want one too!</p>

<p>Most other expandable portable container units being sold are non-compliant, nor can they be without re-engineering and custom fabrication and huge upgrades as Compliant Container Homes are. The cheaper units will never pass the required NSW BASIX certificate, nor Council regulations or AU standards which is a must for a permanent dwelling (which some sellers are selling them for and hoodwinking buyers). Our Engineered unit to Australian Standards and highly upgraded is nothing like its cheap counterparts and passes all requirements.</p>

<p>It makes logical sense to buy a unit that's Compliant rather than inferior and even dangerous. Our units are fire rated to the highest level. At 1000 degrees celsius the walls and ceiling with huge R values (thermal insulation qualities) will only smoulder, not burn. After the last bushfire season I know fire rating is something on everyone's mind.</p>

<p>And yes, they are portable so you can pack them up and take them with you to wherever life takes you should you choose.</p>

<p>Pictures shown are 20 foot version. Also comes in 40' version.</p>

<h4><strong>Priced at just $45,000</strong> inc GST! <strong>Call 0411 826 965</strong> to order your Granny Flat, and start making income or save on rent or mortgages today!</h4>

<p>Here are some additional pictures and variations. Front verandah, higher pitched roof, downpipes and guttering, cladding, Caesar stone bench tops, aluminium double glazed windows and doors are all standard!!</p>
HTML;

$file_system = \Drupal::service('file_system');
$file_system->prepareDirectory(
  $destination_dir,
  FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
);

/**
 * Returns media ID for a local D7 file (reuses existing file/media when present).
 */
$ensure_media = static function (string $filename, string $alt) use ($source_dir, $destination_dir, $file_system): ?int {
  $source = $source_dir . '/' . $filename;
  if (!is_readable($source)) {
    print "Missing source file: {$filename}\n";
    return NULL;
  }

  $destination = $destination_dir . '/' . $filename;
  $existing_files = \Drupal::entityTypeManager()->getStorage('file')
    ->loadByProperties(['uri' => $destination]);
  if ($existing_files) {
    $file = reset($existing_files);
  }
  else {
    $uri = $file_system->copy($source, $destination, FileSystemInterface::EXISTS_REPLACE);
    if ($uri === FALSE) {
      print "Failed to copy: {$filename}\n";
      return NULL;
    }
    $file = File::create([
      'uri' => $uri,
      'status' => 1,
    ]);
    $file->save();
    print "Copied file: {$filename}\n";
  }

  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $existing_media = $media_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('bundle', 'image')
    ->condition('field_media_image.target_id', $file->id())
    ->range(0, 1)
    ->execute();
  if ($existing_media) {
    $mid = (int) reset($existing_media);
    print "Reused media {$mid} for {$filename}\n";
    return $mid;
  }

  $media = Media::create([
    'bundle' => 'image',
    'name' => $alt,
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => $alt,
    ],
  ]);
  $media->setPublished(TRUE);
  $media->save();
  print "Created media {$media->id()} for {$filename}\n";
  return (int) $media->id();
};

$main_media_id = $ensure_media($main_image_file, 'Compliant container home — exterior');
if ($main_media_id === NULL) {
  throw new \RuntimeException('Main image import failed.');
}

$gallery_media_ids = [];
foreach ($gallery_files as $gallery_file) {
  $mid = $ensure_media($gallery_file, 'Compliant container home gallery');
  if ($mid !== NULL) {
    $gallery_media_ids[] = $mid;
  }
}

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$existing = $node_storage->loadByProperties([
  'type' => 'container_home',
  'title' => WCF_CCH_NODE_TITLE,
]);
$node = $existing ? reset($existing) : NULL;
if ($node) {
  print 'Updating existing node: ' . $node->id() . "\n";
}
else {
  $node = Node::create([
    'type' => 'container_home',
    'title' => WCF_CCH_NODE_TITLE,
    'uid' => 1,
  ]);
  print "Creating new node: Compliant Container Homes\n";
}

$node->set('body', [
  'value' => $body,
  'format' => 'basic_html',
]);
$node->set('field_main_image', ['target_id' => $main_media_id]);
$node->set('field_gallery', array_map(
  static fn (int $mid): array => ['target_id' => $mid],
  $gallery_media_ids
));
$node->set('field_sold', FALSE);
$node->setPublished(TRUE);
$node->set('path', [
  'alias' => WCF_CCH_ALIAS,
  'pathauto' => 0,
  'langcode' => 'en',
]);
$node->save();
$nid = (int) $node->id();
print "Saved node {$nid}\n";

// Ensure path alias entity exists (in addition to node path field).
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$aliases = $alias_storage->loadByProperties(['alias' => WCF_CCH_ALIAS]);
$path = '/node/' . $nid;
$alias_entity = NULL;
foreach ($aliases as $candidate) {
  if ($candidate->getPath() === $path) {
    $alias_entity = $candidate;
    break;
  }
}
if (!$alias_entity && $aliases) {
  $alias_entity = reset($aliases);
  $alias_entity->setPath($path);
  $alias_entity->save();
  print "Updated path alias to {$path}\n";
}
elseif (!$alias_entity) {
  PathAlias::create([
    'path' => $path,
    'alias' => WCF_CCH_ALIAS,
    'langcode' => 'en',
  ])->save();
  print 'Created path alias ' . WCF_CCH_ALIAS . "\n";
}
else {
  print "Path alias already correct for node {$nid}\n";
}

// Fix legacy redirect (was incorrectly pointing at a stallion).
$redirects = \Drupal::entityTypeManager()->getStorage('redirect')
  ->loadByProperties(['redirect_source' => ['path' => WCF_CCH_LEGACY_REDIRECT_SOURCE]]);
$redirect = $redirects ? reset($redirects) : NULL;
$target = WCF_CCH_ALIAS;
if ($redirect) {
  $redirect->setRedirect($target);
  $redirect->setStatusCode(301);
  $redirect->setPublished();
  $redirect->save();
  print 'Updated redirect ' . $redirect->id() . ': /' . WCF_CCH_LEGACY_REDIRECT_SOURCE . " → {$target}\n";
}
else {
  Redirect::create([
    'redirect_source' => ['path' => WCF_CCH_LEGACY_REDIRECT_SOURCE],
    'redirect_redirect' => ['uri' => $target],
    'status_code' => 301,
    'language' => 'en',
  ])->setPublished()->save();
  print 'Created redirect /' . WCF_CCH_LEGACY_REDIRECT_SOURCE . " → {$target}\n";
}

print "\nDone. Node {$nid} at " . WCF_CCH_ALIAS . "\n";
