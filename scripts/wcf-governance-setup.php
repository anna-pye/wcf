<?php

/**
 * @file
 * WCF operational governance: Search API, editorial workflow, SEO, sitemap.
 *
 * Run after enabling modules:
 *   ddev drush en search_api search_api_db content_moderation workflows \
 *     simple_sitemap metatag_twitter_cards -y
 *   ddev drush php:script scripts/wcf-governance-setup.php
 *   ddev drush cex -y
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\views\Entity\View;
use Drupal\workflows\Entity\Workflow;

/**
 * Prints a status line.
 */
function wcf_gov_print(string $message): void {
  print $message . "\n";
}

/**
 * Ensures Search API server and index exist.
 */
function wcf_gov_setup_search_api(): void {
  $server_id = 'wcf_database';
  $server = Server::load($server_id);
  if (!$server) {
    $server = Server::create([
      'id' => $server_id,
      'name' => 'WCF database',
      'description' => 'Database search backend for Winning Colours Farm.',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 3,
        'matching' => 'words',
        'phrase' => 'bigram',
      ],
      'status' => TRUE,
    ]);
    $server->save();
    wcf_gov_print("Created Search API server: {$server_id}");
  }
  else {
    wcf_gov_print("Search API server {$server_id} already exists.");
  }

  $index_id = 'stallion_content';
  $index = Index::load($index_id);
  if (!$index) {
    $index = Index::create([
      'id' => $index_id,
      'name' => 'Stallion content',
      'description' => 'Published stallion, container home, and article content for site search.',
      'server' => $server_id,
      'status' => TRUE,
      'read_only' => FALSE,
    ]);
    wcf_gov_print("Created Search API index: {$index_id}");
  }
  else {
    wcf_gov_print("Search API index {$index_id} already exists — updating fields.");
  }

  $index->set('datasource_settings', [
    'entity:node' => [
      'bundles' => [
        'default' => FALSE,
        'selected' => [
          'stallion' => 'stallion',
          'container_home' => 'container_home',
          'article' => 'article',
        ],
      ],
      'languages' => [
        'default' => TRUE,
        'selected' => [],
      ],
    ],
  ]);

  $field_settings = [
    'title' => [
      'label' => 'Title',
      'datasource_id' => 'entity:node',
      'property_path' => 'title',
      'type' => 'text',
      'boost' => 8.0,
    ],
    'body' => [
      'label' => 'Body',
      'datasource_id' => 'entity:node',
      'property_path' => 'body',
      'type' => 'text',
    ],
    'summary' => [
      'label' => 'Summary',
      'datasource_id' => 'entity:node',
      'property_path' => 'body:summary',
      'type' => 'text',
    ],
    'category' => [
      'label' => 'Category',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_category',
      'type' => 'integer',
    ],
    'status' => [
      'label' => 'Stallion status',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_status',
      'type' => 'string',
    ],
    'created' => [
      'label' => 'Created',
      'datasource_id' => 'entity:node',
      'property_path' => 'created',
      'type' => 'date',
    ],
    'featured' => [
      'label' => 'Featured',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_featured',
      'type' => 'boolean',
    ],
    'node_status' => [
      'label' => 'Published',
      'datasource_id' => 'entity:node',
      'property_path' => 'status',
      'type' => 'boolean',
      'indexed_locked' => TRUE,
      'type_locked' => TRUE,
    ],
    'type' => [
      'label' => 'Content type',
      'datasource_id' => 'entity:node',
      'property_path' => 'type',
      'type' => 'string',
    ],
  ];

  $index->set('field_settings', $field_settings);

  $index->set('processor_settings', [
    'add_url' => [
      'weights' => ['preprocess_index' => -30],
      'status' => TRUE,
    ],
    'aggregated_field' => [
      'weights' => ['add_properties' => 20],
      'status' => TRUE,
    ],
    'content_access' => [
      'weights' => [
        'preprocess_index' => -6,
        'preprocess_query' => -4,
      ],
      'status' => TRUE,
    ],
    'entity_status' => [
      'weights' => ['preprocess_index' => -10],
      'status' => TRUE,
    ],
    'html_filter' => [
      'weights' => [
        'preprocess_index' => -3,
        'preprocess_query' => -6,
      ],
      'status' => TRUE,
      'all_fields' => FALSE,
      'fields' => ['body', 'summary', 'title'],
      'title' => TRUE,
      'alt' => TRUE,
    ],
    'ignorecase' => [
      'weights' => [
        'preprocess_index' => -5,
        'preprocess_query' => -8,
      ],
      'status' => TRUE,
      'fields' => ['body', 'summary', 'title'],
    ],
    'tokenizer' => [
      'weights' => [
        'preprocess_index' => -2,
        'preprocess_query' => -5,
      ],
      'status' => TRUE,
      'fields' => ['body', 'summary', 'title'],
      'spaces' => '',
      'overlap_cjk' => 1,
      'minimum_word_size' => '3',
    ],
    'transliteration' => [
      'weights' => [
        'preprocess_index' => -4,
        'preprocess_query' => -7,
      ],
      'status' => TRUE,
      'fields' => ['body', 'summary', 'title'],
    ],
  ]);

  $index->set('tracker_settings', ['default' => []]);
  $index->set('options', [
    'cron_limit' => 50,
    'index_directly' => TRUE,
  ]);
  $index->save();

  if (!$index->status()) {
    $index->enable()->save();
    wcf_gov_print('Enabled Search API index stallion_content.');
  }

  /** @var \Drupal\search_api\IndexInterface $index */
  $index = Index::load($index_id);
  wcf_gov_reindex_if_empty($index);
}

/**
 * Reindexes when the index has no items tracked yet.
 */
function wcf_gov_reindex_if_empty(IndexInterface $index): void {
  $tracker = $index->getTrackerInstance();
  $remaining = $tracker->getRemainingItemsCount();
  $indexed = $tracker->getIndexedItemsCount();
  if ($indexed === 0) {
    wcf_gov_print('Queueing full reindex for stallion_content…');
    $index->reindex();
    $index->indexItems();
    wcf_gov_print('Initial index build complete.');
  }
  else {
    wcf_gov_print("Index has {$indexed} items tracked ({$remaining} remaining).");
  }
}

/**
 * Creates the search_stallions View.
 */
function wcf_gov_setup_search_view(): void {
  $view = View::load('search_stallions');
  if ($view) {
    wcf_gov_print('View search_stallions already exists — updating.');
  }
  else {
    $view = View::create([
      'id' => 'search_stallions',
      'label' => 'Search',
      'module' => 'views',
      'description' => 'Site search across stallions, container homes, and articles.',
      'tag' => 'wcf',
      'base_table' => 'search_api_index_stallion_content',
      'base_field' => 'search_api_id',
    ]);
  }

  $default = [
    'title' => 'Search',
    'css_class' => 'site-search stallion-listing',
    'access' => [
      'type' => 'perm',
      'options' => ['perm' => 'access content'],
    ],
    'cache' => [
      'type' => 'search_api_tag',
      'options' => [],
    ],
    'exposed_form' => [
      'type' => 'basic',
      'options' => [
        'submit_button' => 'Search',
        'reset_button' => TRUE,
        'reset_button_label' => 'Clear filters',
        'exposed_sorts_label' => 'Sort by',
        'expose_sort_order' => FALSE,
      ],
    ],
    'pager' => [
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
    ],
    'style' => [
      'type' => 'html_list',
      'options' => [
        'type' => 'ul',
        'class' => 'stallion-listing__grid',
        'wrapper_class' => 'stallion-listing__results',
      ],
    ],
    'row' => [
      'type' => 'search_api',
      'options' => [
        'view_modes' => [
          'entity:node' => [
            'stallion' => 'card',
            'container_home' => 'card',
            'article' => 'teaser',
          ],
        ],
      ],
    ],
    'filters' => [
      'search_api_fulltext' => [
        'id' => 'search_api_fulltext',
        'table' => 'search_api_index_stallion_content',
        'field' => 'search_api_fulltext',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'plugin_id' => 'search_api_fulltext',
        'operator' => 'and',
        'value' => '',
        'group' => 1,
        'exposed' => TRUE,
        'expose' => [
          'operator_id' => 'search_api_fulltext_op',
          'label' => 'Keywords',
          'description' => 'Search stallions, container homes, and articles.',
          'use_operator' => FALSE,
          'operator' => 'search_api_fulltext_op',
          'identifier' => 'keywords',
          'required' => FALSE,
          'remember' => FALSE,
          'multiple' => FALSE,
        ],
        'is_grouped' => FALSE,
        'parse_mode' => 'direct',
        'min_length' => NULL,
        'fields' => [
          'title' => 'title',
          'body' => 'body',
          'summary' => 'summary',
        ],
      ],
      'category' => [
        'id' => 'category',
        'table' => 'search_api_index_stallion_content',
        'field' => 'category',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'plugin_id' => 'search_api_term',
        'operator' => 'or',
        'value' => [],
        'group' => 1,
        'exposed' => TRUE,
        'expose' => [
          'operator_id' => 'category_op',
          'label' => 'Category',
          'description' => '',
          'use_operator' => FALSE,
          'operator' => 'category_op',
          'identifier' => 'category',
          'required' => FALSE,
          'remember' => FALSE,
          'multiple' => FALSE,
        ],
        'is_grouped' => FALSE,
        'vid' => 'categories',
        'hierarchy' => FALSE,
        'type' => 'select',
        'limit' => TRUE,
      ],
      'status' => [
        'id' => 'status',
        'table' => 'search_api_index_stallion_content',
        'field' => 'status',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'plugin_id' => 'search_api_options',
        'operator' => 'or',
        'value' => [],
        'group' => 1,
        'exposed' => TRUE,
        'expose' => [
          'operator_id' => 'status_op',
          'label' => 'Stallion status',
          'description' => 'Filter stallions by availability status.',
          'use_operator' => FALSE,
          'operator' => 'status_op',
          'identifier' => 'stallion_status',
          'required' => FALSE,
          'remember' => FALSE,
          'multiple' => FALSE,
        ],
        'is_grouped' => FALSE,
      ],
    ],
    'sorts' => [
      'featured' => [
        'id' => 'featured',
        'table' => 'search_api_index_stallion_content',
        'field' => 'featured',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'plugin_id' => 'search_api',
        'order' => 'DESC',
        'exposed' => FALSE,
      ],
      'created' => [
        'id' => 'created',
        'table' => 'search_api_index_stallion_content',
        'field' => 'created',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'plugin_id' => 'search_api',
        'order' => 'DESC',
        'exposed' => FALSE,
      ],
    ],
    'empty' => [
      'area_text_custom' => [
        'id' => 'area_text_custom',
        'table' => 'views',
        'field' => 'area_text_custom',
        'plugin_id' => 'text_custom',
        'empty' => TRUE,
        'content' => '<p>No results match your search. Try different keywords or clear the filters.</p>',
      ],
    ],
  ];

  $view->set('display', [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => $default,
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'display_extenders' => [],
        'path' => 'search',
        'menu' => [
          'type' => 'normal',
          'title' => 'Search',
          'weight' => 10,
          'menu_name' => 'main',
        ],
      ],
    ],
  ]);
  $view->save();
  wcf_gov_print('Configured View search_stallions at /search.');
}

/**
 * Creates Editorial workflow and applies it to content types.
 */
function wcf_gov_setup_editorial_workflow(): void {
  $workflow_id = 'editorial';
  $workflow = Workflow::load($workflow_id);
  if (!$workflow) {
    $workflow = Workflow::create([
      'id' => $workflow_id,
      'label' => 'Editorial',
      'type' => 'content_moderation',
      'type_settings' => [],
    ]);
    wcf_gov_print('Created workflow: editorial');
  }
  else {
    wcf_gov_print('Workflow editorial already exists — updating.');
  }

  $workflow->set('type_settings', [
    'states' => [
      'draft' => [
        'label' => 'Draft',
        'weight' => -5,
        'published' => FALSE,
        'default_revision' => FALSE,
      ],
      'review' => [
        'label' => 'In review',
        'weight' => 0,
        'published' => FALSE,
        'default_revision' => FALSE,
      ],
      'published' => [
        'label' => 'Published',
        'weight' => 5,
        'published' => TRUE,
        'default_revision' => TRUE,
      ],
    ],
    'transitions' => [
      'submit_for_review' => [
        'label' => 'Submit for review',
        'from' => ['draft'],
        'to' => 'review',
        'weight' => 0,
      ],
      'publish' => [
        'label' => 'Publish',
        'from' => ['review', 'draft'],
        'to' => 'published',
        'weight' => 1,
      ],
      'send_back' => [
        'label' => 'Send back to draft',
        'from' => ['review'],
        'to' => 'draft',
        'weight' => 2,
      ],
      'create_new_draft' => [
        'label' => 'Create new draft',
        'from' => ['published'],
        'to' => 'draft',
        'weight' => 3,
      ],
    ],
    'entity_types' => [
      'node' => ['homepage', 'stallion', 'article'],
    ],
    'default_moderation_state' => 'draft',
  ]);
  $workflow->save();

  foreach (['homepage', 'stallion', 'article'] as $bundle) {
    $node_type = NodeType::load($bundle);
    if (!$node_type) {
      continue;
    }
    $node_type->setThirdPartySetting('content_moderation', 'enabled', TRUE);
    $node_type->setThirdPartySetting('content_moderation', 'workflow', $workflow_id);
    $node_type->setNewRevision(TRUE);
    $node_type->setPreviewMode(1);
    $node_type->save();
    wcf_gov_print("Applied editorial workflow to {$bundle}.");

    wcf_gov_configure_moderation_form_display($bundle);
  }
}

/**
 * Adds moderation_state and revision help to editorial forms.
 */
function wcf_gov_configure_moderation_form_display(string $bundle): void {
  $display = EntityFormDisplay::load("node.{$bundle}.default");
  if (!$display) {
    return;
  }
  $display->setComponent('moderation_state', [
    'type' => 'moderation_state_default',
    'weight' => 90,
    'region' => 'content',
    'settings' => [],
  ]);
  $display->setComponent('status', [
    'type' => 'boolean_checkbox',
    'weight' => 91,
    'region' => 'content',
    'settings' => [
      'display_label' => TRUE,
    ],
  ]);
  $display->save();
}

/**
 * Configures metatag defaults for SEO hardening.
 */
function wcf_gov_setup_metatag(): void {
  $global = MetatagDefaults::load('global');
  if ($global) {
    $tags = $global->get('tags');
    $tags['twitter_cards_type'] = 'summary_large_image';
    $tags['twitter_cards_title'] = '[current-page:title]';
    $tags['twitter_cards_description'] = '[site:slogan]';
    $global->set('tags', $tags);
    $global->save();
    wcf_gov_print('Updated global metatag defaults with Twitter cards.');
  }

  $node = MetatagDefaults::load('node');
  if ($node) {
    $tags = $node->get('tags');
    $tags['og_image'] = '[node:field_main_image:entity:field_media_image:entity:url]';
    $tags['twitter_cards_image'] = '[node:field_main_image:entity:field_media_image:entity:url]';
    $tags['twitter_cards_type'] = 'summary_large_image';
    $tags['twitter_cards_title'] = '[node:title]';
    $tags['twitter_cards_description'] = '[node:summary]';
    $node->set('tags', $tags);
    $node->save();
    wcf_gov_print('Updated node metatag defaults with OG/Twitter image fallback.');
  }

  wcf_gov_ensure_metatag_default('node__homepage', 'Content: Homepage', [
    'title' => '[node:title] | [site:name]',
    'description' => '[site:slogan]',
    'canonical_url' => '[node:url]',
    'og_title' => '[node:title]',
    'og_description' => '[site:slogan]',
    'og_url' => '[node:url:absolute]',
    'og_type' => 'website',
    'twitter_cards_type' => 'summary_large_image',
    'twitter_cards_title' => '[node:title]',
    'twitter_cards_description' => '[site:slogan]',
  ]);

  wcf_gov_ensure_metatag_default('node__container_home', 'Content: Container Home', [
    'title' => '[node:title] | [site:name]',
    'description' => '[node:summary]',
    'canonical_url' => '[node:url]',
    'og_title' => '[node:title]',
    'og_description' => '[node:summary]',
    'og_url' => '[node:url:absolute]',
    'og_image' => '[node:field_main_image:entity:field_media_image:entity:url]',
    'og_type' => 'article',
    'twitter_cards_type' => 'summary_large_image',
    'twitter_cards_title' => '[node:title]',
    'twitter_cards_description' => '[node:summary]',
    'twitter_cards_image' => '[node:field_main_image:entity:field_media_image:entity:url]',
  ]);

  $stallion = MetatagDefaults::load('node__stallion');
  if ($stallion) {
    $tags = $stallion->get('tags');
    $tags['og_image'] = '[node:field_main_image:entity:field_media_image:entity:url]';
    $tags['twitter_cards_type'] = 'summary_large_image';
    $tags['twitter_cards_title'] = '[node:title]';
    $tags['twitter_cards_description'] = '[node:summary]';
    $tags['twitter_cards_image'] = '[node:field_main_image:entity:field_media_image:entity:url]';
    $stallion->set('tags', $tags);
    $stallion->save();
    wcf_gov_print('Updated stallion metatag defaults.');
  }

  $article = MetatagDefaults::load('node__article');
  if (!$article) {
    wcf_gov_ensure_metatag_default('node__article', 'Content: Article', [
      'title' => '[node:title] | [site:name]',
      'description' => '[node:summary]',
      'canonical_url' => '[node:url]',
      'og_title' => '[node:title]',
      'og_description' => '[node:summary]',
      'og_url' => '[node:url:absolute]',
      'og_image' => '[node:field_image:url]',
      'og_type' => 'article',
      'twitter_cards_type' => 'summary_large_image',
      'twitter_cards_title' => '[node:title]',
      'twitter_cards_description' => '[node:summary]',
      'twitter_cards_image' => '[node:field_image:url]',
    ]);
  }
}

/**
 * Creates or updates a metatag defaults entity.
 */
function wcf_gov_ensure_metatag_default(string $id, string $label, array $tags): void {
  $defaults = MetatagDefaults::load($id);
  if ($defaults) {
    $defaults->set('tags', array_merge($defaults->get('tags') ?? [], $tags));
    $defaults->save();
    wcf_gov_print("Updated metatag defaults: {$id}");
    return;
  }
  MetatagDefaults::create([
    'id' => $id,
    'label' => $label,
    'tags' => $tags,
    'status' => TRUE,
    'langcode' => 'en',
  ])->save();
  wcf_gov_print("Created metatag defaults: {$id}");
}

/**
 * Enables simple_sitemap bundle inclusion for WCF content types.
 */
function wcf_gov_setup_sitemap(): void {
  $bundles = [
    'homepage' => ['priority' => '1.0', 'changefreq' => 'weekly'],
    'stallion' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'article' => ['priority' => '0.6', 'changefreq' => 'monthly'],
    'container_home' => ['priority' => '0.7', 'changefreq' => 'weekly'],
  ];
  $config_factory = \Drupal::configFactory();
  foreach ($bundles as $bundle => $settings) {
    $config_name = "simple_sitemap.bundle_settings.default.node.{$bundle}";
    $config = $config_factory->getEditable($config_name);
    $config->set('index', TRUE);
    $config->set('priority', $settings['priority']);
    $config->set('changefreq', $settings['changefreq']);
    $config->set('include_images', FALSE);
    $config->save();
    wcf_gov_print("Sitemap enabled for node bundle: {$bundle}");
  }

  $settings = $config_factory->getEditable('simple_sitemap.settings');
  $settings->set('max_links', 20000);
  $settings->set('cron_generation', TRUE);
  $settings->save();
  wcf_gov_print('Updated simple_sitemap settings.');
}

/**
 * Improves admin content overview and editorial UX labels.
 */
function wcf_gov_setup_admin_ux(): void {
  foreach (['homepage', 'stallion', 'article'] as $bundle) {
    $type = NodeType::load($bundle);
    if ($type) {
      $type->set('help', 'Use the editorial workflow: save as Draft, submit for In review, then Publish when ready. Revisions are kept automatically.');
      $type->save();
    }
  }
}

// --- Execution ---
wcf_gov_print('WCF governance setup starting…');
wcf_gov_setup_search_api();
wcf_gov_setup_search_view();
wcf_gov_setup_editorial_workflow();
wcf_gov_setup_metatag();
wcf_gov_setup_sitemap();
wcf_gov_setup_admin_ux();
wcf_gov_print('WCF governance setup complete.');
