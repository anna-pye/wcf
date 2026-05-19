<?php

/**
 * @file
 * WCF discovery intelligence layer: facets, governance views, featured content.
 *
 * Run after enabling modules:
 *   ddev drush en facets facets_summary -y
 *   ddev drush php:script scripts/wcf-discovery-setup.php
 *   ddev drush cex -y
 */

declare(strict_types=1);

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\facets\Entity\Facet;
use Drupal\facets_summary\Entity\FacetsSummary;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\search_api\Entity\Index;
use Drupal\views\Entity\View;

/**
 * Prints a status line.
 */
function wcf_disc_print(string $message): void {
  print $message . "\n";
}

/**
 * Facet source ID for the site search page display.
 */
function wcf_disc_facet_source_id(): string {
  return 'search_api:views_page__search_stallions__page_1';
}

/**
 * Creates or updates a Search API facet.
 */
function wcf_disc_ensure_facet(
  string $id,
  string $name,
  string $field_identifier,
  int $weight,
  array $extra_processors = [],
): void {
  $source = wcf_disc_facet_source_id();
  $processors = [
    'url_processor_handler' => [
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ],
  ];
  $processors += $extra_processors;

  $facet = Facet::load($id);
  if (!$facet) {
    $facet = Facet::create([
      'id' => $id,
      'name' => $name,
      'weight' => $weight,
      'use_hierarchy' => FALSE,
      'hierarchy' => ['type' => 'taxonomy', 'config' => []],
    ]);
    wcf_disc_print("Created facet: {$id}");
  }
  else {
    wcf_disc_print("Updated facet: {$id}");
  }

  $facet->setFacetSourceId($source);
  $facet->setFieldIdentifier($field_identifier);
  $facet->setUrlAlias($id);
  $facet->setWidget('links', [
    'show_numbers' => TRUE,
    'soft_limit' => 0,
  ]);
  $facet->setQueryOperator('or');
  $facet->setEmptyBehavior(['behavior' => 'none']);
  $facet->setOnlyVisibleWhenFacetSourceIsVisible(TRUE);
  $facet->set('show_title', TRUE);
  $facet->set('processor_configs', $processors);
  $facet->save();
}

/**
 * Places a facet block in the theme sidebar.
 */
function wcf_disc_place_facet_block(string $facet_id, int $weight): void {
  $block_id = 'wcf_facet_' . $facet_id;
  $plugin_id = 'facet_block:' . $facet_id;
  $block = Block::load($block_id);
  if (!$block) {
    $block = Block::create([
      'id' => $block_id,
      'theme' => 'wcf_theme',
      'plugin' => $plugin_id,
      'region' => 'sidebar_first',
      'weight' => $weight,
      'status' => TRUE,
      'settings' => [
        'id' => $plugin_id,
        'label' => '',
        'label_display' => 'visible',
        'provider' => 'facets',
        'context_mapping' => [],
      ],
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => FALSE,
          'pages' => "/search\n/search*",
        ],
      ],
    ]);
    $block->save();
    wcf_disc_print("Placed facet block: {$block_id}");
    return;
  }

  $block->setRegion('sidebar_first');
  $block->setWeight($weight);
  $block->setStatus(TRUE);
  $block->save();
  wcf_disc_print("Updated facet block: {$block_id}");
}

/**
 * Configures facets and facet summary for site search.
 */
function wcf_disc_setup_facets(): void {
  wcf_disc_ensure_facet('stallion_status', 'Stallion status', 'status', 0, [
    'list_item' => [
      'processor_id' => 'list_item',
      'weights' => ['build' => 5],
      'settings' => [],
    ],
  ]);
  wcf_disc_ensure_facet('categories', 'Categories', 'category', 1, [
    'translate_entity' => [
      'processor_id' => 'translate_entity',
      'weights' => ['build' => 5],
      'settings' => [],
    ],
  ]);
  wcf_disc_ensure_facet('content_type', 'Content type', 'type', 2, [
    'list_item' => [
      'processor_id' => 'list_item',
      'weights' => ['build' => 5],
      'settings' => [],
    ],
  ]);

  wcf_disc_place_facet_block('stallion_status', 0);
  wcf_disc_place_facet_block('categories', 1);
  wcf_disc_place_facet_block('content_type', 2);

  $summary_id = 'search_active_filters';
  $summary = FacetsSummary::load($summary_id);
  if (!$summary) {
    $summary = FacetsSummary::create([
      'id' => $summary_id,
      'name' => 'Search active filters',
      'facet_source_id' => wcf_disc_facet_source_id(),
    ]);
    wcf_disc_print("Created facets summary: {$summary_id}");
  }
  $summary->set('facets', [
    'stallion_status' => [
      'checked' => TRUE,
      'label' => 'Status',
      'separator' => ', ',
      'show_count' => FALSE,
      'weight' => 0,
    ],
    'categories' => [
      'checked' => TRUE,
      'label' => 'Category',
      'separator' => ', ',
      'show_count' => FALSE,
      'weight' => 1,
    ],
    'content_type' => [
      'checked' => TRUE,
      'label' => 'Type',
      'separator' => ', ',
      'show_count' => FALSE,
      'weight' => 2,
    ],
  ]);
  $summary->set('processor_configs', [
    'reset_facets' => [
      'processor_id' => 'reset_facets',
      'weights' => ['build' => 30],
      'settings' => [
        'link_text' => 'Clear all filters',
      ],
    ],
    'show_count' => [
      'processor_id' => 'show_count',
      'weights' => ['build' => 5],
      'settings' => [],
    ],
  ]);
  $summary->save();

  $summary_block_id = 'wcf_search_facets_summary';
  $summary_plugin = 'facets_summary_block:' . $summary_id;
  $block = Block::load($summary_block_id);
  if (!$block) {
    $block = Block::create([
      'id' => $summary_block_id,
      'theme' => 'wcf_theme',
      'plugin' => $summary_plugin,
      'region' => 'content',
      'weight' => -20,
      'status' => TRUE,
      'settings' => [
        'id' => $summary_plugin,
        'label' => 'Active filters',
        'label_display' => 'visible',
        'provider' => 'facets_summary',
      ],
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => FALSE,
          'pages' => "/search\n/search*",
        ],
      ],
    ]);
    $block->save();
    wcf_disc_print("Placed facets summary block: {$summary_block_id}");
  }
}

/**
 * Updates search_stallions to use facets instead of duplicate exposed filters.
 */
function wcf_disc_setup_search_view(): void {
  $view = View::load('search_stallions');
  if (!$view) {
    wcf_disc_print('View search_stallions not found — skipping.');
    return;
  }

  $display = &$view->getDisplay('default');
  unset($display['display_options']['filters']['category']);
  unset($display['display_options']['filters']['tags']);
  unset($display['display_options']['filters']['status']);

  $display['display_options']['exposed_form']['options']['reset_button_label'] = 'Reset search';

  $display['display_options']['header']['result'] = [
    'id' => 'result',
    'table' => 'views',
    'field' => 'result',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'result',
    'empty' => TRUE,
    'content' => 'Displaying @start–@end of @total results',
  ];

  $display['display_options']['empty']['area_text_custom']['content'] =
    '<p class="site-search__empty-message" role="status">No results match your search. Try different keywords or <a href="/search">clear all filters</a>.</p>';

  $view->save();
  wcf_disc_print('Updated View search_stallions (facets-only filtering, result count header).');
}

/**
 * Ensures featured_content paragraph type and fields exist.
 */
function wcf_disc_setup_featured_content_paragraph(): void {
  if (!ParagraphsType::load('featured_content')) {
    ParagraphsType::create([
      'id' => 'featured_content',
      'label' => 'Featured content',
      'description' => 'Editorial section that surfaces recent content by type via a cached View listing.',
    ])->save();
    wcf_disc_print('Created paragraph type: featured_content');
  }

  if (!FieldStorageConfig::loadByName('paragraph', 'field_content_type')) {
    FieldStorageConfig::create([
      'field_name' => 'field_content_type',
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [
          'stallion' => 'Stallions',
          'article' => 'Articles',
          'container_home' => 'Container homes',
        ],
      ],
    ])->save();
    wcf_disc_print('Created field storage: paragraph.field_content_type');
  }

  if (!FieldStorageConfig::loadByName('paragraph', 'field_item_limit')) {
    FieldStorageConfig::create([
      'field_name' => 'field_item_limit',
      'entity_type' => 'paragraph',
      'type' => 'integer',
      'cardinality' => 1,
    ])->save();
    wcf_disc_print('Created field storage: paragraph.field_item_limit');
  }

  $fields = [
    'field_title' => [
      'type' => 'string',
      'label' => 'Title',
      'storage_exists' => TRUE,
    ],
    'field_description' => [
      'type' => 'text_long',
      'label' => 'Description',
      'storage_exists' => TRUE,
    ],
    'field_content_type' => [
      'type' => 'list_string',
      'label' => 'Content type',
      'required' => TRUE,
    ],
    'field_item_limit' => [
      'type' => 'integer',
      'label' => 'Item limit',
      'description' => 'Number of items to show (1–12).',
      'default_value' => [['value' => 3]],
      'required' => TRUE,
    ],
  ];

  foreach ($fields as $field_name => $info) {
    if (FieldConfig::loadByName('paragraph', 'featured_content', $field_name)) {
      continue;
    }
    if (empty($info['storage_exists']) && !FieldStorageConfig::loadByName('paragraph', $field_name)) {
      continue;
    }
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'paragraph',
      'bundle' => 'featured_content',
      'label' => $info['label'],
      'description' => $info['description'] ?? '',
      'required' => $info['required'] ?? FALSE,
      'default_value' => $info['default_value'] ?? [],
      'settings' => $info['settings'] ?? [],
    ])->save();
    wcf_disc_print("Created field: paragraph.featured_content.{$field_name}");
  }

  $homepage_sections = FieldConfig::loadByName('node', 'homepage', 'field_feature_sections');
  if ($homepage_sections) {
    $handler_settings = $homepage_sections->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    if (empty($target_bundles['featured_content'])) {
      $target_bundles['featured_content'] = 'featured_content';
      $handler_settings['target_bundles'] = $target_bundles;
      $handler_settings['target_bundles_drag_drop']['featured_content'] = [
        'weight' => 4,
        'enabled' => TRUE,
      ];
      $homepage_sections->setSetting('handler_settings', $handler_settings);
      $homepage_sections->save();
      wcf_disc_print('Added featured_content to homepage feature sections.');
    }
  }
}

/**
 * Creates the featured_content discovery View block display.
 */
function wcf_disc_setup_featured_content_view(): void {
  $view = View::load('featured_content');
  if (!$view) {
    $view = View::create([
      'id' => 'featured_content',
      'label' => 'Featured content',
      'module' => 'node',
      'description' => 'Reusable listings for featured content paragraphs.',
      'tag' => 'wcf',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
    ]);
    wcf_disc_print('Created View featured_content');
  }

  $default = [
    'title' => 'Featured content',
    'access' => [
      'type' => 'perm',
      'options' => ['perm' => 'access content'],
    ],
    'cache' => [
      'type' => 'tag',
      'options' => [],
    ],
    'pager' => [
      'type' => 'some',
      'options' => [
        'offset' => 0,
        'items_per_page' => 3,
      ],
    ],
    'filters' => [
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
    ],
    'sorts' => [
      'created' => [
        'id' => 'created',
        'table' => 'node_field_data',
        'field' => 'created',
        'entity_type' => 'node',
        'entity_field' => 'created',
        'plugin_id' => 'date',
        'order' => 'DESC',
      ],
    ],
    'style' => [
      'type' => 'html_list',
      'options' => [
        'type' => 'ul',
        'wrapper_class' => 'stallion-listing__results',
        'class' => 'stallion-listing__grid',
      ],
    ],
    'row' => [
      'type' => 'entity:node',
      'options' => ['view_mode' => 'card'],
    ],
    'css_class' => 'stallion-listing featured-content-listing',
  ];

  $block_base = [
    'filters' => $default['filters'],
    'style' => $default['style'],
    'cache' => $default['cache'],
    'pager' => $default['pager'],
    'sorts' => $default['sorts'],
  ];

  $bundles = [
    'stallion' => 'card',
    'article' => 'teaser',
    'container_home' => 'teaser',
  ];

  $displays = [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => $default,
    ],
  ];

  $position = 1;
  foreach ($bundles as $bundle => $view_mode) {
    $display_id = 'block_' . $bundle;
    $displays[$display_id] = [
      'id' => $display_id,
      'display_title' => ucfirst(str_replace('_', ' ', $bundle)),
      'display_plugin' => 'block',
      'position' => $position++,
      'display_options' => $block_base + [
        'filters' => [
          'status' => $default['filters']['status'],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'entity_type' => 'node',
            'entity_field' => 'type',
            'plugin_id' => 'bundle',
            'value' => [$bundle => $bundle],
            'group' => 1,
          ],
        ],
        'row' => [
          'type' => 'entity:node',
          'options' => ['view_mode' => $view_mode],
        ],
      ],
    ];
  }

  $view->set('display', $displays);
  $view->save();
  wcf_disc_print('Configured View featured_content block displays per bundle.');
}

/**
 * Creates content_health admin view.
 */
function wcf_disc_setup_content_health_view(): void {
  $view = View::load('content_health');
  if ($view) {
    wcf_disc_print('View content_health already exists — updating.');
  }
  else {
    $view = View::create([
      'id' => 'content_health',
      'label' => 'Content health',
      'module' => 'node',
      'description' => 'Editorial governance: unpublished, missing assets, stale content.',
      'tag' => 'wcf',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
    ]);
    wcf_disc_print('Created View content_health');
  }

  $fields = [
    'title' => [
      'id' => 'title',
      'table' => 'node_field_data',
      'field' => 'title',
      'entity_type' => 'node',
      'entity_field' => 'title',
      'plugin_id' => 'field',
      'label' => 'Title',
      'settings' => ['link_to_entity' => TRUE],
    ],
    'type' => [
      'id' => 'type',
      'table' => 'node_field_data',
      'field' => 'type',
      'entity_type' => 'node',
      'entity_field' => 'type',
      'plugin_id' => 'field',
      'label' => 'Type',
    ],
    'status' => [
      'id' => 'status',
      'table' => 'node_field_data',
      'field' => 'status',
      'entity_type' => 'node',
      'entity_field' => 'status',
      'plugin_id' => 'field',
      'label' => 'Published',
      'type' => 'boolean',
      'settings' => [
        'format' => 'custom',
        'format_custom_false' => 'Unpublished',
        'format_custom_true' => 'Published',
      ],
    ],
    'changed' => [
      'id' => 'changed',
      'table' => 'node_field_data',
      'field' => 'changed',
      'entity_type' => 'node',
      'entity_field' => 'changed',
      'plugin_id' => 'field',
      'label' => 'Updated',
      'type' => 'timestamp',
      'settings' => ['date_format' => 'short'],
    ],
    'operations' => [
      'id' => 'operations',
      'table' => 'node',
      'field' => 'operations',
      'plugin_id' => 'entity_operations',
      'label' => 'Operations',
    ],
  ];

  $base_display = [
    'title' => 'Content health',
    'access' => [
      'type' => 'perm',
      'options' => ['perm' => 'access content overview'],
    ],
    'cache' => ['type' => 'tag', 'options' => []],
    'pager' => [
      'type' => 'full',
      'options' => ['items_per_page' => 50],
    ],
    'exposed_form' => [
      'type' => 'basic',
      'options' => [
        'submit_button' => 'Filter',
        'reset_button' => TRUE,
        'reset_button_label' => 'Reset',
      ],
    ],
    'fields' => $fields,
    'filters' => [
      'status' => [
        'id' => 'status',
        'table' => 'node_field_data',
        'field' => 'status',
        'entity_type' => 'node',
        'entity_field' => 'status',
        'plugin_id' => 'boolean',
        'value' => 'All',
        'group' => 1,
        'exposed' => TRUE,
        'expose' => [
          'label' => 'Published status',
          'identifier' => 'status',
        ],
      ],
    ],
    'sorts' => [
      'changed' => [
        'id' => 'changed',
        'table' => 'node_field_data',
        'field' => 'changed',
        'entity_type' => 'node',
        'entity_field' => 'changed',
        'plugin_id' => 'date',
        'order' => 'ASC',
      ],
    ],
    'empty' => [
      'area' => [
        'id' => 'area',
        'table' => 'views',
        'field' => 'area',
        'plugin_id' => 'text',
        'empty' => TRUE,
        'content' => [
          'value' => '<p>No content issues match this filter.</p>',
          'format' => 'basic_html',
        ],
      ],
    ],
  ];

  $view->set('display', [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => $base_display,
    ],
    'page_overview' => [
      'id' => 'page_overview',
      'display_title' => 'Overview',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'admin/content/health',
        'menu' => [
          'type' => 'tab',
          'title' => 'Health overview',
          'parent' => 'system.admin_content',
          'weight' => 5,
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'entity_type' => 'node',
            'entity_field' => 'status',
            'plugin_id' => 'boolean',
            'value' => '0',
            'group' => 1,
          ],
        ],
      ],
    ],
    'page_missing_image' => [
      'id' => 'page_missing_image',
      'display_title' => 'Missing images',
      'display_plugin' => 'page',
      'position' => 2,
      'display_options' => [
        'path' => 'admin/content/health/missing-images',
        'menu' => [
          'type' => 'tab',
          'title' => 'Missing images',
          'parent' => 'system.admin_content',
          'weight' => 6,
        ],
        'filters' => [
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
            'value' => [
              'stallion' => 'stallion',
              'container_home' => 'container_home',
            ],
            'group' => 1,
          ],
        ],
        'relationships' => [
          'field_main_image' => [
            'id' => 'field_main_image',
            'table' => 'node__field_main_image',
            'field' => 'field_main_image',
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => 'Main image',
            'plugin_id' => 'standard',
            'required' => FALSE,
          ],
        ],
        'filters_extra' => [
          'field_main_image_target_id' => [
            'id' => 'field_main_image_target_id',
            'table' => 'node__field_main_image',
            'field' => 'field_main_image_target_id',
            'relationship' => 'field_main_image',
            'plugin_id' => 'numeric',
            'operator' => 'empty',
            'value' => ['min' => '', 'max' => ''],
            'group' => 1,
          ],
        ],
      ],
    ],
    'page_stale' => [
      'id' => 'page_stale',
      'display_title' => 'Stale content',
      'display_plugin' => 'page',
      'position' => 3,
      'display_options' => [
        'path' => 'admin/content/health/stale',
        'menu' => [
          'type' => 'tab',
          'title' => 'Stale content',
          'parent' => 'system.admin_content',
          'weight' => 7,
        ],
        'filters' => [
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
          'changed' => [
            'id' => 'changed',
            'table' => 'node_field_data',
            'field' => 'changed',
            'entity_type' => 'node',
            'entity_field' => 'changed',
            'plugin_id' => 'date',
            'operator' => '<',
            'value' => [
              'type' => 'offset',
              'value' => '-90 days',
            ],
            'group' => 1,
          ],
        ],
      ],
    ],
  ]);

  // Merge missing-image extra filter into display options.
  $displays = $view->get('display');
  if (isset($displays['page_missing_image']['display_options']['filters_extra'])) {
    $extra = $displays['page_missing_image']['display_options']['filters_extra'];
    unset($displays['page_missing_image']['display_options']['filters_extra']);
    $displays['page_missing_image']['display_options']['filters'] = array_merge(
      $displays['page_missing_image']['display_options']['filters'] ?? [],
      $extra
    );
    $view->set('display', $displays);
  }

  $view->save();
  wcf_disc_print('Configured View content_health at admin/content/health');
}

/**
 * Creates media governance admin views.
 */
function wcf_disc_setup_media_governance_views(): void {
  $view = View::load('media_governance');
  if (!$view) {
    $view = View::create([
      'id' => 'media_governance',
      'label' => 'Media governance',
      'module' => 'media',
      'description' => 'Operational media maintenance: alt text, file size, usage.',
      'tag' => 'wcf',
      'base_table' => 'media_field_data',
      'base_field' => 'mid',
    ]);
    wcf_disc_print('Created View media_governance');
  }

  $media_fields = [
    'name' => [
      'id' => 'name',
      'table' => 'media_field_data',
      'field' => 'name',
      'entity_type' => 'media',
      'entity_field' => 'name',
      'plugin_id' => 'field',
      'label' => 'Name',
      'settings' => ['link_to_entity' => TRUE],
    ],
    'bundle' => [
      'id' => 'bundle',
      'table' => 'media_field_data',
      'field' => 'bundle',
      'entity_type' => 'media',
      'entity_field' => 'bundle',
      'plugin_id' => 'field',
      'label' => 'Type',
    ],
    'changed' => [
      'id' => 'changed',
      'table' => 'media_field_data',
      'field' => 'changed',
      'entity_type' => 'media',
      'entity_field' => 'changed',
      'plugin_id' => 'field',
      'label' => 'Updated',
      'type' => 'timestamp',
      'settings' => ['date_format' => 'short'],
    ],
    'operations' => [
      'id' => 'operations',
      'table' => 'media',
      'field' => 'operations',
      'entity_type' => 'media',
      'plugin_id' => 'entity_operations',
      'label' => 'Operations',
    ],
  ];

  $base = [
    'title' => 'Media governance',
    'access' => [
      'type' => 'perm',
      'options' => ['perm' => 'access media overview'],
    ],
    'cache' => ['type' => 'tag', 'options' => []],
    'pager' => ['type' => 'full', 'options' => ['items_per_page' => 50]],
    'fields' => $media_fields,
    'filters' => [
      'bundle' => [
        'id' => 'bundle',
        'table' => 'media_field_data',
        'field' => 'bundle',
        'entity_type' => 'media',
        'entity_field' => 'bundle',
        'plugin_id' => 'bundle',
        'value' => ['image' => 'image'],
        'group' => 1,
      ],
    ],
  ];

  $view->set('display', [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => $base,
    ],
    'page_missing_alt' => [
      'id' => 'page_missing_alt',
      'display_title' => 'Missing alt text',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'admin/content/media/missing-alt',
        'menu' => [
          'type' => 'tab',
          'title' => 'Missing alt text',
          'parent' => 'entity.media.collection',
          'weight' => 20,
        ],
        'relationships' => [
          'field_media_image_target_id' => [
            'id' => 'field_media_image_target_id',
            'table' => 'media__field_media_image',
            'field' => 'field_media_image_target_id',
            'plugin_id' => 'standard',
            'required' => TRUE,
          ],
        ],
        'fields' => array_merge($media_fields, [
          'field_media_image_alt' => [
            'id' => 'field_media_image_alt',
            'table' => 'media__field_media_image',
            'field' => 'field_media_image_alt',
            'relationship' => 'field_media_image_target_id',
            'plugin_id' => 'field',
            'label' => 'Alt text',
          ],
        ]),
        'filters' => [
          'bundle' => $base['filters']['bundle'],
          'field_media_image_alt' => [
            'id' => 'field_media_image_alt',
            'table' => 'media__field_media_image',
            'field' => 'field_media_image_alt',
            'relationship' => 'field_media_image_target_id',
            'plugin_id' => 'string',
            'operator' => 'empty',
            'value' => '',
            'group' => 1,
          ],
        ],
      ],
    ],
    'page_oversized' => [
      'id' => 'page_oversized',
      'display_title' => 'Oversized images',
      'display_plugin' => 'page',
      'position' => 2,
      'display_options' => [
        'path' => 'admin/content/media/oversized',
        'menu' => [
          'type' => 'tab',
          'title' => 'Oversized images',
          'parent' => 'entity.media.collection',
          'weight' => 21,
        ],
        'relationships' => [
          'field_media_image_target_id' => [
            'id' => 'field_media_image_target_id',
            'table' => 'media__field_media_image',
            'field' => 'field_media_image_target_id',
            'plugin_id' => 'standard',
            'required' => TRUE,
          ],
          'file_managed' => [
            'id' => 'file_managed',
            'table' => 'file_managed',
            'field' => 'fid',
            'relationship' => 'field_media_image_target_id',
            'plugin_id' => 'standard',
            'required' => TRUE,
          ],
        ],
        'fields' => array_merge($media_fields, [
          'filesize' => [
            'id' => 'filesize',
            'table' => 'file_managed',
            'field' => 'filesize',
            'relationship' => 'file_managed',
            'plugin_id' => 'field',
            'label' => 'File size',
            'type' => 'file_size',
          ],
        ]),
        'filters' => [
          'bundle' => $base['filters']['bundle'],
          'filesize' => [
            'id' => 'filesize',
            'table' => 'file_managed',
            'field' => 'filesize',
            'relationship' => 'file_managed',
            'plugin_id' => 'numeric',
            'operator' => '>',
            'value' => ['min' => '', 'max' => '', 'value' => '512000'],
            'group' => 1,
          ],
        ],
      ],
    ],
    'page_unused' => [
      'id' => 'page_unused',
      'display_title' => 'Potentially unused',
      'display_plugin' => 'page',
      'position' => 3,
      'display_options' => [
        'path' => 'admin/content/media/unused',
        'menu' => [
          'type' => 'tab',
          'title' => 'Potentially unused',
          'parent' => 'entity.media.collection',
          'weight' => 22,
        ],
        'relationships' => [
          'node__field_main_image' => [
            'id' => 'node__field_main_image',
            'table' => 'node__field_main_image',
            'field' => 'field_main_image_target_id',
            'plugin_id' => 'standard',
            'required' => FALSE,
          ],
        ],
        'filters' => [
          'bundle' => $base['filters']['bundle'],
          'field_main_image_target_id' => [
            'id' => 'field_main_image_target_id',
            'table' => 'node__field_main_image',
            'field' => 'field_main_image_target_id',
            'relationship' => 'node__field_main_image',
            'plugin_id' => 'numeric',
            'operator' => 'empty',
            'value' => ['min' => '', 'max' => ''],
            'group' => 1,
          ],
        ],
      ],
    ],
  ]);
  $view->save();
  wcf_disc_print('Configured View media_governance (alt, oversized, unused tabs).');
}

/**
 * Improves editorial admin UX copy on content types.
 */
function wcf_disc_setup_editorial_ops(): void {
  $content_view = View::load('content');
  if ($content_view) {
    $displays = $content_view->get('display');
    $displays['default']['display_options']['title'] = 'Content overview';
    $content_view->set('display', $displays);
    $content_view->save();
    wcf_disc_print('Updated content overview view title.');
  }

  foreach (['homepage', 'stallion', 'article', 'container_home', 'page'] as $bundle) {
    $type = NodeType::load($bundle);
    if (!$type) {
      continue;
    }
    $help = 'Save drafts while working. Submit for review when ready for an editor. Published content is visible on the public site. Use the Content health report under Content for maintenance.';
    $type->set('help', $help);
    $type->save();
  }

  $moderated = View::load('moderated_content');
  if ($moderated) {
    $displays = $moderated->get('display');
    $displays['default']['display_options']['title'] = 'Content in moderation';
    $displays['default']['display_options']['empty']['area_text_custom']['content'] =
      '<p>No content is awaiting moderation.</p>';
    $moderated->set('display', $displays);
    $moderated->save();
    wcf_disc_print('Updated moderated content view labels.');
  }
}

/**
 * Reindexes Search API after facet field changes.
 */
function wcf_disc_reindex_search(): void {
  $index = Index::load('stallion_content');
  if ($index) {
    $index->reindex();
    $index->indexItems(100);
    wcf_disc_print('Reindexed stallion_content Search API index.');
  }
}

// --- Execution ---
wcf_disc_print('WCF discovery setup starting…');
wcf_disc_setup_facets();
wcf_disc_setup_search_view();
wcf_disc_setup_featured_content_paragraph();
wcf_disc_setup_featured_content_view();
wcf_disc_setup_content_health_view();
wcf_disc_setup_media_governance_views();
wcf_disc_setup_editorial_ops();
wcf_disc_reindex_search();
wcf_disc_print('WCF discovery setup complete.');
