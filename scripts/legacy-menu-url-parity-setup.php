<?php

/**
 * @file
 * Legacy menu links, footer block, and category/marketing redirects.
 *
 * Usage:
 *   ddev drush php:script scripts/legacy-menu-url-parity-setup.php
 *   ddev drush php:script scripts/legacy-menu-url-parity-setup.php -- --apply
 */

use Drupal\block\Entity\Block;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
/** @var \Drupal\redirect\RedirectRepository $redirect_repository */
$redirect_repository = \Drupal::service('redirect.repository');
$database = \Drupal::database();

// Main menu Home is provided by standard.front_page; do not duplicate.
$main_links = [
  ['title' => 'Stallions', 'uri' => 'internal:/stallions', 'weight' => 1],
  ['title' => 'Search', 'uri' => 'internal:/search', 'weight' => 2],
  ['title' => 'Nomination', 'uri' => 'internal:/nomination', 'weight' => 3],
  ['title' => 'Contact', 'uri' => 'internal:/contact-us', 'weight' => 4],
];
// Re-weight after standard.front_page (typically weight 0).

$footer_links = [
  ['title' => 'Home', 'uri' => 'internal:/', 'weight' => 0],
  ['title' => 'Stallions', 'uri' => 'internal:/stallions', 'weight' => 1],
  ['title' => 'Search', 'uri' => 'internal:/search', 'weight' => 2],
  ['title' => 'Nomination', 'uri' => 'internal:/nomination', 'weight' => 3],
  ['title' => 'Contact', 'uri' => 'internal:/contact-us', 'weight' => 4],
  [
    'title' => 'Facebook',
    'uri' => 'https://www.facebook.com/pages/Winning-Colours-Farm/353221651451104',
    'weight' => 5,
    'options' => ['attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer']],
  ],
];

// D7 hardcoded paths use underscores for some categories; task list uses hyphens.
$legacy_redirects = [
  'category/stallions' => 'internal:/stallions',
  'category/asb-stallions' => 'internal:/stallions',
  'category/asb_stallions' => 'internal:/stallions',
  'category/foals' => 'internal:/stallions',
  'category/broodmares' => 'internal:/stallions',
  'category/for-sale' => 'internal:/stallions',
  'category/sold' => 'internal:/stallions',
  'showcase' => 'internal:/',
  'sold-stallions' => 'internal:/stallions',
];

$report = [
  'apply' => $apply,
  'menu_links' => ['planned' => [], 'created' => 0, 'skipped' => 0],
  'footer_block' => ['status' => 'planned'],
  'redirects' => ['planned' => [], 'created' => 0, 'skipped' => 0, 'alias_conflicts' => []],
];

/**
 * Finds an existing menu link by menu, title, and URI.
 */
$find_menu_link = static function (string $menu_name, string $title, string $uri): ?MenuLinkContent {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('menu_name', $menu_name)
    ->execute();
  foreach ($storage->loadMultiple($ids) as $link) {
    if ($link->get('link')->isEmpty()) {
      continue;
    }
    $existing_uri = (string) $link->get('link')->uri;
    if ($link->getTitle() === $title && $existing_uri === $uri) {
      return $link;
    }
  }
  return NULL;
};

foreach (['main' => $main_links, 'footer' => $footer_links] as $menu_name => $links) {
  foreach ($links as $item) {
    $entry = [
      'menu' => $menu_name,
      'title' => $item['title'],
      'uri' => $item['uri'],
      'status' => 'planned',
    ];
    $existing = $find_menu_link($menu_name, $item['title'], $item['uri']);
    if ($existing) {
      $entry['status'] = 'exists';
      $report['menu_links']['skipped']++;
    }
    elseif ($apply) {
      $values = [
        'title' => $item['title'],
        'link' => ['uri' => $item['uri']],
        'menu_name' => $menu_name,
        'weight' => $item['weight'],
        'enabled' => TRUE,
      ];
      if (!empty($item['options'])) {
        $values['link']['options'] = $item['options'];
      }
      MenuLinkContent::create($values)->save();
      $entry['status'] = 'created';
      $report['menu_links']['created']++;
    }
    $report['menu_links']['planned'][] = $entry;
  }
}

$block_id = 'wcf_theme_footer';
$existing_block = Block::load($block_id);
if ($existing_block) {
  $report['footer_block'] = ['status' => 'exists', 'id' => $block_id];
}
elseif ($apply) {
  $block = Block::create([
    'id' => $block_id,
    'theme' => 'wcf_theme',
    'plugin' => 'system_menu_block:footer',
    'region' => 'footer',
    'weight' => 0,
    'settings' => [
      'id' => 'system_menu_block:footer',
      'label' => 'Footer',
      'label_display' => '0',
      'provider' => 'system',
      'level' => 1,
      'depth' => 0,
      'expand_all_items' => FALSE,
    ],
    'visibility' => [],
  ]);
  $block->save();
  $report['footer_block'] = ['status' => 'created', 'id' => $block_id];
}

foreach ($legacy_redirects as $source => $target_uri) {
  $source_path = ltrim($source, '/');
  $alias_row = $database->query(
    'SELECT alias, path FROM {path_alias} WHERE alias = :alias AND status = 1 LIMIT 1',
    [':alias' => '/' . $source_path]
  )->fetchAssoc();

  $entry = [
    'source' => '/' . $source_path,
    'target' => $target_uri,
    'status' => 'planned',
  ];

  if ($alias_row) {
    $entry['status'] = 'alias_conflict';
    $entry['alias_path'] = $alias_row['path'];
    $report['redirects']['alias_conflicts'][] = $entry;
    continue;
  }

  $existing = $redirect_repository->findMatchingRedirect($source_path, [], $default_langcode);
  if ($existing) {
    $entry['status'] = 'exists';
    $report['redirects']['skipped']++;
  }
  elseif ($apply) {
    Redirect::create([
      'redirect_source' => ['path' => $source_path],
      'redirect_redirect' => ['uri' => $target_uri],
      'language' => $default_langcode,
      'status_code' => 301,
    ])->save();
    $entry['status'] = 'created';
    $report['redirects']['created']++;
  }
  $report['redirects']['planned'][] = $entry;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
