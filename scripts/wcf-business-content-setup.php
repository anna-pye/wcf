<?php

/**
 * @file
 * WCF Drupal 11 business content architecture setup.
 *
 * Establishes stallion content type, testimonial_item and feature_card
 * paragraph types, view modes, image styles, and editorial displays.
 *
 * Run: ddev drush php:script scripts/wcf-business-content-setup.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Creates a field storage if it does not exist.
 */
function wcf_business_ensure_field_storage(array $values): FieldStorageConfig {
  $storage = FieldStorageConfig::loadByName($values['entity_type'], $values['field_name']);
  if ($storage) {
    return $storage;
  }
  return FieldStorageConfig::create($values);
}

/**
 * Creates a field instance if it does not exist.
 */
function wcf_business_ensure_field_instance(array $values): FieldConfig {
  $field = FieldConfig::loadByName($values['entity_type'], $values['bundle'], $values['field_name']);
  if ($field) {
    if (!empty($values['description']) && $field->getDescription() !== $values['description']) {
      $field->setDescription($values['description']);
      $field->save();
    }
    return $field;
  }
  return FieldConfig::create($values);
}

/**
 * Ensures form display has components for the given fields.
 */
function wcf_business_configure_form_display(string $entity_type, string $bundle, array $components, string $mode = 'default'): void {
  $display = EntityFormDisplay::load("{$entity_type}.{$bundle}.{$mode}");
  if (!$display) {
    $display = EntityFormDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
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
function wcf_business_configure_view_display(string $entity_type, string $bundle, array $components, string $mode = 'default'): void {
  $display = EntityViewDisplay::load("{$entity_type}.{$bundle}.{$mode}");
  if (!$display) {
    $display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
      'status' => TRUE,
    ]);
  }
  foreach ($components as $field_name => $component) {
    $display->setComponent($field_name, $component);
  }
  $display->save();
}

/**
 * Ensures an entity view mode exists.
 */
function wcf_business_ensure_view_mode(string $entity_type, string $mode, string $label, string $description = ''): void {
  $id = "{$entity_type}.{$mode}";
  if (EntityViewMode::load($id)) {
    return;
  }
  EntityViewMode::create([
    'id' => $id,
    'targetEntityType' => $entity_type,
    'status' => TRUE,
    'label' => $label,
    'description' => $description,
    'cache' => TRUE,
  ])->save();
  print "Created view mode: {$id}\n";
}

/**
 * Ensures an image style exists with scale-width effect.
 */
function wcf_business_ensure_image_style(string $id, string $label, int $width): ImageStyle {
  $style = ImageStyle::load($id);
  if ($style) {
    return $style;
  }
  $style = ImageStyle::create(['name' => $id, 'label' => $label]);
  $style->addImageEffect([
    'id' => 'image_scale',
    'weight' => 0,
    'data' => ['width' => $width, 'height' => NULL, 'upscale' => FALSE],
  ]);
  $style->save();
  print "Created image style: {$id}\n";
  return $style;
}

// ---------------------------------------------------------------------------
// Prerequisites
// ---------------------------------------------------------------------------
if (!Vocabulary::load('categories')) {
  throw new \RuntimeException('Categories vocabulary (categories) is missing. Import config/sync or run wcf-governed-categories.php first.');
}

$module_installer = \Drupal::service('module_installer');
if (!\Drupal::moduleHandler()->moduleExists('telephone')) {
  $module_installer->install(['telephone'], TRUE);
  print "Enabled module: telephone\n";
}

if (!\Drupal::moduleHandler()->moduleExists('options')) {
  throw new \RuntimeException('Options module is required for field_status.');
}

// ---------------------------------------------------------------------------
// Phase 1: View modes and responsive image styles
// ---------------------------------------------------------------------------
wcf_business_ensure_view_mode('node', 'card', 'Card', 'Compact card layout for listings.');
wcf_business_ensure_view_mode('node', 'hero', 'Hero', 'Prominent hero layout for featured content.');
wcf_business_ensure_view_mode('media', 'card', 'Card', 'Card-sized media rendering.');
wcf_business_ensure_view_mode('media', 'teaser', 'Teaser', 'Small teaser media rendering.');

wcf_business_ensure_image_style('card_desktop', 'Card desktop (600)', 600);
wcf_business_ensure_image_style('card_mobile', 'Card mobile (400)', 400);
wcf_business_ensure_image_style('teaser_thumb', 'Teaser thumbnail (300)', 300);

if (!ResponsiveImageStyle::load('card')) {
  ResponsiveImageStyle::create([
    'id' => 'card',
    'label' => 'Card',
    'image_style_mappings' => [
      [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 768px) 600px, 100vw',
          'sizes_image_styles' => ['card_desktop', 'card_mobile'],
        ],
        'breakpoint_id' => 'responsive_image.viewport_sizing',
        'multiplier' => '1x',
      ],
    ],
    'fallback_image_style' => 'card_mobile',
    'breakpoint_group' => 'responsive_image',
  ])->save();
  print "Created responsive image style: card\n";
}

// Media view displays for card and teaser.
wcf_business_configure_view_display('media', 'image', [
  'field_media_image' => [
    'type' => 'responsive_image',
    'label' => 'visually_hidden',
    'weight' => 0,
    'settings' => [
      'responsive_image_style' => 'card',
      'image_link' => '',
      'image_loading' => ['attribute' => 'lazy'],
    ],
    'region' => 'content',
  ],
], 'card');

wcf_business_configure_view_display('media', 'image', [
  'field_media_image' => [
    'type' => 'image',
    'label' => 'visually_hidden',
    'weight' => 0,
    'settings' => [
      'image_style' => 'teaser_thumb',
      'image_link' => '',
      'image_loading' => ['attribute' => 'lazy'],
    ],
    'region' => 'content',
  ],
], 'teaser');

// ---------------------------------------------------------------------------
// Phase 2: Stallion content type
// ---------------------------------------------------------------------------
if (!NodeType::load('stallion')) {
  NodeType::create([
    'type' => 'stallion',
    'name' => 'Stallion',
    'description' => 'Horse and stallion business profiles. Modern replacement for legacy product/stallion content.',
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => FALSE,
  ])->save();
  node_add_body_field(NodeType::load('stallion'));
  print "Created content type: stallion\n";
}
else {
  print "Content type already exists: stallion\n";
}

// Reuse existing media field storages where possible.
wcf_business_ensure_field_storage([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Main image',
  'description' => 'Primary portrait or hero image. Select from the Media Library.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => -1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Gallery',
  'description' => 'Additional images shown in the profile gallery.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_status',
  'entity_type' => 'node',
  'type' => 'list_string',
  'cardinality' => 1,
  'settings' => [
    'allowed_values' => [
      'active' => 'Active',
      'sold' => 'Sold',
      'retired' => 'Retired',
    ],
  ],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_status',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Status',
  'description' => 'Current availability or standing of this stallion.',
  'required' => TRUE,
  'translatable' => TRUE,
  'default_value' => [['value' => 'active']],
  'settings' => [],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_video',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_video',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Video',
  'description' => 'YouTube or Vimeo video via Remote video media. Do not paste embed HTML.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['remote_video' => 'remote_video'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_cta_phone',
  'entity_type' => 'node',
  'type' => 'telephone',
  'cardinality' => 1,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_cta_phone',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Enquiry phone',
  'description' => 'Click-to-call number shown on the stallion profile.',
  'required' => FALSE,
  'translatable' => TRUE,
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_featured',
  'entity_type' => 'node',
  'type' => 'boolean',
  'cardinality' => 1,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_featured',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Featured',
  'description' => 'Highlight this stallion in listings and homepage sections.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => [
    'on_label' => 'Featured',
    'off_label' => 'Not featured',
  ],
])->save();

if (!FieldStorageConfig::loadByName('node', 'field_category')) {
  wcf_business_ensure_field_storage([
    'field_name' => 'field_category',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
}

wcf_business_ensure_field_instance([
  'field_name' => 'field_category',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Category',
  'description' => 'Select a governed category from the approved list.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['categories' => 'categories'],
      'sort' => ['field' => 'name', 'direction' => 'asc'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_documents',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'cardinality' => -1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_documents',
  'entity_type' => 'node',
  'bundle' => 'stallion',
  'label' => 'Documents',
  'description' => 'Pedigree PDFs, sale sheets, and other downloadable documents.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['document' => 'document'],
      'sort' => ['field' => '_none'],
      'auto_create' => FALSE,
    ],
  ],
])->save();

// Stallion form display — grouped by weight (content → media → business → publishing).
wcf_business_configure_form_display('node', 'stallion', [
  'title' => [
    'type' => 'string_textfield',
    'weight' => 0,
    'settings' => ['size' => 60, 'placeholder' => ''],
    'region' => 'content',
  ],
  'body' => [
    'type' => 'text_textarea_with_summary',
    'weight' => 1,
    'settings' => ['rows' => 9, 'summary_rows' => 3, 'placeholder' => '', 'show_summary' => TRUE],
    'region' => 'content',
  ],
  'field_main_image' => [
    'type' => 'media_library_widget',
    'weight' => 10,
    'settings' => ['media_types' => ['image']],
    'region' => 'content',
  ],
  'field_gallery' => [
    'type' => 'media_library_widget',
    'weight' => 11,
    'settings' => ['media_types' => ['image']],
    'region' => 'content',
  ],
  'field_video' => [
    'type' => 'media_library_widget',
    'weight' => 12,
    'settings' => ['media_types' => ['remote_video']],
    'region' => 'content',
  ],
  'field_documents' => [
    'type' => 'media_library_widget',
    'weight' => 13,
    'settings' => ['media_types' => ['document']],
    'region' => 'content',
  ],
  'field_status' => [
    'type' => 'options_select',
    'weight' => 20,
    'settings' => [],
    'region' => 'content',
  ],
  'field_featured' => [
    'type' => 'boolean_checkbox',
    'weight' => 21,
    'settings' => ['display_label' => TRUE],
    'region' => 'content',
  ],
  'field_cta_phone' => [
    'type' => 'telephone_default',
    'weight' => 22,
    'settings' => ['placeholder' => ''],
    'region' => 'content',
  ],
  'field_category' => [
    'type' => 'options_select',
    'weight' => 23,
    'settings' => [],
    'region' => 'content',
  ],
  'path' => ['type' => 'path', 'weight' => 30, 'region' => 'content'],
  'url_redirects' => ['weight' => 50, 'region' => 'content'],
  'status' => [
    'type' => 'boolean_checkbox',
    'weight' => 120,
    'settings' => ['display_label' => TRUE],
    'region' => 'content',
  ],
]);

$stallion_media_formatter = [
  'type' => 'entity_reference_entity_view',
  'label' => 'hidden',
  'settings' => ['view_mode' => 'default', 'link' => FALSE],
  'region' => 'content',
];

wcf_business_configure_view_display('node', 'stallion', [
  'body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 1, 'region' => 'content'],
  'field_main_image' => array_merge($stallion_media_formatter, ['weight' => 0, 'settings' => ['view_mode' => 'default', 'link' => FALSE]]),
  'field_gallery' => array_merge($stallion_media_formatter, ['weight' => 2, 'label' => 'above']),
  'field_video' => array_merge($stallion_media_formatter, ['weight' => 3]),
  'field_documents' => array_merge($stallion_media_formatter, ['weight' => 4, 'label' => 'above']),
  'field_status' => ['type' => 'list_default', 'label' => 'inline', 'weight' => 5, 'region' => 'content'],
  'field_cta_phone' => ['type' => 'telephone_link', 'label' => 'inline', 'weight' => 6, 'region' => 'content'],
  'field_category' => ['type' => 'entity_reference_label', 'label' => 'above', 'weight' => 7, 'settings' => ['link' => TRUE], 'region' => 'content'],
  'links' => ['weight' => 100, 'region' => 'content'],
]);

// Teaser view mode.
wcf_business_configure_view_display('node', 'stallion', [
  'field_main_image' => [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'teaser', 'link' => FALSE],
    'region' => 'content',
  ],
  'body' => [
    'type' => 'text_summary_or_trimmed',
    'label' => 'hidden',
    'weight' => 1,
    'settings' => ['trim_length' => 200],
    'region' => 'content',
  ],
  'field_status' => ['type' => 'list_default', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
], 'teaser');

// Card view mode.
wcf_business_configure_view_display('node', 'stallion', [
  'field_main_image' => [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'card', 'link' => FALSE],
    'region' => 'content',
  ],
  'body' => [
    'type' => 'text_summary_or_trimmed',
    'label' => 'hidden',
    'weight' => 1,
    'settings' => ['trim_length' => 120],
    'region' => 'content',
  ],
  'field_status' => ['type' => 'list_default', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
  'field_featured' => ['type' => 'boolean', 'label' => 'hidden', 'weight' => 3, 'region' => 'content'],
], 'card');

// Hero view mode.
wcf_business_configure_view_display('node', 'stallion', [
  'field_main_image' => [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'hero', 'link' => FALSE],
    'region' => 'content',
  ],
  'body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 1, 'region' => 'content'],
  'field_status' => ['type' => 'list_default', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
  'field_cta_phone' => ['type' => 'telephone_link', 'label' => 'hidden', 'weight' => 3, 'region' => 'content'],
], 'hero');

print "Configured stallion content type\n";

// Pathauto for stallions.
if (!PathautoPattern::load('node_stallion')) {
  $pattern = PathautoPattern::create([
    'id' => 'node_stallion',
    'label' => 'Content Stallion',
    'type' => 'canonical_entities:node',
    'pattern' => 'stallions/[node:title]',
    'weight' => -5,
  ]);
  $pattern->addSelectionCondition([
    'id' => 'entity_bundle:node',
    'bundles' => ['stallion' => 'stallion'],
    'negate' => FALSE,
    'context_mapping' => ['node' => 'node'],
  ]);
  $pattern->save();
  print "Created pathauto pattern: node_stallion\n";
}

// Metatag defaults for stallion bundle.
$stallion_metatag = MetatagDefaults::load('node__stallion');
if (!$stallion_metatag) {
  $stallion_metatag = MetatagDefaults::create([
    'id' => 'node__stallion',
    'label' => 'Content: Stallion',
    'tags' => [
      'description' => '[node:summary]',
      'og_title' => '[node:title]',
      'og_description' => '[node:summary]',
      'og_url' => '[node:url:absolute]',
      'og_type' => 'article',
    ],
  ]);
  $stallion_metatag->save();
  print "Created metatag defaults: node__stallion\n";
}

// ---------------------------------------------------------------------------
// Phase 3: testimonial_item paragraph
// ---------------------------------------------------------------------------
if (!ParagraphsType::load('testimonial_item')) {
  ParagraphsType::create([
    'id' => 'testimonial_item',
    'label' => 'Testimonial',
    'description' => 'Customer or client testimonial quote with attribution.',
  ])->save();
  print "Created paragraph type: testimonial_item\n";
}

wcf_business_ensure_field_storage([
  'field_name' => 'field_quote',
  'entity_type' => 'paragraph',
  'type' => 'text_long',
  'cardinality' => 1,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_quote',
  'entity_type' => 'paragraph',
  'bundle' => 'testimonial_item',
  'label' => 'Quote',
  'description' => 'The testimonial text. Use plain language; avoid HTML.',
  'required' => TRUE,
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_author_name',
  'entity_type' => 'paragraph',
  'type' => 'string',
  'cardinality' => 1,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_author_name',
  'entity_type' => 'paragraph',
  'bundle' => 'testimonial_item',
  'label' => 'Author name',
  'description' => 'Name of the person giving the testimonial.',
  'required' => TRUE,
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_author_role',
  'entity_type' => 'paragraph',
  'type' => 'string',
  'cardinality' => 1,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_author_role',
  'entity_type' => 'paragraph',
  'bundle' => 'testimonial_item',
  'label' => 'Author role',
  'description' => 'Optional role, location, or affiliation (e.g. "Stud principal, NSW").',
  'required' => FALSE,
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_author_image',
  'entity_type' => 'paragraph',
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_author_image',
  'entity_type' => 'paragraph',
  'bundle' => 'testimonial_item',
  'label' => 'Author image',
  'description' => 'Optional portrait from the Media Library.',
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

wcf_business_configure_form_display('paragraph', 'testimonial_item', [
  'field_quote' => [
    'type' => 'text_textarea',
    'weight' => 0,
    'settings' => ['rows' => 4, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_author_name' => [
    'type' => 'string_textfield',
    'weight' => 1,
    'settings' => ['size' => 60, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_author_role' => [
    'type' => 'string_textfield',
    'weight' => 2,
    'settings' => ['size' => 60, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_author_image' => [
    'type' => 'media_library_widget',
    'weight' => 3,
    'settings' => ['media_types' => ['image']],
    'region' => 'content',
  ],
]);

wcf_business_configure_view_display('paragraph', 'testimonial_item', [
  'field_quote' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0, 'region' => 'content'],
  'field_author_name' => ['type' => 'string', 'label' => 'hidden', 'weight' => 1, 'region' => 'content'],
  'field_author_role' => ['type' => 'string', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
  'field_author_image' => [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 3,
    'settings' => ['view_mode' => 'teaser', 'link' => FALSE],
    'region' => 'content',
  ],
]);

print "Configured testimonial_item paragraph\n";

// ---------------------------------------------------------------------------
// Phase 4: feature_card paragraph (reuses hero_slide field storages)
// ---------------------------------------------------------------------------
if (!ParagraphsType::load('feature_card')) {
  ParagraphsType::create([
    'id' => 'feature_card',
    'label' => 'Feature card',
    'description' => 'Promotional feature strip with image, text, and CTA. Replaces legacy showcase/banner strips.',
  ])->save();
  print "Created paragraph type: feature_card\n";
}

foreach (['field_title', 'field_description', 'field_image', 'field_cta_text', 'field_cta_url'] as $field_name) {
  if (!FieldStorageConfig::loadByName('paragraph', $field_name)) {
    throw new \RuntimeException("Expected paragraph field storage {$field_name} is missing. Run foundation setup first.");
  }
}

wcf_business_ensure_field_instance([
  'field_name' => 'field_title',
  'entity_type' => 'paragraph',
  'bundle' => 'feature_card',
  'label' => 'Title',
  'description' => 'Headline for this feature card.',
  'required' => TRUE,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_description',
  'entity_type' => 'paragraph',
  'bundle' => 'feature_card',
  'label' => 'Description',
  'description' => 'Short supporting text.',
  'required' => FALSE,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_image',
  'entity_type' => 'paragraph',
  'bundle' => 'feature_card',
  'label' => 'Image',
  'description' => 'Feature image from the Media Library.',
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

wcf_business_ensure_field_instance([
  'field_name' => 'field_cta_text',
  'entity_type' => 'paragraph',
  'bundle' => 'feature_card',
  'label' => 'CTA text',
  'description' => 'Button or link label.',
  'required' => FALSE,
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_cta_url',
  'entity_type' => 'paragraph',
  'bundle' => 'feature_card',
  'label' => 'CTA URL',
  'description' => 'Destination URL for the call to action.',
  'required' => FALSE,
  'settings' => ['title' => 0, 'link_type' => 16],
])->save();

wcf_business_configure_form_display('paragraph', 'feature_card', [
  'field_title' => [
    'type' => 'string_textfield',
    'weight' => 0,
    'settings' => ['size' => 60, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_image' => [
    'type' => 'media_library_widget',
    'weight' => 1,
    'settings' => ['media_types' => ['image']],
    'region' => 'content',
  ],
  'field_description' => [
    'type' => 'text_textarea',
    'weight' => 2,
    'settings' => ['rows' => 4, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_cta_text' => [
    'type' => 'string_textfield',
    'weight' => 3,
    'settings' => ['size' => 60, 'placeholder' => ''],
    'region' => 'content',
  ],
  'field_cta_url' => [
    'type' => 'link_default',
    'weight' => 4,
    'settings' => ['placeholder_url' => '', 'placeholder_title' => ''],
    'region' => 'content',
  ],
]);

wcf_business_configure_view_display('paragraph', 'feature_card', [
  'field_title' => ['type' => 'string', 'label' => 'hidden', 'weight' => 1, 'region' => 'content'],
  'field_image' => [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'card', 'link' => FALSE],
    'region' => 'content',
  ],
  'field_description' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 2, 'region' => 'content'],
]);

print "Configured feature_card paragraph\n";

// ---------------------------------------------------------------------------
// Phase 5: Homepage paragraph reference fields (editorial sections)
// ---------------------------------------------------------------------------
wcf_business_ensure_field_storage([
  'field_name' => 'field_testimonials',
  'entity_type' => 'node',
  'type' => 'entity_reference_revisions',
  'cardinality' => -1,
  'settings' => ['target_type' => 'paragraph'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_testimonials',
  'entity_type' => 'node',
  'bundle' => 'homepage',
  'label' => 'Testimonials',
  'description' => 'Customer testimonials displayed on the homepage.',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => ['testimonial_item' => 'testimonial_item'],
      'negate' => 0,
      'target_bundles_drag_drop' => [
        'testimonial_item' => ['enabled' => TRUE, 'weight' => 0],
        'hero_slide' => ['enabled' => FALSE, 'weight' => 1],
        'feature_card' => ['enabled' => FALSE, 'weight' => 2],
      ],
    ],
  ],
])->save();

wcf_business_ensure_field_storage([
  'field_name' => 'field_feature_cards',
  'entity_type' => 'node',
  'type' => 'entity_reference_revisions',
  'cardinality' => -1,
  'settings' => ['target_type' => 'paragraph'],
])->save();

wcf_business_ensure_field_instance([
  'field_name' => 'field_feature_cards',
  'entity_type' => 'node',
  'bundle' => 'homepage',
  'label' => 'Feature cards',
  'description' => 'Promotional feature strips for services or highlights.',
  'required' => FALSE,
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => ['feature_card' => 'feature_card'],
      'negate' => 0,
      'target_bundles_drag_drop' => [
        'feature_card' => ['enabled' => TRUE, 'weight' => 0],
        'hero_slide' => ['enabled' => FALSE, 'weight' => 1],
        'testimonial_item' => ['enabled' => FALSE, 'weight' => 2],
      ],
    ],
  ],
])->save();

// Extend homepage displays.
$homepage_form = EntityFormDisplay::load('node.homepage.default');
if ($homepage_form) {
  $homepage_form->setComponent('field_testimonials', [
    'type' => 'paragraphs',
    'weight' => 20,
    'settings' => [
      'title' => 'Testimonial',
      'title_plural' => 'Testimonials',
      'edit_mode' => 'open',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'testimonial_item',
    ],
    'region' => 'content',
  ]);
  $homepage_form->setComponent('field_feature_cards', [
    'type' => 'paragraphs',
    'weight' => 21,
    'settings' => [
      'title' => 'Feature card',
      'title_plural' => 'Feature cards',
      'edit_mode' => 'open',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'feature_card',
    ],
    'region' => 'content',
  ]);
  $homepage_form->save();
}

$homepage_view = EntityViewDisplay::load('node.homepage.default');
if ($homepage_view) {
  $homepage_view->setComponent('field_testimonials', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 10,
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'region' => 'content',
  ]);
  $homepage_view->setComponent('field_feature_cards', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 11,
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'region' => 'content',
  ]);
  $homepage_view->save();
}

print "Extended homepage with testimonials and feature cards\n";

$homepage_full = EntityViewDisplay::load('node.homepage.full');
if ($homepage_full) {
  $homepage_full->setComponent('field_testimonials', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 11,
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'region' => 'content',
  ]);
  $homepage_full->setComponent('field_feature_cards', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 10,
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'region' => 'content',
  ]);
  $homepage_full->removeComponent('links');
  $homepage_full->save();
  print "Updated homepage full view display\n";
}

$stallion_form = EntityFormDisplay::load('node.stallion.default');
if ($stallion_form) {
  $stallion_form->removeComponent('promote');
  $stallion_form->removeComponent('sticky');
  $stallion_form->save();
}

print "\nWCF business content setup complete.\n";
