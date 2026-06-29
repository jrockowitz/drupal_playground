# Term Reference Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a contrib-ready Drupal module that adds a `References` tab to taxonomy term pages and lets editors add or remove the current term from eligible entity reference fields.

**Architecture:** The module discovers taxonomy-term entity reference field groups by `{entity_type_id}.{field_name}`, generates dynamic routes for each group, and generates matching taxonomy term local tasks through a local-task deriver. A management form uses generic content entity APIs, entity queries, entity access, and field access to add and remove the current term.

**Tech Stack:** Drupal 10/11 custom module, PHP 8.3, Symfony routes, Drupal local task derivers, Drupal Form API, BrowserTestBase functional tests, DDEV.

---

## File Structure

- Create `web/modules/custom/term_reference/term_reference.info.yml`: module metadata.
- Create `web/modules/custom/term_reference/term_reference.routing.yml`: route callback registration and autocomplete route.
- Create `web/modules/custom/term_reference/term_reference.links.task.yml`: primary local task plus deriver registration for secondary local tasks.
- Create `web/modules/custom/term_reference/term_reference.services.yml`: autowired services.
- Create `web/modules/custom/term_reference/term_reference.permissions.yml`: one narrow administrative fallback permission.
- Create `web/modules/custom/term_reference/src/TermReferenceDiscoveryInterface.php`: discovery contract.
- Create `web/modules/custom/term_reference/src/TermReferenceDiscovery.php`: discovers eligible `{entity_type_id}.{field_name}` groups.
- Create `web/modules/custom/term_reference/src/TermReferenceManagerInterface.php`: mutation/query contract.
- Create `web/modules/custom/term_reference/src/TermReferenceManager.php`: finds, adds, and removes references.
- Create `web/modules/custom/term_reference/src/Access/TermReferenceAccessCheck.php`: route access checker.
- Create `web/modules/custom/term_reference/src/Controller/TermReferenceAutocompleteController.php`: autocomplete for eligible entities.
- Create `web/modules/custom/term_reference/src/Controller/TermReferenceOverviewController.php`: redirects the primary `References` task to the first available secondary task.
- Create `web/modules/custom/term_reference/src/Form/TermReferenceManageForm.php`: add/remove management form.
- Create `web/modules/custom/term_reference/src/Plugin/Derivative/TermReferenceLocalTasks.php`: secondary local-task deriver.
- Create `web/modules/custom/term_reference/src/Routing/TermReferenceRoutes.php`: dynamic route callback.
- Create `web/modules/custom/term_reference/tests/modules/term_reference_test/term_reference_test.info.yml`: test helper module.
- Create `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/*.yml`: test vocabulary, bundles, media type, and fields.
- Create `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`: browser coverage.
- Create `web/modules/custom/term_reference/README.md`: concise project documentation and contrib positioning.

## Task 1: Module Skeleton And Test Fixture Module

**Files:**
- Create: `web/modules/custom/term_reference/term_reference.info.yml`
- Create: `web/modules/custom/term_reference/term_reference.permissions.yml`
- Create: `web/modules/custom/term_reference/term_reference.routing.yml`
- Create: `web/modules/custom/term_reference/term_reference.links.task.yml`
- Create: `web/modules/custom/term_reference/term_reference.services.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/term_reference_test.info.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/taxonomy.vocabulary.tags.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/node.type.page.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/node.type.article.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/media.type.image.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/field.storage.node.field_tags.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/field.field.node.page.field_tags.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/field.field.node.article.field_tags.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/field.storage.media.field_tags.yml`
- Create: `web/modules/custom/term_reference/tests/modules/term_reference_test/config/install/field.field.media.image.field_tags.yml`

- [ ] **Step 1: Create the base module metadata**

Create `web/modules/custom/term_reference/term_reference.info.yml`:

```yaml
name: 'Term Reference'
type: module
description: 'Manage taxonomy term entity references from taxonomy term pages.'
package: Taxonomy
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:field
  - drupal:taxonomy
```

- [ ] **Step 2: Create the narrow fallback permission**

Create `web/modules/custom/term_reference/term_reference.permissions.yml`:

```yaml
administer term references:
  title: 'Administer term references'
  description: 'Manage taxonomy term references even when route visibility cannot be determined through entity and field access alone.'
  restrict access: true
```

- [ ] **Step 3: Create route and task declaration files**

Create `web/modules/custom/term_reference/term_reference.routing.yml`:

```yaml
route_callbacks:
  - '\Drupal\term_reference\Routing\TermReferenceRoutes::routes'

term_reference.autocomplete:
  path: '/taxonomy/term/{taxonomy_term}/references/{entity_type_id}/{field_name}/autocomplete'
  defaults:
    _controller: '\Drupal\term_reference\Controller\TermReferenceAutocompleteController::autocomplete'
  requirements:
    _custom_access: '\Drupal\term_reference\Access\TermReferenceAccessCheck::access'
  options:
    parameters:
      taxonomy_term:
        type: entity:taxonomy_term
```

Create `web/modules/custom/term_reference/term_reference.links.task.yml`:

```yaml
term_reference.references:
  route_name: term_reference.references
  base_route: entity.taxonomy_term.canonical
  title: 'References'
  weight: 20

term_reference.reference_tasks:
  deriver: '\Drupal\term_reference\Plugin\Derivative\TermReferenceLocalTasks'
```

Create `web/modules/custom/term_reference/term_reference.services.yml`:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  Drupal\term_reference\TermReferenceDiscoveryInterface:
    class: Drupal\term_reference\TermReferenceDiscovery

  Drupal\term_reference\TermReferenceManagerInterface:
    class: Drupal\term_reference\TermReferenceManager

  Drupal\term_reference\Access\TermReferenceAccessCheck: {}

  Drupal\term_reference\Controller\TermReferenceAutocompleteController: {}

  Drupal\term_reference\Controller\TermReferenceOverviewController: {}

  Drupal\term_reference\Routing\TermReferenceRoutes: {}
```

- [ ] **Step 4: Create the test helper module metadata**

Create `web/modules/custom/term_reference/tests/modules/term_reference_test/term_reference_test.info.yml`:

```yaml
name: 'Term Reference Test'
type: module
description: 'Provides test fixtures for Term Reference.'
package: Testing
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:field
  - drupal:media
  - drupal:node
  - drupal:taxonomy
  - term_reference:term_reference
```

- [ ] **Step 5: Add fixture configuration**

Create install config for a `tags` vocabulary, `page` and `article` node types, an `image` media type, and `field_tags` fields on nodes and media. Use standard Drupal field config YAML exported from a local throwaway site or generated through the UI, then normalize these values:

```yaml
# field.storage.node.field_tags.yml
id: node.field_tags
field_name: field_tags
entity_type: node
type: entity_reference
settings:
  target_type: taxonomy_term
cardinality: -1
```

```yaml
# field.field.node.page.field_tags.yml and field.field.node.article.field_tags.yml
field_name: field_tags
entity_type: node
bundle: page
label: Tags
settings:
  handler: default:taxonomy_term
  handler_settings:
    target_bundles:
      tags: tags
```

```yaml
# field.storage.media.field_tags.yml
id: media.field_tags
field_name: field_tags
entity_type: media
type: entity_reference
settings:
  target_type: taxonomy_term
cardinality: -1
```

```yaml
# field.field.media.image.field_tags.yml
field_name: field_tags
entity_type: media
bundle: image
label: Tags
settings:
  handler: default:taxonomy_term
  handler_settings:
    target_bundles:
      tags: tags
```

- [ ] **Step 6: Verify skeleton syntax**

Run:

```bash
ddev code-review web/modules/custom/term_reference
```

Expected: The command reports missing PHP classes referenced by services/routes. YAML syntax must not fail.

- [ ] **Step 7: Commit skeleton**

```bash
git add web/modules/custom/term_reference
git commit -m "feat: scaffold Term Reference module" -m "AI-assisted by Codex"
```

## Task 2: Discovery Service

**Files:**
- Create: `web/modules/custom/term_reference/src/TermReferenceDiscoveryInterface.php`
- Create: `web/modules/custom/term_reference/src/TermReferenceDiscovery.php`
- Test: `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`

- [ ] **Step 1: Write a failing discovery functional test**

Create `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php` with the first test method:

```php
<?php

namespace Drupal\Tests\term_reference\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests taxonomy term reference management.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceManageFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'term_reference_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests discovering reference groups for the Tags vocabulary.
   */
  public function testReferenceDiscoveryAndTasks(): void {
    $term = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->create([
        'vid' => 'tags',
        'name' => 'Blue',
      ]);
    $term->save();

    $groups = $this->container->get('Drupal\term_reference\TermReferenceDiscoveryInterface')
      ->getReferenceGroupsForVocabulary('tags');

    // Check that content and media field groups are discovered.
    $this->assertArrayHasKey('node.field_tags', $groups);
    $this->assertArrayHasKey('media.field_tags', $groups);
    $this->assertSame(['article', 'page'], array_keys($groups['node.field_tags']['bundles']));
    $this->assertSame('Tags', $groups['node.field_tags']['field_label']);
    $this->assertSame('Content', $groups['node.field_tags']['entity_type_label_plural']);
  }

}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL because `Drupal\term_reference\TermReferenceDiscoveryInterface` does not exist.

- [ ] **Step 3: Add the discovery interface**

Create `web/modules/custom/term_reference/src/TermReferenceDiscoveryInterface.php`:

```php
<?php

namespace Drupal\term_reference;

/**
 * Discovers entity reference fields that can reference taxonomy terms.
 */
interface TermReferenceDiscoveryInterface {

  /**
   * Gets reference groups for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   *
   * @return array
   *   Reference groups keyed by entity type ID and field name.
   */
  public function getReferenceGroupsForVocabulary(string $vocabulary_id): array;

  /**
   * Gets one reference group for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array|null
   *   The reference group, or NULL when none exists.
   */
  public function getReferenceGroup(string $vocabulary_id, string $entity_type_id, string $field_name): ?array;

}
```

- [ ] **Step 4: Add the discovery implementation**

Create `web/modules/custom/term_reference/src/TermReferenceDiscovery.php`:

```php
<?php

namespace Drupal\term_reference;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Discovers entity reference fields that can reference taxonomy vocabularies.
 */
class TermReferenceDiscovery implements TermReferenceDiscoveryInterface {

  /**
   * Constructs a TermReferenceDiscovery object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getReferenceGroupsForVocabulary(string $vocabulary_id): array {
    $groups = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !$entity_type->entityClassImplements('\Drupal\Core\Entity\FieldableEntityInterface')) {
        continue;
      }
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $bundle) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
          if (!$field_definition instanceof FieldConfigInterface || !$this->fieldTargetsVocabulary($field_definition, $vocabulary_id)) {
            continue;
          }
          $group_id = $entity_type_id . '.' . $field_name;
          $groups[$group_id] ??= [
            'id' => $group_id,
            'entity_type_id' => $entity_type_id,
            'entity_type_label_plural' => (string) $entity_type->getPluralLabel(),
            'field_name' => $field_name,
            'field_label' => (string) $field_definition->label(),
            'vocabulary_id' => $vocabulary_id,
            'bundles' => [],
          ];
          $groups[$group_id]['bundles'][$bundle_id] = [
            'id' => $bundle_id,
            'label' => (string) ($bundle['label'] ?? $bundle_id),
          ];
        }
      }
    }
    ksort($groups);
    foreach ($groups as &$group) {
      ksort($group['bundles']);
    }
    return $groups;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceGroup(string $vocabulary_id, string $entity_type_id, string $field_name): ?array {
    $groups = $this->getReferenceGroupsForVocabulary($vocabulary_id);
    return $groups[$entity_type_id . '.' . $field_name] ?? NULL;
  }

  /**
   * Checks whether a field targets a vocabulary.
   *
   * @param \Drupal\field\FieldConfigInterface $field_definition
   *   The field definition.
   * @param string $vocabulary_id
   *   The vocabulary ID.
   *
   * @return bool
   *   TRUE when the field targets the vocabulary.
   */
  protected function fieldTargetsVocabulary(FieldConfigInterface $field_definition, string $vocabulary_id): bool {
    if ($field_definition->getType() !== 'entity_reference') {
      return FALSE;
    }
    if ($field_definition->getSetting('target_type') !== 'taxonomy_term') {
      return FALSE;
    }
    $handler_settings = $field_definition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    return empty($target_bundles) || in_array($vocabulary_id, $target_bundles);
  }

}
```

- [ ] **Step 5: Run the test to verify discovery passes**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: PASS for the discovery assertions.

- [ ] **Step 6: Commit discovery**

```bash
git add web/modules/custom/term_reference
git commit -m "feat: discover term reference field groups" -m "AI-assisted by Codex"
```

## Task 3: Dynamic Routes And Local Task Deriver

**Files:**
- Create: `web/modules/custom/term_reference/src/Routing/TermReferenceRoutes.php`
- Create: `web/modules/custom/term_reference/src/Plugin/Derivative/TermReferenceLocalTasks.php`
- Create: `web/modules/custom/term_reference/src/Controller/TermReferenceOverviewController.php`
- Modify: `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`

- [ ] **Step 1: Extend the failing functional test for tasks**

Append to `testReferenceDiscoveryAndTasks()` after discovery assertions:

```php
$account = $this->drupalCreateUser([
  'access content',
  'administer term references',
]);
$this->drupalLogin($account);
$this->drupalGet($term->toUrl());

// Check that the References task and generated secondary tasks are visible.
$this->assertSession()->linkExists('References');
$this->clickLink('References');
$this->assertSession()->linkExists('Tags (Content)');
$this->assertSession()->linkExists('Tags (Media)');
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL because dynamic routes and local task derivatives do not exist.

- [ ] **Step 3: Add dynamic route generation**

Before creating the route callback, add this method to `TermReferenceDiscoveryInterface`:

```php
/**
 * Gets every unique reference group across vocabularies.
 *
 * @return array
 *   Reference groups keyed by entity type ID and field name.
 */
public function getAllReferenceGroups(): array;
```

Add this implementation to `TermReferenceDiscovery`:

```php
/**
 * {@inheritdoc}
 */
public function getAllReferenceGroups(): array {
  $groups = [];
  foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
    if (!$entity_type instanceof ContentEntityTypeInterface || !$entity_type->entityClassImplements('\Drupal\Core\Entity\FieldableEntityInterface')) {
      continue;
    }
    foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $bundle) {
      foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
        if (!$field_definition instanceof FieldConfigInterface || $field_definition->getType() !== 'entity_reference' || $field_definition->getSetting('target_type') !== 'taxonomy_term') {
          continue;
        }
        $group_id = $entity_type_id . '.' . $field_name;
        $groups[$group_id] ??= [
          'id' => $group_id,
          'entity_type_id' => $entity_type_id,
          'entity_type_label_plural' => (string) $entity_type->getPluralLabel(),
          'field_name' => $field_name,
          'field_label' => (string) $field_definition->label(),
          'bundles' => [],
        ];
        $groups[$group_id]['bundles'][$bundle_id] = [
          'id' => $bundle_id,
          'label' => (string) ($bundle['label'] ?? $bundle_id),
        ];
      }
    }
  }
  ksort($groups);
  foreach ($groups as &$group) {
    ksort($group['bundles']);
  }
  return $groups;
}
```

Create `web/modules/custom/term_reference/src/Routing/TermReferenceRoutes.php`:

```php
<?php

namespace Drupal\term_reference\Routing;

use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Builds dynamic routes for term reference management forms.
 */
class TermReferenceRoutes {

  /**
   * Constructs a TermReferenceRoutes object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Returns routes for every discovered entity type and field pair.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  public function routes(): RouteCollection {
    $collection = new RouteCollection();
    $overview_route = new Route('/taxonomy/term/{taxonomy_term}/references');
    $overview_route->setDefaults([
      '_controller' => '\Drupal\term_reference\Controller\TermReferenceOverviewController::overview',
      '_title' => 'References',
    ]);
    $overview_route->setRequirement('_permission', 'administer term references');
    $overview_route->setOption('_admin_route', TRUE);
    $overview_route->setOption('parameters', [
      'taxonomy_term' => [
        'type' => 'entity:taxonomy_term',
      ],
    ]);
    $collection->add('term_reference.references', $overview_route);

    foreach ($this->termReferenceDiscovery->getAllReferenceGroups() as $group) {
      $route = new Route('/taxonomy/term/{taxonomy_term}/references/' . $group['entity_type_id'] . '/' . $group['field_name']);
      $route->setDefaults([
        '_form' => '\Drupal\term_reference\Form\TermReferenceManageForm',
        '_title' => 'References',
        'entity_type_id' => $group['entity_type_id'],
        'field_name' => $group['field_name'],
      ]);
      $route->setRequirement('_custom_access', '\Drupal\term_reference\Access\TermReferenceAccessCheck::access');
      $route->setOption('_admin_route', TRUE);
      $route->setOption('parameters', [
        'taxonomy_term' => [
          'type' => 'entity:taxonomy_term',
        ],
      ]);
      $collection->add('term_reference.references.' . $group['entity_type_id'] . '.' . $group['field_name'], $route);
    }
    return $collection;
  }

}
```

Create `web/modules/custom/term_reference/src/Controller/TermReferenceOverviewController.php`:

```php
<?php

namespace Drupal\term_reference\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles the primary References taxonomy term task.
 */
class TermReferenceOverviewController extends ControllerBase {

  /**
   * Constructs a TermReferenceOverviewController object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Redirects the primary task to the first available reference group.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect response or an empty-state render array.
   */
  public function overview(TermInterface $taxonomy_term): RedirectResponse|array {
    $groups = $this->termReferenceDiscovery->getReferenceGroupsForVocabulary($taxonomy_term->bundle());
    if (!$groups) {
      return [
        '#markup' => $this->t('No reference fields are available for this term.'),
      ];
    }
    $group = reset($groups);
    $url = Url::fromRoute('term_reference.references.' . $group['entity_type_id'] . '.' . $group['field_name'], [
      'taxonomy_term' => $taxonomy_term->id(),
    ]);
    return new RedirectResponse($url->toString());
  }

}
```

- [ ] **Step 4: Add the local task deriver**

Create `web/modules/custom/term_reference/src/Plugin/Derivative/TermReferenceLocalTasks.php`:

```php
<?php

namespace Drupal\term_reference\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives local tasks for term reference field groups.
 */
class TermReferenceLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a TermReferenceLocalTasks object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('Drupal\term_reference\TermReferenceDiscoveryInterface')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->termReferenceDiscovery->getAllReferenceGroups() as $group_id => $group) {
      $this->derivatives[$group_id] = [
        'route_name' => 'term_reference.references.' . $group['entity_type_id'] . '.' . $group['field_name'],
        'title' => $group['field_label'] . ' (' . $group['entity_type_label_plural'] . ')',
        'parent_id' => 'term_reference.references',
        'weight' => 0,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
```

- [ ] **Step 5: Run the test**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL because access and form classes are missing. The router rebuild must no longer fail on missing route callbacks or local task derivers.

- [ ] **Step 6: Commit routes and tasks**

```bash
git add web/modules/custom/term_reference
git commit -m "feat: generate term reference routes and tasks" -m "AI-assisted by Codex"
```

## Task 4: Access Checker And Empty Management Form

**Files:**
- Create: `web/modules/custom/term_reference/src/Access/TermReferenceAccessCheck.php`
- Create: `web/modules/custom/term_reference/src/Form/TermReferenceManageForm.php`
- Modify: `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`

- [ ] **Step 1: Add route access and page assertions**

Add assertions after clicking `References`:

```php
$this->clickLink('Tags (Content)');
$this->assertSession()->statusCodeEquals(200);
$this->assertSession()->pageTextContains('Add Content references to Blue');
$this->assertSession()->pageTextContains('Entity type');
$this->assertSession()->pageTextContains('Content');
$this->assertSession()->pageTextContains('Field');
$this->assertSession()->pageTextContains('Tags (field_tags)');
$this->assertSession()->pageTextContains('Eligible bundles');
$this->assertSession()->pageTextContains('Article');
$this->assertSession()->pageTextContains('Basic page');
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL because the access checker and form do not exist.

- [ ] **Step 3: Add the access checker**

Create `web/modules/custom/term_reference/src/Access/TermReferenceAccessCheck.php`:

```php
<?php

namespace Drupal\term_reference\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;

/**
 * Checks access to term reference management routes.
 */
class TermReferenceAccessCheck {

  /**
   * Constructs a TermReferenceAccessCheck object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Checks access to a term reference route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, TermInterface $taxonomy_term, string $entity_type_id, string $field_name): AccessResult {
    $group = $this->termReferenceDiscovery->getReferenceGroup($taxonomy_term->bundle(), $entity_type_id, $field_name);
    if (!$group) {
      return AccessResult::forbidden()->addCacheableDependency($taxonomy_term);
    }
    return AccessResult::allowedIfHasPermission($account, 'administer term references')
      ->addCacheableDependency($taxonomy_term);
  }

}
```

- [ ] **Step 4: Add the empty management form**

Create `web/modules/custom/term_reference/src/Form/TermReferenceManageForm.php`:

```php
<?php

namespace Drupal\term_reference\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;

/**
 * Provides a form for managing entities that reference a taxonomy term.
 */
class TermReferenceManageForm extends FormBase {

  /**
   * Constructs a TermReferenceManageForm object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'term_reference_manage_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?TermInterface $taxonomy_term = NULL, string $entity_type_id = '', string $field_name = ''): array {
    $group = $this->termReferenceDiscovery->getReferenceGroup($taxonomy_term->bundle(), $entity_type_id, $field_name);
    $bundle_labels = array_column($group['bundles'], 'label');

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Reference field summary'),
      '#open' => TRUE,
    ];
    $form['summary']['items'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Entity type: @type', ['@type' => $group['entity_type_label_plural']]),
        $this->t('Field: @label (@name)', ['@label' => $group['field_label'], '@name' => $group['field_name']]),
        $this->t('Target vocabulary: @vocabulary', ['@vocabulary' => $taxonomy_term->bundle()]),
        $this->t('Eligible bundles: @bundles', ['@bundles' => implode(', ', $bundle_labels)]),
      ],
    ];
    $form['add'] = [
      '#type' => 'details',
      '#title' => $this->t('Add @entity_type references to @term', [
        '@entity_type' => $group['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
      '#open' => TRUE,
    ];
    $form['references'] = [
      '#type' => 'table',
      '#caption' => $this->t('Existing @entity_type references to @term', [
        '@entity_type' => $group['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
      '#header' => [
        '',
        $this->t('Label'),
        $this->t('ID'),
        $this->t('Published'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No references are available.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
```

- [ ] **Step 5: Run the test**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: PASS for route visibility and empty form summary assertions.

- [ ] **Step 6: Commit access and form shell**

```bash
git add web/modules/custom/term_reference
git commit -m "feat: add term reference access and form shell" -m "AI-assisted by Codex"
```

## Task 5: Reference Manager, Autocomplete, Add, And Remove

**Files:**
- Create: `web/modules/custom/term_reference/src/TermReferenceManagerInterface.php`
- Create: `web/modules/custom/term_reference/src/TermReferenceManager.php`
- Create: `web/modules/custom/term_reference/src/Controller/TermReferenceAutocompleteController.php`
- Modify: `web/modules/custom/term_reference/src/Form/TermReferenceManageForm.php`
- Modify: `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`

- [ ] **Step 1: Add functional coverage for add/remove**

Extend `testReferenceDiscoveryAndTasks()` to create Page and Article nodes, add one through the form, verify the table, remove it, then add the other. Use `drupalPostForm()` with the autocomplete value format `Label (entity_id)`.

```php
$node_storage = $this->container->get('entity_type.manager')->getStorage('node');
$page = $node_storage->create([
  'type' => 'page',
  'title' => 'Page reference',
  'status' => 1,
]);
$page->save();
$article = $node_storage->create([
  'type' => 'article',
  'title' => 'Article reference',
  'status' => 1,
]);
$article->save();

$this->drupalGet('/taxonomy/term/' . $term->id() . '/references/node/field_tags');
$this->submitForm([
  'entity' => 'Page reference (' . $page->id() . ')',
], 'Add');

// Check that the page now references the term and appears in the table.
$node_storage->resetCache([$page->id()]);
$page = $node_storage->load($page->id());
$this->assertSame((string) $term->id(), $page->get('field_tags')->target_id);
$this->assertSession()->pageTextContains('Page reference');
$this->assertSession()->pageTextContains((string) $page->id());
$this->assertSession()->pageTextContains('Published');
$this->assertSession()->linkExists('View');
$this->assertSession()->linkExists('Edit');

$this->submitForm([
  'references[' . $page->id() . '][remove]' => TRUE,
], 'Remove');

// Check that removing clears only the selected term.
$node_storage->resetCache([$page->id()]);
$page = $node_storage->load($page->id());
$this->assertTrue($page->get('field_tags')->isEmpty());

$this->submitForm([
  'entity' => 'Article reference (' . $article->id() . ')',
], 'Add');

// Check that another eligible bundle can be added from the same Content task.
$node_storage->resetCache([$article->id()]);
$article = $node_storage->load($article->id());
$this->assertSame((string) $term->id(), $article->get('field_tags')->target_id);
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL because autocomplete, add, remove, and table population are not implemented.

- [ ] **Step 3: Add the manager interface**

Create `web/modules/custom/term_reference/src/TermReferenceManagerInterface.php`:

```php
<?php

namespace Drupal\term_reference;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Manages taxonomy term references on content entities.
 */
interface TermReferenceManagerInterface {

  /**
   * Loads entities that reference a term through a field.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param array $group
   *   The reference group.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The referencing entities.
   */
  public function loadReferencingEntities(TermInterface $term, array $group): array;

  /**
   * Adds a term reference to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param string $field_name
   *   The field name.
   */
  public function addReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void;

  /**
   * Removes a term reference from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param string $field_name
   *   The field name.
   */
  public function removeReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void;

}
```

- [ ] **Step 4: Add the manager implementation**

Create `web/modules/custom/term_reference/src/TermReferenceManager.php`:

```php
<?php

namespace Drupal\term_reference;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Manages taxonomy term references on content entities.
 */
class TermReferenceManager implements TermReferenceManagerInterface {

  /**
   * Constructs a TermReferenceManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function loadReferencingEntities(TermInterface $term, array $group): array {
    $storage = $this->entityTypeManager->getStorage($group['entity_type_id']);
    $entity_type = $this->entityTypeManager->getDefinition($group['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition($group['field_name'] . '.target_id', $term->id());
    if ($bundle_key) {
      $query->condition($bundle_key, array_keys($group['bundles']), 'IN');
    }
    $label_key = $entity_type->getKey('label');
    if ($label_key) {
      $query->sort($label_key);
    }
    $entity_ids = $query->execute();
    return $entity_ids ? $storage->loadMultiple($entity_ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function addReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void {
    foreach ($entity->get($field_name) as $item) {
      if ((string) $item->target_id === (string) $term->id()) {
        return;
      }
    }
    $entity->get($field_name)->appendItem($term->id());
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function removeReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void {
    foreach ($entity->get($field_name) as $delta => $item) {
      if ((string) $item->target_id === (string) $term->id()) {
        $entity->get($field_name)->removeItem($delta);
      }
    }
    $entity->save();
  }

}
```

- [ ] **Step 5: Add autocomplete controller**

Create `web/modules/custom/term_reference/src/Controller/TermReferenceAutocompleteController.php`:

```php
<?php

namespace Drupal\term_reference\Controller;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides autocomplete suggestions for term reference management forms.
 */
class TermReferenceAutocompleteController extends ControllerBase {

  /**
   * Constructs a TermReferenceAutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Returns matching manageable entities.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON autocomplete response.
   */
  public function autocomplete(Request $request, TermInterface $taxonomy_term, string $entity_type_id, string $field_name): JsonResponse {
    $matches = [];
    $input = Tags::explode($request->query->get('q', ''));
    $search = trim((string) end($input));
    $group = $this->termReferenceDiscovery->getReferenceGroup($taxonomy_term->bundle(), $entity_type_id, $field_name);
    if (!$group || $search === '') {
      return new JsonResponse($matches);
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $label_key = $entity_type->getKey('label');
    $bundle_key = $entity_type->getKey('bundle');
    $query = $storage->getQuery()->accessCheck(TRUE)->range(0, 10);
    if ($label_key) {
      $query->condition($label_key, $search, 'CONTAINS');
      $query->sort($label_key);
    }
    if ($bundle_key) {
      $query->condition($bundle_key, array_keys($group['bundles']), 'IN');
    }

    foreach ($storage->loadMultiple($query->execute()) as $entity) {
      if (!$entity->access('update', $this->currentUser) || !$entity->hasField($field_name) || !$entity->get($field_name)->access('edit', $this->currentUser)) {
        continue;
      }
      $matches[] = [
        'value' => $entity->label() . ' (' . $entity->id() . ')',
        'label' => $entity->label() . ' (' . $entity->id() . ')',
      ];
    }

    return new JsonResponse($matches);
  }

}
```

- [ ] **Step 6: Wire manager behavior into the form**

Update `TermReferenceManageForm` constructor to inject `TermReferenceManagerInterface` and `EntityTypeManagerInterface`:

```php
/**
 * Constructs a TermReferenceManageForm object.
 *
 * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
 *   The term reference discovery service.
 * @param \Drupal\term_reference\TermReferenceManagerInterface $termReferenceManager
 *   The term reference manager.
 * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
 *   The entity type manager.
 */
public function __construct(
  protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  protected TermReferenceManagerInterface $termReferenceManager,
  protected EntityTypeManagerInterface $entityTypeManager,
) {}
```

Add these protected properties to the form class:

```php
/**
 * The current taxonomy term.
 *
 * @var \Drupal\taxonomy\TermInterface|null
 */
protected ?TermInterface $term = NULL;

/**
 * The current reference group.
 *
 * @var array
 */
protected array $referenceGroup = [];
```

In `buildForm()`, store the current term and group, then add the autocomplete element inside `$form['add']`:

```php
$this->term = $taxonomy_term;
$this->referenceGroup = $group;
$form['add']['entity'] = [
  '#type' => 'textfield',
  '#title' => $this->t('@entity_type entity', ['@entity_type' => $group['entity_type_label_plural']]),
  '#description' => $this->t('Enter the label of an existing entity that should reference this term.'),
  '#autocomplete_route_name' => 'term_reference.autocomplete',
  '#autocomplete_route_parameters' => [
    'taxonomy_term' => $taxonomy_term->id(),
    'entity_type_id' => $entity_type_id,
    'field_name' => $field_name,
  ],
  '#required' => TRUE,
];
$form['add']['submit'] = [
  '#type' => 'submit',
  '#value' => $this->t('Add'),
  '#submit' => ['::addReferenceSubmit'],
];
```

Replace the empty table with rows loaded from the manager:

```php
$entities = $this->termReferenceManager->loadReferencingEntities($taxonomy_term, $group);
$form['references']['#tree'] = TRUE;
foreach ($entities as $entity) {
  $operations = [];
  if ($entity->access('view')) {
    $operations['view'] = [
      'title' => $this->t('View'),
      'url' => $entity->toUrl(),
    ];
  }
  if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => $entity->toUrl('edit-form'),
    ];
  }
  $form['references'][$entity->id()] = [
    'remove' => [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove @label', ['@label' => $entity->label()]),
      '#title_display' => 'invisible',
    ],
    'label' => [
      '#plain_text' => $entity->label(),
    ],
    'id' => [
      '#plain_text' => $entity->id(),
    ],
    'published' => [
      '#plain_text' => ($entity->hasField('status') && (bool) $entity->get('status')->value) ? $this->t('Published') : $this->t('Unpublished'),
    ],
    'operations' => [
      'data' => [
        '#type' => 'operations',
        '#links' => $operations,
      ],
    ],
  ];
}
if ($entities) {
  $form['remove'] = [
    '#type' => 'submit',
    '#value' => $this->t('Remove'),
    '#submit' => ['::removeReferenceSubmit'],
  ];
}
```

Add validation and submit handlers:

```php
/**
 * Validates the entity selected for adding.
 *
 * @param array $form
 *   The form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
public function validateForm(array &$form, FormStateInterface $form_state): void {
  if ((string) $form_state->getValue('op') !== (string) $this->t('Add')) {
    return;
  }
  if (!preg_match('/\(([^)]+)\)$/', trim((string) $form_state->getValue('entity')), $matches)) {
    $form_state->setErrorByName('entity', $this->t('Select an entity from the autocomplete suggestions.'));
    return;
  }
  $entity = $this->entityTypeManager->getStorage($this->referenceGroup['entity_type_id'])->load($matches[1]);
  if (!$entity || !$entity->hasField($this->referenceGroup['field_name']) || !$entity->access('update') || !$entity->get($this->referenceGroup['field_name'])->access('edit')) {
    $form_state->setErrorByName('entity', $this->t('The selected entity cannot be managed.'));
    return;
  }
  $form_state->set('term_reference_entity', $entity);
}

/**
 * Adds the current term to the selected entity.
 *
 * @param array $form
 *   The form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
public function addReferenceSubmit(array &$form, FormStateInterface $form_state): void {
  $entity = $form_state->get('term_reference_entity');
  $this->termReferenceManager->addReference($entity, $this->term, $this->referenceGroup['field_name']);
  $this->messenger()->addStatus($this->t('@label now references @term.', [
    '@label' => $entity->label(),
    '@term' => $this->term->label(),
  ]));
}

/**
 * Removes the current term from selected entities.
 *
 * @param array $form
 *   The form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
public function removeReferenceSubmit(array &$form, FormStateInterface $form_state): void {
  $selected_ids = [];
  foreach ($form_state->getValue('references', []) as $entity_id => $row) {
    if (!empty($row['remove'])) {
      $selected_ids[] = $entity_id;
    }
  }
  foreach ($this->entityTypeManager->getStorage($this->referenceGroup['entity_type_id'])->loadMultiple($selected_ids) as $entity) {
    if ($entity->access('update') && $entity->hasField($this->referenceGroup['field_name']) && $entity->get($this->referenceGroup['field_name'])->access('edit')) {
      $this->termReferenceManager->removeReference($entity, $this->term, $this->referenceGroup['field_name']);
    }
  }
}
```

- [ ] **Step 7: Run the test**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: PASS for discovery, tasks, summary, add, list, operations, and remove.

- [ ] **Step 8: Commit manager and form behavior**

```bash
git add web/modules/custom/term_reference
git commit -m "feat: manage taxonomy term references" -m "AI-assisted by Codex"
```

## Task 6: Access Hardening And Media Coverage

**Files:**
- Modify: `web/modules/custom/term_reference/src/Access/TermReferenceAccessCheck.php`
- Modify: `web/modules/custom/term_reference/src/Controller/TermReferenceAutocompleteController.php`
- Modify: `web/modules/custom/term_reference/src/Form/TermReferenceManageForm.php`
- Modify: `web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php`

- [ ] **Step 1: Add test coverage for denied users and media**

Add a second assertion block inside the same test method:

```php
$media_storage = $this->container->get('entity_type.manager')->getStorage('media');
$media = $media_storage->create([
  'bundle' => 'image',
  'name' => 'Image reference',
  'status' => 1,
]);
$media->save();

$this->drupalGet('/taxonomy/term/' . $term->id() . '/references/media/field_tags');
$this->submitForm([
  'entity' => 'Image reference (' . $media->id() . ')',
], 'Add');

// Check that media references are managed separately from content references.
$media_storage->resetCache([$media->id()]);
$media = $media_storage->load($media->id());
$this->assertSame((string) $term->id(), $media->get('field_tags')->target_id);

$limited_account = $this->drupalCreateUser([
  'access content',
]);
$this->drupalLogin($limited_account);
$this->drupalGet('/taxonomy/term/' . $term->id() . '/references/node/field_tags');

// Check that users without management access cannot use the route.
$this->assertSession()->statusCodeEquals(403);
```

- [ ] **Step 2: Run the test to verify current behavior**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: FAIL until media permissions and access behavior are complete.

- [ ] **Step 3: Harden access and autocomplete**

Update the route access checker to allow `administer term references` and to deny invalid groups. Keep form-level entity and field access checks for each mutation. In autocomplete, only return entities where:

```php
$entity->access('update', $account)
  && $entity->hasField($field_name)
  && $entity->get($field_name)->access('edit', $account);
```

- [ ] **Step 4: Run the test**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: PASS for media and denied-user coverage.

- [ ] **Step 5: Commit access hardening**

```bash
git add web/modules/custom/term_reference
git commit -m "test: cover term reference access and media" -m "AI-assisted by Codex"
```

## Task 7: Documentation, Linting, And Final Verification

**Files:**
- Create: `web/modules/custom/term_reference/README.md`
- Modify: files found by linting.

- [ ] **Step 1: Add README**

Create `web/modules/custom/term_reference/README.md`:

```markdown
# Term Reference

Term Reference adds a `References` tab to taxonomy term pages. The tab lets
authorized editors add or remove the current term from entity reference fields
that target the term's vocabulary.

Secondary tasks are generated per `{entity_type_id}.{field_name}` pair. For
example, a `field_tags` field on nodes and media appears as `Tags (Content)` and
`Tags (Media)`.

The module uses Drupal entity and field access checks before mutating
references. It does not create bundle-specific tabs; each task manages all
eligible bundles for the selected entity type and field name.
```

- [ ] **Step 2: Run automated tests**

Run:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceManageFormTest.php
```

Expected: PASS.

- [ ] **Step 3: Run lint and static review**

Run:

```bash
ddev code-review web/modules/custom/term_reference
```

Expected: PASS.

- [ ] **Step 4: Fix automated review findings**

Run:

```bash
ddev code-fix web/modules/custom/term_reference
ddev code-review web/modules/custom/term_reference
```

Expected: PASS after fixes.

- [ ] **Step 5: Commit documentation and verification fixes**

```bash
git add web/modules/custom/term_reference
git commit -m "docs: document Term Reference module" -m "AI-assisted by Codex"
```

## Self-Review

- Spec coverage: The plan covers module metadata, discovery, route generation, local task derivation, generic entity management, summary details, table columns, access checks, test fixtures, media and node coverage, and documentation.
- Placeholder scan: The plan avoids placeholder markers and names concrete files, classes, commands, and expected outcomes.
- Type consistency: The service names, class names, route parameters, and group array keys are consistent across tasks.
