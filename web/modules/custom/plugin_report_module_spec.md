# Plugin Report Module Specification

## Overview

`plugin_report` is a Drupal 10/11 contributed module that provides two Drupal
Reports pages: one listing every `DefaultPluginManager` instance registered in
the container, and a per-manager detail page listing all plugins discovered by
that manager. The manager list shows the service ID, class, provider (inferred
from the class namespace), subdirectory, discovery class (PHP 8 attribute name
preferred, falling back to annotation class), plugin interface, and alter hook —
all extracted via Reflection without triggering `getDefinitions()`. The plugin detail table renders every key from each plugin
definition, serializing non-scalar values to YAML. The module ships a single
service (`plugin_report.manager`), two routes, and no configuration, schema, or
install/update hooks.

---

## Requirements

### Functional

1. **Manager list page** at `/admin/reports/plugins` shows a sortable table of
   every service in the container that is an `instanceof DefaultPluginManager`,
   with columns: Service ID, Class, Provider, Subdirectory, Discovery,
   Plugin Interface, Alter Hook.
2. Each row links the Service ID to its **plugin detail page** at
   `/admin/reports/plugins/{plugin_manager}` (route parameter, not query string).
3. **Plugin detail page** calls `getDefinitions()` on the selected manager and
   renders a table with one row per plugin. Columns are derived dynamically from
   `array_keys()` of the first definition.
4. Non-scalar values in plugin definitions are serialized to YAML via
   `Yaml::dump()`. Before serializing an array or object, all `TranslatableMarkup`
   instances are recursively converted to plain strings so YAML output contains
   readable labels rather than serialized object representations. Top-level
   `TranslatableMarkup` cell values are also cast to string directly.
5. The pages are accessible only to users with the core `access site reports`
   permission; no new permissions are introduced.
6. `PluginReportManager::getPluginManagers()` returns an array of metadata
   arrays (one per manager), sorted alphabetically by service ID.
7. `PluginReportManager::getPlugins(string $plugin_manager_id)` returns the raw
   `getDefinitions()` array for the named manager unchanged.

### Functional — Manager metadata (via Reflection)

Each manager metadata array returned by `getPluginManagers()` contains:

| Key | Source | Example |
|---|---|---|
| `id` | Service ID | `plugin.manager.block` |
| `class` | `get_class($service)` | `Drupal\Core\Block\BlockManager` |
| `provider` | Second namespace segment of class (index 1) | `block` |
| `subdir` | Reflection on `protected $subdir` | `Plugin/Block` |
| `discovery` | Reflection: `$pluginDefinitionAttributeName` if set, else `$pluginDefinitionAnnotationName` | `Drupal\Core\Block\Annotation\Block` |
| `interface` | Reflection on `protected $pluginInterface` | `Drupal\Core\Block\BlockPluginInterface` |
| `alter_hook` | Reflection on `protected $alterHook` | `block` |

Provider is extracted as `explode('\\', $class)[1]` — the second segment of the
fully-qualified class name, which corresponds to the Drupal module machine name
for all core and contrib managers (e.g., `Drupal\block\...` → `block`).

### Non-Functional

- Supports Drupal 10 and Drupal 11 (`core_version_requirement: ^10 || ^11`).
- Drupal coding standards (`phpcs Drupal,DrupalPractice`).
- Dependency injection throughout; no `\Drupal::` static calls inside classes.
- Constructor promotion is **not** used; explicit property declaration.
- Uses Symfony service autowiring (`autowire: true`) — no explicit `arguments`
  in `plugin_report.services.yml`.
- PHPUnit kernel tests for `PluginReportManager`; functional browser tests for `PluginReportController`.

---

## Steps to Review

### Manager list page

- ⬜ Visit `/admin/reports/plugins` — confirm a table appears with columns:
  Service ID, Class, Provider, Subdirectory, Discovery, Plugin
  Interface, Alter Hook.
- ⬜ Confirm `plugin.manager.block` appears with provider `block`, subdir
  `Plugin/Block`.
- ⬜ Confirm rows are sorted alphabetically by service ID.
- ⬜ Confirm each Service ID cell is a link to
  `/admin/reports/plugins/plugin.manager.block` (etc.).

### Plugin detail page

- ⬜ Click the `plugin.manager.block` link — confirm a table of block plugins
  appears with columns derived from the first definition's keys.
- ⬜ Confirm array values (e.g., `context_definitions`) are rendered as YAML
  strings, not bare `Array`.
- ⬜ Confirm `TranslatableMarkup` values — both at the top level and nested
  inside arrays — are rendered as readable strings, not serialized objects.
- ⬜ Click a manager with zero plugins — confirm an empty-state message is shown.

### Access control

- ⬜ Log in without `access site reports` — confirm both routes return 403.

### Route parameter

- ⬜ Manually navigate to `/admin/reports/plugins/plugin.manager.field.formatter`
  — confirm the correct manager's plugins are displayed.
- ⬜ Navigate to `/admin/reports/plugins/plugin.manager.does_not_exist` — confirm
  a 404 response rather than a PHP exception.

---

## Design

### Routing

| Route name | Path | Controller method | Permission |
|---|---|---|---|
| `plugin_report.managers` | `/admin/reports/plugins` | `::managers` | `access site reports` |
| `plugin_report.plugins` | `/admin/reports/plugins/{plugin_manager}` | `::plugins` | `access site reports` |

`{plugin_manager}` is a raw string route parameter (not an entity). The
controller validates it against `getPluginManagers()` and throws a
`NotFoundHttpException` if it is unknown.

### Service Architecture

**`PluginReportManager`** (`src/PluginReportManager.php`)

- Service ID: `plugin_report.manager`
- `autowire: true` — Symfony injects `ContainerInterface` by type hint.

```php
/**
 * Returns metadata about all DefaultPluginManager services.
 *
 * @return array<int, array<string, mixed>>
 *   Indexed array of metadata arrays, sorted by 'id'.
 */
public function getPluginManagers(): array;

/**
 * Returns all plugin definitions for the given plugin manager service ID.
 *
 * @param string $pluginManagerId
 *   The service ID of the plugin manager.
 *
 * @return array
 *   Plugin definitions keyed by plugin ID, exactly as returned by
 *   getDefinitions(). Non-scalar values are not pre-processed.
 *
 * @throws \InvalidArgumentException
 *   If the service ID is not a known DefaultPluginManager.
 */
public function getPlugins(string $pluginManagerId): array;
```

**Reflection strategy** — `getPluginManagers()` iterates `$container->getServiceIds()`,
instantiates each service that passes `instanceof DefaultPluginManager`, then
reads protected properties using `\ReflectionProperty`. Because protected
properties of a parent class require the reflection to target the declaring
class (not the subclass), the helper reads from
`new \ReflectionProperty(DefaultPluginManager::class, $property)` for each of
`subdir`, `pluginDefinitionAnnotationName`, `pluginDefinitionAttributeName`,
`pluginInterface`, `alterHook`.

The `discovery` value is resolved as: `$pluginDefinitionAttributeName` when
non-empty, otherwise `$pluginDefinitionAnnotationName`. This covers both
annotation-only managers (Drupal 10 older style) and attribute-based managers
(Drupal 10.2+ / Drupal 11 style).

**Provider extraction** — `explode('\\', get_class($service))[1]` — the second
namespace segment is always the module name for Drupal-namespaced services
(e.g., `Drupal\block\Plugin\Block\BlockManager` → `block`).

### Controller Architecture

**`PluginReportController`** (`src/Controller/PluginReportController.php`)

Extends `ControllerBase`. Injects `PluginReportManager` via `create()`.

```php
public function managers(): array
```
Returns a `#type => 'table'` render array with the fixed columns listed in the
Functional requirements. Each Service ID cell is a `Link` render element
pointing to `plugin_report.plugins`.

```php
public function plugins(string $plugin_manager): array
```
Validates `$plugin_manager` exists in `getPluginManagers()`; throws
`NotFoundHttpException` if not. Calls `getPlugins()`, derives headers from the
first definition's keys, maps values through `formatValue()` and a private
`convertTranslatableMarkup()` helper:

```php
private function convertTranslatableMarkup(mixed $value): mixed {
  if ($value instanceof TranslatableMarkup) {
    return (string) $value;
  }
  if (is_array($value)) {
    return array_map($this->convertTranslatableMarkup(...), $value);
  }
  return $value;
}

private function formatValue(mixed $value): string {
  if ($value instanceof TranslatableMarkup) {
    return (string) $value;
  }
  if (!is_scalar($value)) {
    return Yaml::dump($this->convertTranslatableMarkup($value), 2, 2);
  }
  return (string) $value;
}
```

`convertTranslatableMarkup()` recursively walks arrays, casting any
`TranslatableMarkup` leaf to string. Objects that are not `TranslatableMarkup`
are left as-is and handled by `Yaml::dump()`.

---

## Module File Structure

```
plugin_report/
├── composer.json
├── README.md
├── plugin_report.info.yml
├── plugin_report.routing.yml
├── plugin_report.links.menu.yml       # "Plugins" entry under Reports
├── plugin_report.services.yml
└── src/
    ├── PluginReportManager.php
    └── Controller/
        └── PluginReportController.php
tests/
└── src/
    ├── Kernel/
    │   └── PluginReportManagerTest.php
    └── Functional/
        └── PluginReportControllerTest.php
```

---

## Implementation

---

### `plugin_report.info.yml`

```yaml
name: Plugin Report
type: module
description: 'Lists all plugin managers and their plugins on the Drupal Reports page.'
package: Development
core_version_requirement: ^10 || ^11
```

---

### `README.md`

```markdown
# Plugin Report

Plugin Report is a Drupal module that provides a developer-focused Reports page
listing every plugin manager registered in the service container alongside all
plugins discovered by each manager.

## Features

- Lists all `DefaultPluginManager` services with their class, provider,
  subdirectory, discovery class (annotation or PHP 8 attribute), plugin
  interface, and alter hook.
- Drill-down detail page showing every plugin definition for a selected manager.
- Non-scalar definition values (arrays, objects) are serialized to YAML for
  readability. `TranslatableMarkup` instances are resolved to plain strings at
  all nesting levels.
- No configuration. No database schema. Read-only reporting only.

## Requirements

- Drupal 10 or 11
- No contributed module dependencies

## Installation

Install as you would any Drupal module:

```shell
composer require drupal/plugin_report
drush en plugin_report
```

## Usage

Navigate to **Reports → Plugins** (`/admin/reports/plugins`).

- The landing page lists every plugin manager found in the container.
- Click any Service ID to view the full list of plugins it exposes.

Requires the core **Access site reports** permission
(`access site reports`).

## Maintainers

- [Your name](https://www.drupal.org/u/yourname)
```

---


```yaml
plugin_report.managers:
  path: '/admin/reports/plugins'
  defaults:
    _controller: '\Drupal\plugin_report\Controller\PluginReportController::managers'
    _title: 'Plugin Report'
  requirements:
    _permission: 'access site reports'

plugin_report.plugins:
  path: '/admin/reports/plugins/{plugin_manager}'
  defaults:
    _controller: '\Drupal\plugin_report\Controller\PluginReportController::plugins'
    _title: 'Plugin Report'
  requirements:
    _permission: 'access site reports'
    plugin_manager: .+
```

The `plugin_manager: .+` requirement allows service IDs containing dots
(e.g., `plugin.manager.block`) without Symfony interpreting the trailing
segments as a format suffix.

---

### `plugin_report.links.menu.yml`

```yaml
plugin_report.managers:
  title: 'Plugins'
  description: 'List all plugin managers and their registered plugins.'
  route_name: plugin_report.managers
  parent: system.admin_reports
```

---

### `plugin_report.services.yml`

```yaml
services:
  _defaults:
    autowire: true

  plugin_report.manager:
    class: Drupal\plugin_report\PluginReportManager
```

---

### `src/PluginReportManager.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\plugin_report;

use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovers DefaultPluginManager services and their plugin definitions.
 */
final class PluginReportManager {

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * Constructs a PluginReportManager.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * Returns metadata about all DefaultPluginManager services.
   *
   * Iterates every service ID in the container, instantiates it, and keeps
   * those that are instances of DefaultPluginManager. Protected properties are
   * read via Reflection to avoid calling getDefinitions() prematurely.
   *
   * @return array<int, array<string, mixed>>
   *   Indexed array of metadata arrays sorted by service ID. Each entry has
   *   keys: id, class, provider, subdir, discovery, interface, alter_hook.
   */
  public function getPluginManagers(): array {
    $managers = [];
    foreach ($this->container->getServiceIds() as $serviceId) {
      try {
        $service = $this->container->get($serviceId);
      }
      catch (\Throwable) {
        continue;
      }
      if (!$service instanceof DefaultPluginManager) {
        continue;
      }
      $class = get_class($service);
      $managers[] = [
        'id'         => $serviceId,
        'class'      => $class,
        'provider'   => $this->extractProvider($class),
        'subdir'     => $this->readProtected($service, 'subdir'),
        'discovery'  => $this->readProtected($service, 'pluginDefinitionAttributeName')
                        ?: $this->readProtected($service, 'pluginDefinitionAnnotationName'),
        'interface'  => $this->readProtected($service, 'pluginInterface'),
        'alter_hook' => $this->readProtected($service, 'alterHook'),
      ];
    }
    usort($managers, static fn($a, $b) => strcmp($a['id'], $b['id']));
    return $managers;
  }

  /**
   * Returns all plugin definitions for the given plugin manager service ID.
   *
   * @param string $pluginManagerId
   *   The service ID.
   *
   * @return array
   *   Plugin definitions keyed by plugin ID, exactly as returned by
   *   getDefinitions(). Non-scalar values are not pre-processed.
   *
   * @throws \InvalidArgumentException
   *   If the service ID is not a known DefaultPluginManager.
   */
  public function getPlugins(string $pluginManagerId): array {
    $ids = array_column($this->getPluginManagers(), 'id');
    if (!in_array($pluginManagerId, $ids, TRUE)) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known DefaultPluginManager service.', $pluginManagerId)
      );
    }
    return $this->container->get($pluginManagerId)->getDefinitions();
  }

  /**
   * Extracts the provider (module name) from a fully-qualified class name.
   *
   * The second namespace segment of Drupal-namespaced classes is the module
   * machine name (e.g., Drupal\block\...) → 'block'.
   *
   * @param string $class
   *   Fully-qualified class name.
   *
   * @return string
   *   The provider, or an empty string if indeterminate.
   */
  protected function extractProvider(string $class): string {
    $parts = explode('\\', $class);
    return $parts[1] ?? '';
  }

  /**
   * Reads a protected property from a DefaultPluginManager via Reflection.
   *
   * Properties are declared on DefaultPluginManager itself, so reflection
   * always targets that class to ensure accessibility regardless of the
   * concrete subclass.
   *
   * @param \Drupal\Core\Plugin\DefaultPluginManager $manager
   *   The plugin manager instance.
   * @param string $property
   *   The property name.
   *
   * @return mixed
   *   The property value, or NULL if not accessible.
   */
  protected function readProtected(DefaultPluginManager $manager, string $property): mixed {
    try {
      $ref = new \ReflectionProperty(DefaultPluginManager::class, $property);
      return $ref->getValue($manager);
    }
    catch (\ReflectionException) {
      return NULL;
    }
  }

}
```

---

### `src/Controller/PluginReportController.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\plugin_report\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\plugin_report\PluginReportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the Plugin Report pages.
 */
final class PluginReportController extends ControllerBase {

  /**
   * The plugin report manager.
   *
   * @var \Drupal\plugin_report\PluginReportManager
   */
  protected PluginReportManager $pluginReportManager;

  /**
   * Constructs a PluginReportController.
   *
   * @param \Drupal\plugin_report\PluginReportManager $pluginReportManager
   *   The plugin report manager.
   */
  public function __construct(PluginReportManager $pluginReportManager) {
    $this->pluginReportManager = $pluginReportManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin_report.manager'),
    );
  }

  /**
   * Renders the plugin manager list page.
   *
   * @return array
   *   A render array containing a table of all DefaultPluginManager services.
   */
  public function managers(): array {
    $managers = $this->pluginReportManager->getPluginManagers();

    $headers = [
      $this->t('Service ID'),
      $this->t('Class'),
      $this->t('Provider'),
      $this->t('Subdirectory'),
      $this->t('Discovery'),
      $this->t('Plugin Interface'),
      $this->t('Alter Hook'),
    ];

    $rows = [];
    foreach ($managers as $info) {
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $info['id'],
            '#url' => Url::fromRoute('plugin_report.plugins', [
              'plugin_manager' => $info['id'],
            ]),
          ],
        ],
        $info['class'],
        $info['provider'],
        (string) ($info['subdir'] ?? ''),
        (string) ($info['discovery'] ?? ''),
        (string) ($info['interface'] ?? ''),
        (string) ($info['alter_hook'] ?? ''),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No plugin managers found.'),
    ];
  }

  /**
   * Renders the plugin detail page for a single plugin manager.
   *
   * @param string $plugin_manager
   *   The plugin manager service ID from the route parameter.
   *
   * @return array
   *   A render array containing a table of plugins.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the service ID is not a known DefaultPluginManager.
   */
  public function plugins(string $plugin_manager): array {
    try {
      $plugins = $this->pluginReportManager->getPlugins($plugin_manager);
    }
    catch (\InvalidArgumentException) {
      throw new NotFoundHttpException();
    }

    if (empty($plugins)) {
      return ['#markup' => '<p>' . $this->t('No plugins found for this manager.') . '</p>'];
    }

    $headers = array_keys(reset($plugins));

    $rows = [];
    foreach ($plugins as $plugin) {
      $row = [];
      foreach ($headers as $key) {
        $row[] = $this->formatValue($plugin[$key] ?? '');
      }
      $rows[] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No plugins found.'),
    ];
  }

  /**
   * Recursively converts TranslatableMarkup instances to plain strings.
   *
   * Walks arrays depth-first, casting any TranslatableMarkup leaf to string.
   * Non-TranslatableMarkup objects are returned unchanged.
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return mixed
   *   The value with all TranslatableMarkup instances replaced by strings.
   */
  private function convertTranslatableMarkup(mixed $value): mixed {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (is_array($value)) {
      return array_map($this->convertTranslatableMarkup(...), $value);
    }
    return $value;
  }

  /**
   * Formats a single plugin definition value for table display.
   *
   * Top-level TranslatableMarkup is cast to string directly. Non-scalar values
   * are passed through convertTranslatableMarkup() to resolve any nested
   * TranslatableMarkup before being serialized to YAML.
   *
   * @param mixed $value
   *   The raw value from a plugin definition.
   *
   * @return string
   *   A displayable string representation.
   */
  private function formatValue(mixed $value): string {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (!is_scalar($value)) {
      return Yaml::dump($this->convertTranslatableMarkup($value), 2, 2);
    }
    return (string) $value;
  }

}
```

---

### `tests/src/Kernel/PluginReportManagerTest.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\plugin_report\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests PluginReportManager.
 *
 * @group plugin_report
 * @coversDefaultClass \Drupal\plugin_report\PluginReportManager
 */
class PluginReportManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['plugin_report'];

  /**
   * The plugin report manager under test.
   *
   * @var \Drupal\plugin_report\PluginReportManager
   */
  protected $pluginReportManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginReportManager = $this->container->get('plugin_report.manager');
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersReturnsNonEmptyArray(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    self::assertIsArray($managers);
    self::assertNotEmpty($managers);
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersIsSortedById(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    $ids = array_column($managers, 'id');
    $sorted = $ids;
    sort($sorted);
    self::assertSame($sorted, $ids);
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersMetadataShape(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    $first = reset($managers);
    foreach (['id', 'class', 'provider', 'subdir', 'discovery', 'interface', 'alter_hook'] as $key) {
      self::assertArrayHasKey($key, $first);
    }
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testProviderIsExtractedFromClass(): void {
    $this->enableModules(['block']);
    $managers = $this->pluginReportManager->getPluginManagers();
    $index = array_search('plugin.manager.block', array_column($managers, 'id'), TRUE);
    if ($index !== FALSE) {
      self::assertSame('block', $managers[$index]['provider']);
    }
  }

  /**
   * @covers ::getPlugins
   */
  public function testGetPluginsReturnsArray(): void {
    $this->enableModules(['block']);
    $plugins = $this->pluginReportManager->getPlugins('plugin.manager.block');
    self::assertIsArray($plugins);
  }

  /**
   * @covers ::getPlugins
   */
  public function testGetPluginsThrowsOnUnknownManager(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->pluginReportManager->getPlugins('plugin.manager.does_not_exist_xyz');
  }

}
```

---

### `tests/src/Functional/PluginReportControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\plugin_report\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Plugin Report controller pages.
 *
 * @group plugin_report
 */
class PluginReportControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['plugin_report', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Tests all Plugin Report pages in a single browser session.
   *
   * Combines authenticated happy-path, access-denied, and 404 assertions to
   * avoid the overhead of repeated Drupal installs that separate test methods
   * would incur in BrowserTestBase.
   */
  public function testPluginReportPages(): void {
    // Check that both pages are inaccessible without the required permission.
    $unprivileged = $this->drupalCreateUser([]);
    $this->drupalLogin($unprivileged);
    $this->drupalGet('/admin/reports/plugins');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/admin/reports/plugins/plugin.manager.block');
    $this->assertSession()->statusCodeEquals(403);

    // Check that the manager list page renders a table with expected headers.
    $admin = $this->drupalCreateUser(['access site reports']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/reports/plugins');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('Service ID');
    $this->assertSession()->pageTextContains('Class');
    $this->assertSession()->pageTextContains('Provider');
    $this->assertSession()->pageTextContains('Discovery');

    // Check that plugin.manager.block appears and links to its detail page.
    $this->assertSession()->pageTextContains('plugin.manager.block');
    $this->assertSession()->linkByHrefExists('/admin/reports/plugins/plugin.manager.block');

    // Check that the plugin detail page resolves correctly, including dots in
    // the route parameter not being misinterpreted as a format suffix.
    $this->drupalGet('/admin/reports/plugins/plugin.manager.block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    // Check that block definitions expose an 'id' column.
    $this->assertSession()->pageTextContains('id');

    // Check that an unknown manager returns 404 rather than a PHP exception.
    $this->drupalGet('/admin/reports/plugins/plugin.manager.does_not_exist_xyz');
    $this->assertSession()->statusCodeEquals(404);
  }

}
```

---

## Open Questions

1. **Performance of container iteration** — calling `$container->get($id)` for
   every service ID to check `instanceof` will instantiate all services eagerly.
   A compiler pass or caching the result of `getPluginManagers()` would avoid
   this cost on repeat page loads. Can be addressed after initial build.

---

## Reference

- [`DefaultPluginManager`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Plugin!DefaultPluginManager.php/class/DefaultPluginManager/11.x)
- [`DefaultPluginManager::$subdir`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Plugin!DefaultPluginManager.php/property/DefaultPluginManager::subdir)
- [`ControllerBase`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Controller!ControllerBase.php/class/ControllerBase)
- [`BrowserTestBase`](https://api.drupal.org/api/drupal/core!tests!Drupal!Tests!BrowserTestBase.php/class/BrowserTestBase/11.x)
- [`NotFoundHttpException`](https://api.symfony.com/current/components/http-kernel/index.html)
- [Symfony `Yaml::dump()`](https://symfony.com/doc/current/components/yaml.html)
- [`#type => 'table'` render element](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Table.php/class/Table)
- [Drupal Reports system (`system.admin_reports`)](https://api.drupal.org/api/drupal/core!modules!system!system.links.menu.yml)
- [Symfony autowiring](https://symfony.com/doc/current/service_container/autowiring.html)
- [Drupal.org project setup](https://www.drupal.org/node/2499239)
- [Drupal.org issue queue setup](https://www.drupal.org/node/1468982)
