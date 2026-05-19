<?php

/**
 * @file
 * WCF Drupal 11 foundational content architecture setup.
 *
 * Run: ddev drush php:script scripts/wcf-foundation-setup.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Creates a field storage if it does not exist.
 */
function wcf_ensure_field_storage(array $values): FieldStorageConfig {
  $storage = FieldStorageConfig::loadByName($values['entity_type'], $values['field_name']);
  if ($storage) {
    return $storage;
  }
  return FieldStorageConfig::create($values);
}

/**
 * Creates a field instance if it does not exist.
 */
function wcf_ensure_field_instance(array $values): FieldConfig {
  $field = FieldConfig::loadByName($values['entity_type'], $values['bundle'], $values['field_name']);
  if ($field) {
    return $field;
  }
  return FieldConfig::create($values);
}

/**
 * Ensures form display has components for the given fields.
 */
function wcf_configure_form_display(string $entity_type, string $bundle, array $components): void {
  $display = EntityFormDisplay::load("{$entity_type}.{$bundle}.default");
  if (!$display) {
    $display = EntityFormDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }
  foreach ($components as $field_name => $component) {
    $display->setComponent($field_name, $component);
  }
  $display->save();
}

/**
 * Ensures view display has components for the given fields.
 */
function wcf_configure_view_display(string $entity_type, string $bundle, array $components): void {
  $display = EntityViewDisplay::load("{$entity_type}.{$bundle}.default");
  if (!$display) {
    $display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }
  foreach ($components as $field_name => $component) {
    $display->setComponent($field_name, $component);
  }
  $display->save();
}

// Phase 3: Verify Categories vocabulary.
$vocabulary = Vocabulary::load('categories');
if (!$vocabulary) {
  throw new \RuntimeException('Categories vocabulary (categories) is missing. Import config/sync or run wcf-governed-categories.php first.');
}
print "Verified taxonomy vocabulary: categories\n";

// Phase 4A: Verify Basic page exists.
if (!NodeType::load('page')) {
  throw new \RuntimeException('Basic page content type (page) is missing.');
}
print "Verified content type: page (Basic page)\n";

// Phase 4B: Container Home content type.
if (!NodeType::load('container_home')) {
  NodeType::create([
    'type' => 'container_home',
    'name' => 'Container Home',
    'description' => 'Showcase container home listings for migration from compliant_container_homes.',
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => FALSE,
  ])->save();
  node_add_body_field(NodeType::load('container_home'));
  print "Created content type: container_home\n";
}
else {
  print "Content type already exists: container_home\n";
}

// field_main_image storage (single media:image).
wcf_ensure_field_storage([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'bundle' => 'container_home',
  'label' => 'Main image',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

// field_gallery storage (multiple media:image).
wcf_ensure_field_storage([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => -1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'bundle' => 'container_home',
  'label' => 'Gallery',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

// field_sold boolean.
wcf_ensure_field_storage([
  'field_name' => 'field_sold',
  'entity_type' => 'node',
  'type' => 'boolean',
  'cardinality' => 1,
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_sold',
  'entity_type' => 'node',
  'bundle' => 'container_home',
  'label' => 'Sold',
  'required' => FALSE,
  'settings' => [
    'on_label' => 'Sold',
    'off_label' => 'Available',
  ],
])->save();

// field_category on container_home (reuse storage).
if (!FieldStorageConfig::loadByName('node', 'field_category')) {
  wcf_ensure_field_storage([
    'field_name' => 'field_category',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
}

wcf_ensure_field_instance([
  'field_name' => 'field_category',
  'entity_type' => 'node',
  'bundle' => 'container_home',
  'label' => 'Category',
  'description' => 'Governed editorial categories only. Select from the approved list; new categories cannot be created here.',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['categories' => 'categories'],
      'sort' => ['field' => 'name', 'direction' => 'asc'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_configure_form_display('node', 'container_home', [
  'title' => ['type' => 'string_textfield', 'weight' => 0, 'settings' => ['size' => 60, 'placeholder' => ''], 'region' => 'content'],
  'body' => ['type' => 'text_textarea_with_summary', 'weight' => 1, 'settings' => ['rows' => 9, 'summary_rows' => 3, 'placeholder' => ''], 'region' => 'content'],
  'field_main_image' => ['type' => 'media_library_widget', 'weight' => 2, 'settings' => ['media_types' => ['image']], 'region' => 'content'],
  'field_gallery' => ['type' => 'media_library_widget', 'weight' => 3, 'settings' => ['media_types' => ['image']], 'region' => 'content'],
  'field_sold' => ['type' => 'boolean_checkbox', 'weight' => 4, 'settings' => ['display_label' => TRUE], 'region' => 'content'],
  'field_category' => ['type' => 'options_select', 'weight' => 5, 'settings' => [], 'region' => 'content'],
  'path' => ['type' => 'path', 'weight' => 30, 'region' => 'content'],
]);

wcf_configure_view_display('node', 'container_home', [
  'body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0, 'region' => 'content'],
  'field_main_image' => ['type' => 'entity_reference_entity_view', 'label' => 'hidden', 'weight' => 1, 'settings' => ['view_mode' => 'default', 'link' => FALSE], 'region' => 'content'],
  'field_gallery' => ['type' => 'entity_reference_entity_view', 'label' => 'above', 'weight' => 2, 'settings' => ['view_mode' => 'default', 'link' => FALSE], 'region' => 'content'],
  'field_sold' => ['type' => 'boolean', 'label' => 'inline', 'weight' => 3, 'region' => 'content'],
  'field_category' => ['type' => 'entity_reference_label', 'label' => 'above', 'weight' => 4, 'settings' => ['link' => TRUE], 'region' => 'content'],
]);

print "Configured container_home fields and displays\n";

// Phase 5: hero_slide paragraph type.
if (!ParagraphsType::load('hero_slide')) {
  ParagraphsType::create([
    'id' => 'hero_slide',
    'label' => 'Hero slide',
    'description' => 'Reusable hero carousel slide for Layout Builder integration.',
  ])->save();
  print "Created paragraph type: hero_slide\n";
}
else {
  print "Paragraph type already exists: hero_slide\n";
}

wcf_ensure_field_storage([
  'field_name' => 'field_title',
  'entity_type' => 'paragraph',
  'type' => 'string',
  'cardinality' => 1,
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_title',
  'entity_type' => 'paragraph',
  'bundle' => 'hero_slide',
  'label' => 'Title',
  'required' => TRUE,
])->save();

wcf_ensure_field_storage([
  'field_name' => 'field_image',
  'entity_type' => 'paragraph',
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_image',
  'entity_type' => 'paragraph',
  'bundle' => 'hero_slide',
  'label' => 'Image',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_ensure_field_storage([
  'field_name' => 'field_description',
  'entity_type' => 'paragraph',
  'type' => 'text_long',
  'cardinality' => 1,
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_description',
  'entity_type' => 'paragraph',
  'bundle' => 'hero_slide',
  'label' => 'Description',
  'required' => FALSE,
  'settings' => [],
])->save();

wcf_ensure_field_storage([
  'field_name' => 'field_cta_text',
  'entity_type' => 'paragraph',
  'type' => 'string',
  'cardinality' => 1,
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_cta_text',
  'entity_type' => 'paragraph',
  'bundle' => 'hero_slide',
  'label' => 'CTA text',
  'required' => FALSE,
])->save();

wcf_ensure_field_storage([
  'field_name' => 'field_cta_url',
  'entity_type' => 'paragraph',
  'type' => 'link',
  'cardinality' => 1,
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_cta_url',
  'entity_type' => 'paragraph',
  'bundle' => 'hero_slide',
  'label' => 'CTA URL',
  'required' => FALSE,
  'settings' => [
    'title' => 0,
    'link_type' => 16,
  ],
])->save();

wcf_configure_form_display('paragraph', 'hero_slide', [
  'field_title' => ['type' => 'string_textfield', 'weight' => 0, 'settings' => ['size' => 60, 'placeholder' => ''], 'region' => 'content'],
  'field_image' => ['type' => 'media_library_widget', 'weight' => 1, 'settings' => ['media_types' => ['image']], 'region' => 'content'],
  'field_description' => ['type' => 'text_textarea', 'weight' => 2, 'settings' => ['rows' => 5, 'placeholder' => ''], 'region' => 'content'],
  'field_cta_text' => ['type' => 'string_textfield', 'weight' => 3, 'settings' => ['size' => 60, 'placeholder' => ''], 'region' => 'content'],
  'field_cta_url' => ['type' => 'link_default', 'weight' => 4, 'settings' => ['placeholder_url' => '', 'placeholder_title' => ''], 'region' => 'content'],
]);

wcf_configure_view_display('paragraph', 'hero_slide', [
  'field_title' => ['type' => 'string', 'label' => 'hidden', 'weight' => 1, 'region' => 'content'],
  'field_image' => ['type' => 'entity_reference_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'hero', 'link' => FALSE], 'region' => 'content'],
  'field_description' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
]);

print "Configured hero_slide paragraph fields and displays\n";

// Phase 8: Homepage content type with hero slides.
if (!NodeType::load('homepage')) {
  NodeType::create([
    'type' => 'homepage',
    'name' => 'Homepage',
    'description' => 'Dedicated front page content with hero slides and future homepage sections.',
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => FALSE,
  ])->save();
  print "Created content type: homepage\n";
}
else {
  print "Content type already exists: homepage\n";
}

wcf_ensure_field_storage([
  'field_name' => 'field_hero_slides',
  'entity_type' => 'node',
  'type' => 'entity_reference_revisions',
  'cardinality' => -1,
  'settings' => ['target_type' => 'paragraph'],
])->save();

wcf_ensure_field_instance([
  'field_name' => 'field_hero_slides',
  'entity_type' => 'node',
  'bundle' => 'homepage',
  'label' => 'Hero slides',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => ['hero_slide' => 'hero_slide'],
      'negate' => 0,
      'target_bundles_drag_drop' => [
        'hero_slide' => ['enabled' => TRUE, 'weight' => 0],
      ],
    ],
  ],
])->save();

wcf_configure_form_display('node', 'homepage', [
  'title' => ['type' => 'string_textfield', 'weight' => 0, 'settings' => ['size' => 60, 'placeholder' => ''], 'region' => 'content'],
  'field_hero_slides' => [
    'type' => 'paragraphs',
    'weight' => 10,
    'settings' => [
      'title' => 'Hero slide',
      'title_plural' => 'Hero slides',
      'edit_mode' => 'open',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'hero_slide',
    ],
    'region' => 'content',
  ],
  'path' => ['type' => 'path', 'weight' => 30, 'region' => 'content'],
]);

wcf_configure_view_display('node', 'homepage', [
  'field_hero_slides' => [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'region' => 'content',
  ],
]);

print "Configured homepage content type and field_hero_slides\n";

// Phase 6: Metatag global defaults.
$global = MetatagDefaults::load('global');
if ($global) {
  $tags = $global->get('tags');
  $tags['title'] = '[current-page:title] | [site:name]';
  $tags['description'] = '[site:slogan]';
  $tags['og_site_name'] = '[site:name]';
  $tags['og_title'] = '[current-page:title]';
  $tags['og_description'] = '[site:slogan]';
  $tags['og_url'] = '[current-page:url:absolute]';
  $tags['og_type'] = 'website';
  $global->set('tags', $tags);
  $global->save();
  print "Updated metatag global defaults\n";
}

$node_defaults = MetatagDefaults::load('node');
if ($node_defaults) {
  $tags = $node_defaults->get('tags');
  $tags['description'] = '[node:summary]';
  $tags['og_title'] = '[node:title]';
  $tags['og_description'] = '[node:summary]';
  $tags['og_url'] = '[node:url:absolute]';
  $tags['og_type'] = 'article';
  $node_defaults->set('tags', $tags);
  $node_defaults->save();
  print "Updated metatag node defaults\n";
}

// Phase 7: Pathauto patterns.
$patterns = [
  'node_page' => [
    'label' => 'Content Basic page',
    'bundle' => 'page',
    'pattern' => '[node:title]',
    'weight' => -5,
  ],
  'node_container_home' => [
    'label' => 'Content Container Home',
    'bundle' => 'container_home',
    'pattern' => 'container-homes/[node:title]',
    'weight' => -5,
  ],
];

foreach ($patterns as $id => $info) {
  if (PathautoPattern::load($id)) {
    print "Pathauto pattern already exists: {$id}\n";
    continue;
  }
  $pattern = PathautoPattern::create([
    'id' => $id,
    'label' => $info['label'],
    'type' => 'canonical_entities:node',
    'pattern' => $info['pattern'],
    'weight' => $info['weight'],
  ]);
  $pattern->addSelectionCondition([
    'id' => 'entity_bundle:node',
    'bundles' => [$info['bundle'] => $info['bundle']],
    'negate' => FALSE,
    'context_mapping' => ['node' => 'node'],
  ]);
  $pattern->save();
  print "Created pathauto pattern: {$id}\n";
}

// Redirect module: ensure 404 redirect setting is enabled (sane default).
$config = \Drupal::configFactory()->getEditable('redirect.settings');
$config->set('auto_redirect', TRUE);
$config->set('default_status_code', 301);
$config->set('route_normalizer_enabled', TRUE);
$config->save();
print "Configured redirect.settings\n";

print "\nWCF foundation setup complete.\n";
