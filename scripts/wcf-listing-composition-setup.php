<?php

/**
 * @file
 * WCF listing, discovery, and editorial composition setup.
 *
 * Creates:
 * - stallions View (page + block_featured)
 * - featured_stallions paragraph type
 * - homepage field_feature_sections
 *
 * Run: ddev drush php:script scripts/wcf-listing-composition-setup.php
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\views\Entity\View;

/**
 * Creates field storage when missing.
 */
function wcf_listing_ensure_field_storage(array $values): FieldStorageConfig {
  $storage = FieldStorageConfig::loadByName($values['entity_type'], $values['field_name']);
  if ($storage) {
    return $storage;
  }
  $storage = FieldStorageConfig::create($values);
  $storage->save();
  print "Created field storage: {$values['entity_type']}.{$values['field_name']}\n";
  return $storage;
}

/**
 * Creates a field instance when missing.
 */
function wcf_listing_ensure_field_instance(array $values): FieldConfig {
  $field = FieldConfig::loadByName($values['entity_type'], $values['bundle'], $values['field_name']);
  if ($field) {
    return $field;
  }
  $field = FieldConfig::create($values);
  $field->save();
  print "Created field: {$values['entity_type']}.{$values['bundle']}.{$values['field_name']}\n";
  return $field;
}

/**
 * Configures a form display.
 */
function wcf_listing_configure_form_display(string $entity_type, string $bundle, array $components, string $mode = 'default'): void {
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
 * Configures a view display.
 */
function wcf_listing_configure_view_display(string $entity_type, string $bundle, array $components, array $hidden = [], string $mode = 'default'): void {
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
  foreach ($hidden as $field_name) {
    $display->removeComponent($field_name);
  }
  $display->save();
}

/**
 * Ensures featured_stallions paragraph type and fields exist.
 */
function wcf_listing_setup_featured_stallions_paragraph(): void {
  if (!ParagraphsType::load('featured_stallions')) {
    ParagraphsType::create([
      'id' => 'featured_stallions',
      'label' => 'Featured stallions',
      'description' => 'Section that embeds the Featured Stallions listing block.',
    ])->save();
    print "Created paragraph type: featured_stallions\n";
  }

  wcf_listing_ensure_field_storage([
    'field_name' => 'field_featured_count',
    'entity_type' => 'paragraph',
    'type' => 'integer',
    'settings' => [
      'unsigned' => FALSE,
      'size' => 'normal',
      'min' => 1,
      'max' => 12,
      'prefix' => '',
      'suffix' => '',
    ],
    'cardinality' => 1,
  ]);

  wcf_listing_ensure_field_instance([
    'field_name' => 'field_title',
    'entity_type' => 'paragraph',
    'bundle' => 'featured_stallions',
    'label' => 'Title',
    'required' => TRUE,
  ]);

  wcf_listing_ensure_field_instance([
    'field_name' => 'field_description',
    'entity_type' => 'paragraph',
    'bundle' => 'featured_stallions',
    'label' => 'Description',
    'required' => FALSE,
  ]);

  wcf_listing_ensure_field_instance([
    'field_name' => 'field_featured_count',
    'entity_type' => 'paragraph',
    'bundle' => 'featured_stallions',
    'label' => 'Number to show',
    'description' => 'How many featured stallions to display (1–12).',
    'required' => TRUE,
    'default_value' => [['value' => 3]],
  ]);

  wcf_listing_configure_form_display('paragraph', 'featured_stallions', [
    'field_title' => ['type' => 'string_textfield', 'weight' => 0, 'region' => 'content'],
    'field_description' => ['type' => 'text_textarea', 'weight' => 1, 'region' => 'content'],
    'field_featured_count' => ['type' => 'number', 'weight' => 2, 'region' => 'content'],
  ]);

  wcf_listing_configure_view_display('paragraph', 'featured_stallions', [
    'field_title' => [
      'type' => 'string',
      'label' => 'hidden',
      'weight' => 0,
      'region' => 'content',
      'settings' => ['link_to_entity' => FALSE],
    ],
    'field_description' => [
      'type' => 'text_default',
      'label' => 'hidden',
      'weight' => 1,
      'region' => 'content',
    ],
  ], ['field_featured_count']);
}

/**
 * Ensures homepage field_feature_sections and migrates legacy feature cards.
 */
function wcf_listing_setup_homepage_feature_sections(): void {
  wcf_listing_ensure_field_storage([
    'field_name' => 'field_feature_sections',
    'entity_type' => 'node',
    'type' => 'entity_reference_revisions',
    'settings' => ['target_type' => 'paragraph'],
    'cardinality' => -1,
  ]);

  wcf_listing_ensure_field_instance([
    'field_name' => 'field_feature_sections',
    'entity_type' => 'node',
    'bundle' => 'homepage',
    'label' => 'Feature sections',
    'description' => 'Ordered homepage sections: featured stallions grids and feature cards.',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'target_bundles' => [
          'featured_stallions' => 'featured_stallions',
          'feature_card' => 'feature_card',
          'testimonial_item' => 'testimonial_item',
        ],
        'negate' => 0,
        'target_bundles_drag_drop' => [
          'featured_stallions' => ['weight' => 0, 'enabled' => TRUE],
          'feature_card' => ['weight' => 1, 'enabled' => TRUE],
          'testimonial_item' => ['weight' => 2, 'enabled' => TRUE],
          'hero_slide' => ['weight' => 3, 'enabled' => FALSE],
        ],
      ],
    ],
  ]);

  $homepage_form = EntityFormDisplay::load('node.homepage.default');
  if ($homepage_form) {
    $homepage_form->removeComponent('field_feature_cards');
    $homepage_form->save();
    print "Hidden legacy field_feature_cards on homepage form.\n";
  }

  wcf_listing_configure_form_display('node', 'homepage', [
    'field_feature_sections' => [
      'type' => 'paragraphs',
      'weight' => 15,
      'region' => 'content',
      'settings' => [
        'title' => 'Feature section',
        'title_plural' => 'Feature sections',
        'edit_mode' => 'open',
        'closed_mode' => 'summary',
        'autocollapse' => 'none',
        'closed_mode_threshold' => 0,
        'add_mode' => 'dropdown',
        'form_display_mode' => 'default',
        'default_paragraph_type' => 'feature_card',
        'features' => [
          'add_above' => 'add_above',
          'collapse_edit_all' => 'collapse_edit_all',
          'duplicate' => 'duplicate',
        ],
      ],
    ],
  ]);

  foreach (['default', 'full'] as $mode) {
    $display = EntityViewDisplay::load("node.homepage.{$mode}");
    if (!$display) {
      continue;
    }
    $display->setComponent('field_feature_sections', [
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'hidden',
      'weight' => 9,
      'region' => 'content',
      'settings' => [
        'view_mode' => 'default',
        'link' => '',
      ],
    ]);
    $display->removeComponent('field_feature_cards');
    $display->save();
    print "Updated node.homepage.{$mode} display.\n";
  }

  $homepages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'homepage',
  ]);
  foreach ($homepages as $homepage) {
    if (!$homepage instanceof Node) {
      continue;
    }
    if (!$homepage->get('field_feature_sections')->isEmpty()) {
      continue;
    }
    if ($homepage->get('field_feature_cards')->isEmpty()) {
      continue;
    }
    $homepage->set('field_feature_sections', $homepage->get('field_feature_cards')->getValue());
    $homepage->set('field_feature_cards', []);
    $homepage->save();
    print "Migrated feature cards to feature sections on homepage nid {$homepage->id()}.\n";
  }
}

/**
 * Creates or updates the stallions View.
 */
function wcf_listing_setup_stallions_view(): void {
  $view = View::load('stallions');
  if ($view) {
    print "View stallions already exists — updating displays.\n";
  }
  else {
    $view = View::create([
      'id' => 'stallions',
      'label' => 'Stallions',
      'module' => 'node',
      'description' => 'Stallion discovery listing with card rendering.',
      'tag' => 'wcf',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
    ]);
  }

  $default_options = $view->getDisplay('default')['display_options'] ?? [];

  $default_options['title'] = 'Stallions';
  $default_options['css_class'] = 'stallion-listing';
  $default_options['access'] = [
    'type' => 'perm',
    'options' => ['perm' => 'access content'],
  ];
  $default_options['cache'] = ['type' => 'tag', 'options' => []];
  $default_options['exposed_form'] = [
    'type' => 'basic',
    'options' => [
      'submit_button' => 'Apply',
      'reset_button' => TRUE,
      'reset_button_label' => 'Reset',
      'exposed_sorts_label' => 'Sort by',
      'expose_sort_order' => FALSE,
    ],
  ];
  $default_options['pager'] = [
    'type' => 'full',
    'options' => [
      'offset' => 0,
      'pagination_heading_level' => 'h2',
      'items_per_page' => 12,
      'tags' => [
        'next' => 'Next ›',
        'previous' => '‹ Previous',
      ],
    ],
  ];
  $default_options['style'] = [
    'type' => 'html_list',
    'options' => [
      'type' => 'ul',
      'class' => 'stallion-listing__grid',
      'wrapper_class' => 'stallion-listing__results',
    ],
  ];
  $default_options['row'] = [
    'type' => 'entity:node',
    'options' => ['view_mode' => 'card'],
  ];
  $default_options['filters'] = [
    'status' => [
      'id' => 'status',
      'table' => 'node_field_data',
      'field' => 'status',
      'entity_type' => 'node',
      'entity_field' => 'status',
      'plugin_id' => 'boolean',
      'value' => '1',
      'group' => 1,
    ],
    'type' => [
      'id' => 'type',
      'table' => 'node_field_data',
      'field' => 'type',
      'entity_type' => 'node',
      'entity_field' => 'type',
      'plugin_id' => 'bundle',
      'value' => ['stallion' => 'stallion'],
      'group' => 1,
    ],
    'field_status_value' => [
      'id' => 'field_status_value',
      'table' => 'node__field_status',
      'field' => 'field_status_value',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'list_field',
      'operator' => 'or',
      'value' => [],
      'group' => 1,
      'exposed' => TRUE,
      'expose' => [
        'operator_id' => 'field_status_value_op',
        'label' => 'Status',
        'description' => '',
        'use_operator' => FALSE,
        'operator' => 'field_status_value_op',
        'identifier' => 'stallion_status',
        'required' => FALSE,
        'remember' => FALSE,
        'multiple' => FALSE,
      ],
      'is_grouped' => FALSE,
    ],
    'field_category_target_id' => [
      'id' => 'field_category_target_id',
      'table' => 'node__field_category',
      'field' => 'field_category_target_id',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'taxonomy_index_tid',
      'operator' => 'or',
      'value' => [],
      'group' => 1,
      'exposed' => TRUE,
      'expose' => [
        'operator_id' => 'field_category_target_id_op',
        'label' => 'Category',
        'description' => '',
        'use_operator' => FALSE,
        'operator' => 'field_category_target_id_op',
        'identifier' => 'stallion_category',
        'required' => FALSE,
        'remember' => FALSE,
        'multiple' => FALSE,
      ],
      'is_grouped' => FALSE,
      'vid' => 'categories',
      'type' => 'select',
      'limit' => TRUE,
    ],
  ];
  $default_options['sorts'] = [
    'field_featured_value' => [
      'id' => 'field_featured_value',
      'table' => 'node__field_featured',
      'field' => 'field_featured_value',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'standard',
      'order' => 'DESC',
      'exposed' => FALSE,
    ],
    'created' => [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'entity_type' => 'node',
      'entity_field' => 'created',
      'plugin_id' => 'date',
      'order' => 'DESC',
      'exposed' => FALSE,
    ],
  ];
  $default_options['empty'] = [
    'area_text_custom' => [
      'id' => 'area_text_custom',
      'table' => 'views',
      'field' => 'area_text_custom',
      'plugin_id' => 'text_custom',
      'empty' => TRUE,
      'content' => '<p>No stallions match your filters.</p>',
    ],
  ];

  $default_display =& $view->getDisplay('default');
  $default_display['display_options'] = array_replace_recursive(
    $default_display['display_options'] ?? [],
    $default_options
  );

  if (!$view->getDisplay('page_1')) {
    $view->addDisplay('page', 'Page', 'page_1');
  }
  $page =& $view->getDisplay('page_1');
  $page['display_options']['path'] = 'stallions';
  $page['display_options']['menu'] = [
    'type' => 'normal',
    'title' => 'Stallions',
    'weight' => 5,
  ];

  if (!$view->getDisplay('block_featured')) {
    $view->addDisplay('block', 'Featured Stallions', 'block_featured');
  }
  $block =& $view->getDisplay('block_featured');
  $block['display_options']['title'] = 'Featured Stallions';
  $block['display_options']['pager'] = [
    'type' => 'some',
    'options' => ['items_per_page' => 3, 'offset' => 0],
  ];
  $block['display_options']['filters'] = $default_options['filters'];
  $block['display_options']['filters']['field_featured_value'] = [
    'id' => 'field_featured_value',
    'table' => 'node__field_featured',
    'field' => 'field_featured_value',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'boolean',
    'operator' => '=',
    'value' => '1',
    'group' => 1,
    'exposed' => FALSE,
  ];
  unset($block['display_options']['filters']['field_status_value']);
  unset($block['display_options']['filters']['field_category_target_id']);
  $block['display_options']['exposed_form'] = ['type' => 'basic', 'options' => []];
  $block['display_options']['style'] = $default_options['style'];
  $block['display_options']['row'] = $default_options['row'];

  $view->save();
  print "Saved View: stallions\n";
}

// ---------------------------------------------------------------------------
// Execute
// ---------------------------------------------------------------------------
if (!\Drupal::moduleHandler()->moduleExists('views')) {
  throw new \RuntimeException('Views module must be enabled.');
}
if (!\Drupal::moduleHandler()->moduleExists('paragraphs')) {
  throw new \RuntimeException('Paragraphs module must be enabled.');
}
if (!\Drupal::entityTypeManager()->getStorage('node_type')->load('stallion')) {
  throw new \RuntimeException('Stallion content type is missing. Run wcf-business-content-setup.php first.');
}

wcf_listing_setup_featured_stallions_paragraph();
wcf_listing_setup_homepage_feature_sections();
wcf_listing_setup_stallions_view();

print "WCF listing and composition setup complete.\n";
