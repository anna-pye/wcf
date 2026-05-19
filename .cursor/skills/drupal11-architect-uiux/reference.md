# Drupal 11 reference patterns

Read only when implementing non-trivial backend or form work.

## Service / plugin skeleton

```php
<?php

declare(strict_types=1);

namespace Drupal\example\Foo;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ExampleService implements ContainerFactoryPluginInterface {

  public function __construct(
    // Inject interfaces, not concrete classes when possible.
  ) {}

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      // $container->get('entity_type.manager'),
    );
  }
}
```

Register in `example.services.yml` with appropriate tags (`event_subscriber`, `access_check`, etc.).

## Routes and access

```yaml
# example.routing.yml
example.page:
  path: '/example'
  defaults:
    _controller: '\Drupal\example\Controller\ExampleController::page'
    _title: 'Example'
  requirements:
    _permission: 'access content'
    # Or _custom_access for vendor-scoped checks.
```

## Render arrays and caching

```php
$build = [
  '#theme' => 'example_component',
  '#data' => $data,
  '#cache' => [
    'keys' => ['example', 'list', $bundle],
    'contexts' => ['url.query_args', 'languages:language_interface'],
    'tags' => ['node_list:' . $bundle],
    'max-age' => Cache::PERMANENT,
  ],
];
```

Invalidate via entity hooks or `Cache::invalidateTags()` when content changes — not global `drush cr` in production workflows.

## Forms

- Extend `FormBase` or `ConfigFormBase`; inject services via `create()`.
- Use `#states` for UX only; validate permissions and ownership in `validateForm()` / submit handlers.

## Config workflow

```bash
# From project root (DDEV example)
ddev drush config:export -y
ddev drush config:import -y
ddev drush updatedb -y
ddev drush cr   # Use sparingly; prefer cache tag invalidation
```

## Theme preprocess pattern

In `*.theme`, keep preprocessors thin: prepare variables, delegate View/embed building to helpers, respect `access()` on URLs and entities.

## UI/UX anti-patterns

| Avoid | Prefer |
|-------|--------|
| Inline styles in Twig | SCSS component partials |
| `max-age: 0` on listings | Tag/context-aware cache |
| Multiple global CSS libraries | One `global` + conditional attachments |
| Guessing field machine names | Config or `field.storage.*` in repo |
| Hiding admin links only | Route/entity access denial |

## Useful docs in this repo

- `docs/platform-performance-audit.md` — caching, Search API, facets, libraries
- `docs/hero-slider-migration-strategy.md` — migration UX/content modeling notes
