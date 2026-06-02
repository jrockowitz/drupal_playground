# AI Schema.org JSON-LD Recipe Config Action Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add reusable recipe config action support to `ai_schemadotorg_jsonld`, refactor the builder so recipes and Drush share the same API, and replace the imperative Drupal Playground install steps with a recipe.

**Architecture:** Keep runtime field setup in `AiSchemaDotOrgJsonLdBuilder` and expose a new public `addFieldToBundles()` orchestration method. Add a custom `addField` recipe config action that delegates to the builder, thin out the Drush command to use the same path, and add a reusable recipe plus tests that verify explicit bundles, `['*']`, idempotency, and direct per-bundle safety.

**Tech Stack:** Drupal 11 recipes, config action plugins, Drush commands, PHP 8.3, kernel tests, DDEV, PHPUnit.

---

## File Structure

**Create:**
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/Plugin/ConfigAction/AddField.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`
- `recipes/drupal_playground_ai_schemadotorg_jsonld/recipe.yml`
- `recipes/drupal_playground_ai_schemadotorg_jsonld/composer.json`

**Modify:**
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManagerInterface.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManager.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/src/Drush/Commands/AiSchemaDotOrgJsonLdCommands.php`
- `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`
- `.ddev/commands/web/install`
- `recipes/README.txt`
- `docs/superpowers/specs/2026-04-21-ai-schemadotorg-jsonld-recipe-config-action-design.md`

**Verify With:**
- `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`
- `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`
- `ddev code-review web/modules/sandbox/ai_schemadotorg_jsonld`
- `ddev install ai`

---

### Task 1: Refactor the builder and manager APIs

**Files:**
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManagerInterface.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManager.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

- [ ] **Step 1: Write the failing builder tests for the new public API**

Add kernel coverage for:

```php
public function testAddFieldToBundleInitializesMissingEntityTypeSettings(): void {
  /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
  $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

  $config = $this->configFactory->getEditable('ai_schemadotorg_jsonld.settings');
  $entity_types = $config->get('entity_types') ?? [];
  unset($entity_types['node']);
  $config->set('entity_types', $entity_types)->save();

  $builder->addFieldToBundle('node', 'page');

  $entity_type_settings = $this->configFactory
    ->get('ai_schemadotorg_jsonld.settings')
    ->get('entity_types.node');
  $this->assertNotNull($entity_type_settings);
}

public function testAddFieldToBundlesWithWildcard(): void {
  /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
  $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

  $builder->addFieldToBundles('node', ['*']);

  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
}

public function testAddFieldToBundlesRejectsMixedWildcardAndExplicitBundle(): void {
  /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
  $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

  $this->expectException(\InvalidArgumentException::class);
  $this->expectExceptionMessage('The bundles list for node cannot mix "*" with explicit bundle names.');

  $builder->addFieldToBundles('node', ['*', 'page']);
}
```

- [ ] **Step 2: Run the builder kernel test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

Expected: FAIL with undefined method errors for `addFieldToBundles()` and `addFieldToBundle()`, or assertion failures caused by missing wildcard support.

- [ ] **Step 3: Update the builder interface to expose the new API**

Change the interface to:

```php
interface AiSchemaDotOrgJsonLdBuilderInterface {

  const FIELD_NAME = 'field_schemadotorg_jsonld';

  public function addFieldToBundles(string $entity_type_id, array $bundles): void;

  public function addFieldToBundle(string $entity_type_id, string $bundle): void;

}
```

Update the interface docblocks so:
- `addFieldToBundles()` documents `['*']` support and exception conditions
- `addFieldToBundle()` documents that it initializes entity type settings when missing

- [ ] **Step 4: Add a manager method for one-at-a-time entity type initialization**

Update the manager interface with:

```php
public function addEntityType(string $entity_type_id): void;
```

Implement it in `AiSchemaDotOrgJsonLdManager` by delegating to the existing `addEntityTypes()` logic:

```php
public function addEntityType(string $entity_type_id): void {
  $this->addEntityTypes([$entity_type_id]);
}
```

Do not duplicate prompt-building logic. Keep `addEntityType()` as a thin wrapper.

- [ ] **Step 5: Implement `addFieldToBundles()` and rename the per-bundle method**

In `AiSchemaDotOrgJsonLdBuilder`:

1. Rename `addFieldToEntity()` to `addFieldToBundle()`.
2. Add `addFieldToBundles()` with this shape:

```php
public function addFieldToBundles(string $entity_type_id, array $bundles): void {
  $entity_type_definition = $this->getSupportedEntityTypeDefinition($entity_type_id);
  $resolved_bundles = $this->resolveBundles($entity_type_definition, $bundles);

  foreach ($resolved_bundles as $bundle) {
    $this->addFieldToBundle($entity_type_id, $bundle);
  }
}
```

3. Add focused protected helpers:

```php
protected function getSupportedEntityTypeDefinition(string $entity_type_id): ContentEntityTypeInterface
protected function resolveBundles(ContentEntityTypeInterface $entity_type_definition, array $bundles): array
protected function ensureEntityTypeSettings(string $entity_type_id): void
```

Use the following bundle resolution rules:

```php
if ($bundles === []) {
  throw new \InvalidArgumentException('The bundles list for ' . $entity_type_id . ' cannot be empty.');
}

$bundles = array_values(array_unique($bundles));

if (in_array('*', $bundles) && count($bundles) > 1) {
  throw new \InvalidArgumentException('The bundles list for ' . $entity_type_id . ' cannot mix "*" with explicit bundle names.');
}
```

For non-bundle entity types:

```php
if (!$bundle_entity_type_id) {
  if ($bundles === ['*']) {
    return [$entity_type_id];
  }
  if ($bundles === [$entity_type_id]) {
    return [$entity_type_id];
  }
  throw new \InvalidArgumentException('The non-bundle entity type ' . $entity_type_id . ' requires the synthetic bundle ' . $entity_type_id . '.');
}
```

For bundle entity types:

```php
if ($bundles === ['*']) {
  $bundle_entities = $this->entityTypeManager->getStorage($bundle_entity_type_id)->loadMultiple();
  $bundle_ids = array_keys($bundle_entities);
  sort($bundle_ids);
  return $bundle_ids;
}
```

Then validate each explicit bundle exists before returning it.

- [ ] **Step 6: Make `addFieldToBundle()` self-healing**

Start `addFieldToBundle()` with:

```php
public function addFieldToBundle(string $entity_type_id, string $bundle): void {
  $this->getSupportedEntityTypeDefinition($entity_type_id);
  $this->ensureEntityTypeSettings($entity_type_id);
  $this->createFieldStorage($entity_type_id);
  $this->createField($entity_type_id, $bundle);
  $this->createAutomator($entity_type_id, $bundle);
  $this->addFormDisplayComponent($entity_type_id, $bundle);
  $this->addViewDisplayComponent($entity_type_id, $bundle);
}
```

Implement `ensureEntityTypeSettings()` like:

```php
protected function ensureEntityTypeSettings(string $entity_type_id): void {
  $entity_type_settings = $this->configFactory
    ->get('ai_schemadotorg_jsonld.settings')
    ->get('entity_types.' . $entity_type_id);

  if ($entity_type_settings !== NULL) {
    return;
  }

  $this->manager->addEntityType($entity_type_id);
}
```

Inject the manager into the builder constructor in general-to-specific service order.

- [ ] **Step 7: Run the builder kernel test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

Expected: PASS

- [ ] **Step 8: Commit the builder and manager refactor**

Run:

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManagerInterface.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdManager.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
git commit -m "refactor: add bundle-aware JSON-LD field builder AI-assisted by Codex"
```

Expected: commit created with the builder and manager API changes only.

### Task 2: Add the recipe config action plugin

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Plugin/ConfigAction/AddField.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`

- [ ] **Step 1: Write the failing config action tests**

Create a kernel test with coverage for:

```php
public function testAddFieldConfigActionWithExplicitBundles(): void {
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

  $this->configActionManager->applyAction('addField', 'ai_schemadotorg_jsonld.settings', [
    'entity_type' => 'node',
    'bundles' => ['page'],
  ]);

  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
  $this->assertNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
}

public function testAddFieldConfigActionWithWildcardBundles(): void {
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

  $this->configActionManager->applyAction('addField', 'ai_schemadotorg_jsonld.settings', [
    'entity_type' => 'node',
    'bundles' => ['*'],
  ]);

  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
}

public function testAddFieldConfigActionRejectsInvalidPayload(): void {
  $this->expectException(\InvalidArgumentException::class);
  $this->expectExceptionMessage('The addField config action requires a non-empty bundles array.');

  $this->configActionManager->applyAction('addField', 'ai_schemadotorg_jsonld.settings', [
    'entity_type' => 'node',
    'bundles' => [],
  ]);
}
```

- [ ] **Step 2: Run the config action test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`

Expected: FAIL because the `addField` config action plugin does not exist.

- [ ] **Step 3: Implement the config action plugin**

Create `src/Plugin/ConfigAction/AddField.php` with this structure:

```php
<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[ConfigAction(
  id: 'addField',
  admin_label: new TranslatableMarkup('Add Schema.org JSON-LD field'),
  entity_types: ['ai_schemadotorg_jsonld.settings'],
)]
class AddField implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdBuilderInterface $builder,
  ) {}

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(AiSchemaDotOrgJsonLdBuilderInterface::class),
    );
  }

  public function apply(string $configName, mixed $value): void {
    if (!is_array($value) || !isset($value['entity_type']) || !is_string($value['entity_type'])) {
      throw new \InvalidArgumentException('The addField config action requires an entity_type string.');
    }
    if (!isset($value['bundles']) || !is_array($value['bundles']) || $value['bundles'] === []) {
      throw new \InvalidArgumentException('The addField config action requires a non-empty bundles array.');
    }

    $this->builder->addFieldToBundles($value['entity_type'], $value['bundles']);
  }

}
```

- [ ] **Step 4: Run the config action test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php`

Expected: PASS

- [ ] **Step 5: Commit the config action plugin**

Run:

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/Plugin/ConfigAction/AddField.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php
git commit -m "feat: add JSON-LD recipe config action AI-assisted by Codex"
```

Expected: commit created with the config action plugin and its tests.

### Task 3: Thin out the Drush command and add wildcard CLI support

**Files:**
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Drush/Commands/AiSchemaDotOrgJsonLdCommands.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

- [ ] **Step 1: Add a failing regression test for wildcard and direct builder parity**

Extend the builder kernel test with:

```php
public function testAddFieldToBundlesAcceptsWildcardForAllCurrentBundles(): void {
  /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
  $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

  $builder->addFieldToBundles('node', ['*']);

  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
  $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
}
```

This test protects the behavior the Drush command will delegate to.

- [ ] **Step 2: Refactor the Drush command to become a thin adapter**

Replace the current validation-heavy flow with:

```php
public function addField(string $entity_type, string $bundle = ''): void {
  try {
    $bundles = ($bundle === '') ? ['*'] : [$bundle];
    $this->builder->addFieldToBundles($entity_type, $bundles);
  }
  catch (\Throwable $throwable) {
    throw new \RuntimeException($throwable->getMessage(), 0, $throwable);
  }

  $bundle_label = ($bundle === '') ? '*' : $bundle;
  $this->logger()->success('Added Schema.org JSON-LD field to ' . $entity_type . '.' . $bundle_label . '.');
}
```

Update the usage attributes to include:

```php
#[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field node *', description: 'Adds the Schema.org JSON-LD field to all current node bundles.')]
```

Delete the command-local helpers that duplicate builder validation if they are no longer used:

```php
protected function getSupportedEntityTypeDefinition(...)
protected function validateBundle(...)
```

- [ ] **Step 3: Run the builder kernel test to verify the delegated behavior still passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

Expected: PASS

- [ ] **Step 4: Commit the Drush simplification**

Run:

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/Drush/Commands/AiSchemaDotOrgJsonLdCommands.php \
  web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
git commit -m "refactor: route JSON-LD Drush setup through builder AI-assisted by Codex"
```

Expected: commit created with the thinner Drush command only.

### Task 4: Add the reusable recipe and replace the imperative install block

**Files:**
- Create: `recipes/drupal_playground_ai_schemadotorg_jsonld/recipe.yml`
- Create: `recipes/drupal_playground_ai_schemadotorg_jsonld/composer.json`
- Modify: `.ddev/commands/web/install`
- Modify: `recipes/README.txt`

- [ ] **Step 1: Create the new recipe files**

Create `recipes/drupal_playground_ai_schemadotorg_jsonld/recipe.yml`:

```yaml
name: 'Drupal Playground AI Schema.org JSON-LD'
description: >
  Installs and configures the AI Schema.org JSON-LD module and attaches the
  field to all current node bundles.
type: 'Site'
recipes:
  - drupal_playground_ai
install:
  - ai_schemadotorg_jsonld
  - json_field_widget
  - ai_schemadotorg_jsonld_breadcrumb
  - ai_schemadotorg_jsonld_log
config:
  actions:
    ai_schemadotorg_jsonld.settings:
      addField:
        entity_type: node
        bundles: ['*']
```

Create `recipes/drupal_playground_ai_schemadotorg_jsonld/composer.json`:

```json
{
  "name": "drupal/drupal_playground_ai_schemadotorg_jsonld",
  "type": "drupal-recipe",
  "require": {}
}
```

- [ ] **Step 2: Replace the imperative install block**

In `.ddev/commands/web/install`, replace:

```bash
    echo "Enabling the AI Schema.org JSON-LD module..."
    drush en -y ai_schemadotorg_jsonld\
      json_field_widget\
      ai_schemadotorg_jsonld_breadcrumb\
      ai_schemadotorg_jsonld_log
    drush ai_schemadotorg_jsonld:add-field node article
    drush ai_schemadotorg_jsonld:add-field node page
```

with:

```bash
    echo "Applying AI Schema.org JSON-LD recipe..."
    drush recipe ../recipes/drupal_playground_ai_schemadotorg_jsonld
```

Keep the surrounding AI recipe flow unchanged.

- [ ] **Step 3: Document the new recipe**

Add a short entry to `recipes/README.txt` describing:
- the recipe name
- that it installs the JSON-LD module and related submodules
- that it attaches the field to all current node bundles using the `addField` config action

- [ ] **Step 4: Run an install smoke test**

Run: `ddev install ai`

Expected:
- install completes successfully
- the recipe applies without a Drush post-step for article and page
- current node bundles have `field_schemadotorg_jsonld`

- [ ] **Step 5: Commit the recipe and install script change**

Run:

```bash
git add recipes/drupal_playground_ai_schemadotorg_jsonld/recipe.yml \
  recipes/drupal_playground_ai_schemadotorg_jsonld/composer.json \
  recipes/README.txt \
  .ddev/commands/web/install
git commit -m "feat: install JSON-LD setup through recipe AI-assisted by Codex"
```

Expected: commit created with the recipe and install command update.

### Task 5: Run final verification and update the design doc if implementation differs

**Files:**
- Modify: `docs/superpowers/specs/2026-04-21-ai-schemadotorg-jsonld-recipe-config-action-design.md`

- [ ] **Step 1: Run PHPUnit for both kernel test classes**

Run:

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/Plugin/ConfigAction/AddFieldTest.php
```

Expected: PASS for both commands.

- [ ] **Step 2: Run code review on the module**

Run: `ddev code-review web/modules/sandbox/ai_schemadotorg_jsonld`

Expected: PASS with no PHPCS, PHPStan, or related review errors.

- [ ] **Step 3: Re-read the design doc against the implementation**

Check that the final code matches the approved design for:
- `addField` config action name
- `bundles: ['*']`
- `addFieldToBundles()`
- `addFieldToBundle()`
- direct per-bundle initialization safety
- `drush ai_schemadotorg_jsonld:add-field node '*'`

If the implementation required a small deviation, update the spec inline so it remains accurate.

- [ ] **Step 4: Commit the final verification or doc sync changes**

Run:

```bash
git add docs/superpowers/specs/2026-04-21-ai-schemadotorg-jsonld-recipe-config-action-design.md
git commit -m "docs: sync JSON-LD recipe action spec AI-assisted by Codex"
```

Expected: either no-op if the spec already matches, or a small documentation-only commit.
