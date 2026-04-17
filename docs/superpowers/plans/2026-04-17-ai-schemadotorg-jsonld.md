# AI Schema.org JSON-LD Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ai_schemadotorg_jsonld`, a Drupal sandbox module that attaches a Schema.org JSON-LD field to content types, populates it via AI Automators, and injects it into page headers.

**Architecture:** Single module in `web/modules/sandbox/ai_schemadotorg_jsonld/`. A builder service creates the field, automator config, and display components. An event subscriber cleans AI responses. A token resolver renders nodes for the LLM prompt. Hooks wire page output, field access, and the copy button.

**Tech Stack:** Drupal ^11.3, `ai:ai_automators` ^1.3, `field_widget_actions:field_widget_actions` ^1.3, `json_field:json_field` ^1.7. Optional soft dependency on `json_field:json_field_widget`.

---

## File Map

| File | Responsibility |
|---|---|
| `ai_schemadotorg_jsonld.info.yml` | Module metadata and dependencies |
| `ai_schemadotorg_jsonld.module` | Empty procedural shell |
| `ai_schemadotorg_jsonld.services.yml` | Autowired service registrations |
| `ai_schemadotorg_jsonld.routing.yml` | Settings form route |
| `ai_schemadotorg_jsonld.links.menu.yml` | Admin menu link |
| `ai_schemadotorg_jsonld.libraries.yml` | Copy button JS/CSS library |
| `ai_schemadotorg_jsonld.token.inc` | Thin procedural wrappers for token hooks |
| `composer.json` | Module package metadata |
| `config/install/ai_schemadotorg_jsonld.settings.yml` | Default config |
| `src/AiSchemaDotOrgJsonLdBuilderInterface.php` | Builder contract |
| `src/AiSchemaDotOrgJsonLdBuilder.php` | Field + automator + display creation |
| `src/AiSchemaDotOrgJsonLdBreadcrumbListInterface.php` | Breadcrumb contract |
| `src/AiSchemaDotOrgJsonLdBreadcrumbList.php` | BreadcrumbList JSON-LD array builder |
| `src/AiSchemaDotOrgJsonLdTokenResolverInterface.php` | Token contract |
| `src/AiSchemaDotOrgJsonLdTokenResolver.php` | Renders node as anon/default-theme for AI prompt |
| `src/EventSubscriber/AiSchemaDotOrgJsonLdEventSubscriber.php` | Strips/validates JSON from AI responses |
| `src/Form/AiSchemaDotOrgJsonLdSettingsForm.php` | Admin settings UI |
| `src/Hook/AiSchemaDotOrgJsonLdHooks.php` | All OOP hook implementations |
| `js/ai_schemadotorg_jsonld.copy.js` | Copy-to-clipboard button behaviour |
| `css/ai_schemadotorg_jsonld.copy.css` | Copy button styles |
| `tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php` | Field creation, idempotency, delete cascade |
| `tests/src/Unit/AiSchemaDotOrgJsonLdEventSubscriberTest.php` | JSON extraction edge cases |
| `tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php` | Admin form interactions |
| `tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php` | Token render + post-processing |
| `tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php` | Page header JSON-LD injection |

---

## Task 1: Module Scaffold

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.info.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.module`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.services.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.routing.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.links.menu.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/composer.json`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/phpcs.xml.dist` (copy from `entity_labels`)
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/phpstan.neon` (copy from `entity_labels`)
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/.gitlab-ci.yml` (copy from `entity_labels`)
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/config/install/ai_schemadotorg_jsonld.settings.yml`

- [ ] **Step 1: Create the module directory and info.yml**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.info.yml
name: 'AI Schema.org JSON-LD'
type: module
description: 'Uses AI Automators to generate and attach Schema.org JSON-LD to content types.'
package: AI
core_version_requirement: ^11.3
dependencies:
  - ai:ai_automators
  - json_field:json_field
  - field_widget_actions:field_widget_actions
# Optional: json_field:json_field_widget — not listed here as it is a soft
# dependency only. The module checks moduleExists('json_field_widget') at runtime.
```

- [ ] **Step 2: Create the module shell**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.module

/**
 * @file
 * AI Schema.org JSON-LD module.
 */

declare(strict_types=1);
```

- [ ] **Step 3: Create services.yml**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.services.yml
services:

  _defaults:
    autowire: true

  ai_schemadotorg_jsonld.builder:
    class: Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilder

  Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface:
    alias: ai_schemadotorg_jsonld.builder

  ai_schemadotorg_jsonld.breadcrumb_list:
    class: Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbList
    # ChainBreadcrumbBuilderInterface is not auto-aliased in Drupal core,
    # so we wire it explicitly.
    arguments:
      - '@renderer'
      - '@breadcrumb'

  Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface:
    alias: ai_schemadotorg_jsonld.breadcrumb_list

  ai_schemadotorg_jsonld.token_resolver:
    class: Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolver

  Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolverInterface:
    alias: ai_schemadotorg_jsonld.token_resolver

  Drupal\ai_schemadotorg_jsonld\EventSubscriber\AiSchemaDotOrgJsonLdEventSubscriber:
    tags:
      - { name: event_subscriber }

  Drupal\ai_schemadotorg_jsonld\Hook\AiSchemaDotOrgJsonLdHooks:
    autowire: true
```

- [ ] **Step 4: Create routing.yml**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.routing.yml
ai_schemadotorg_jsonld.settings:
  path: '/admin/config/ai/schemadotorg-jsonld'
  defaults:
    _form: '\Drupal\ai_schemadotorg_jsonld\Form\AiSchemaDotOrgJsonLdSettingsForm'
    _title: 'AI Schema.org JSON-LD'
  requirements:
    _permission: 'administer site configuration'
```

- [ ] **Step 5: Create links.menu.yml**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.links.menu.yml
ai_schemadotorg_jsonld.settings:
  title: 'AI Schema.org JSON-LD'
  description: 'Configure AI-generated Schema.org JSON-LD for content types.'
  route_name: ai_schemadotorg_jsonld.settings
  parent: system.admin_config_ai
  weight: 10
```

- [ ] **Step 6: Create composer.json**

```json
{
    "name": "drupal/ai_schemadotorg_jsonld",
    "description": "Uses AI Automators to generate and attach Schema.org JSON-LD to content types.",
    "type": "drupal-module",
    "license": "GPL-2.0-or-later",
    "require": {
        "drupal/core": "^11.3",
        "drupal/ai": "^1.3",
        "drupal/field_widget_actions": "^1.3",
        "drupal/json_field": "^1.7"
    }
}
```

- [ ] **Step 7: Copy quality config files from entity_labels**

```bash
cp web/modules/sandbox/entity_labels/phpcs.xml.dist web/modules/sandbox/ai_schemadotorg_jsonld/phpcs.xml.dist
cp web/modules/sandbox/entity_labels/phpstan.neon web/modules/sandbox/ai_schemadotorg_jsonld/phpstan.neon
cp web/modules/sandbox/entity_labels/.gitlab-ci.yml web/modules/sandbox/ai_schemadotorg_jsonld/.gitlab-ci.yml
```

Edit `phpcs.xml.dist` to replace the ruleset name:

```xml
<ruleset name="ai_schemadotorg_jsonld">
  <description>AI Schema.org JSON-LD coding styles</description>
  <!-- ... rest is identical to entity_labels version ... -->
```

- [ ] **Step 8: Create default settings config**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/config/install/ai_schemadotorg_jsonld.settings.yml
prompt: |
  Generate valid Schema.org JSON-LD for the content below:

  # Input

  Type: [node:content-type]
  URL: [node:url]
  Title: [node:title]
  Summary: [node:summary]
  Image: [node:field_image]

  Body:
  [node:body]

  Content:
  [node:ai_schemadotorg_jsonld:content]

  Current JSON-LD: (This will be omitted for new content)
  [node:field_schemadotorg_jsonld]

  # Requirements

  ## Response

  - Return ONLY the JSON-LD object. No explanatory text, no markdown fences, no preamble.
  - Output must begin with { and end with }.

  ## Schema.org JSON-LD
  - Use only valid Schema.org types and properties (https://schema.org).
  - Set @context to "https://schema.org".
  - Set url to the canonical URL provided above.
  - Choose the most specific applicable @type for the content type given.
  - Use absolute URLs for all links and images.

  ## Schema.org properties and values

  - Try to include all text in the 'Body' and 'Full Content'.
  - Do not fabricate values or URLs, only include text/values/urls that are in the content.

  ## Misc

  - If Current JSON-LD is provided, preserve any manually curated properties and improve or extend — do not discard existing values without cause.
  - Follow Google's Structured Data guidelines: https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data

  # Output format

  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "url": "{url}",
    "name": "{title}",
    "description": "{summary}",
    "text": "{content}",
    "image": [
      {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "{image:src}",
        "description": "{image:alt}"
      }
    ]
  }
default_jsonld: ''
breadcrumb_jsonld: false
bundles: []
```

- [ ] **Step 9: Verify module loads**

```bash
ddev drush en ai_schemadotorg_jsonld -y
```

Expected: `[success] Successfully enabled: ai_schemadotorg_jsonld`

- [ ] **Step 10: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/
git commit -m "feat: scaffold ai_schemadotorg_jsonld module with routing, services, and default config

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 2: Builder Service

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php`

- [ ] **Step 1: Write the failing kernel test**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

/**
 * Tests AiSchemaDotOrgJsonLdBuilder.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('entity_form_display');
    $this->installEntitySchema('entity_view_display');
    $this->installEntitySchema('ai_automator');
    $this->installConfig(['system', 'field', 'node', 'ai_schemadotorg_jsonld']);

    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  }

  /**
   * Tests addFieldToEntity creates all required config.
   */
  public function testAddField(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
    $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);

    $builder->addFieldToEntity('node', 'page');

    // Check that field storage exists.
    $field_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->load('node.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_storage, 'FieldStorageConfig exists.');

    // Check that field instance exists and is translatable.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'FieldConfig exists.');
    $this->assertTrue($field_config->isTranslatable(), 'FieldConfig is translatable.');

    // Check that AI automator config exists.
    $automator = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNotNull($automator, 'AiAutomator config entity exists.');

    // Check that form display includes the field at weight 99.
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.page.default');
    $component = $form_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'Form display component exists.');
    $this->assertSame(99, $component['weight'], 'Form display weight is 99.');

    // Check that view display includes the field at weight 99.
    $view_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.page.default');
    $component = $view_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'View display component exists.');
    $this->assertSame(99, $component['weight'], 'View display weight is 99.');

    // Check idempotency — calling again must not throw.
    $builder->addFieldToEntity('node', 'page');
    $this->addToAssertionCount(1);

    // Check cascade delete — deleting FieldConfig should remove the automator.
    FieldConfig::load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->delete();
    $this->container->get('entity_type.manager')->getStorage('ai_automator')->resetCache();
    $automator_after_delete = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNull($automator_after_delete, 'AiAutomator is deleted when FieldConfig is deleted.');
  }

}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
```

Expected: FAIL — class `AiSchemaDotOrgJsonLdBuilderInterface` not found.

- [ ] **Step 3: Create the interface**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

/**
 * Interface for the AI Schema.org JSON-LD builder service.
 */
interface AiSchemaDotOrgJsonLdBuilderInterface {

  /**
   * The name of the Schema.org JSON-LD field.
   */
  const FIELD_NAME = 'field_schemadotorg_jsonld';

  /**
   * Adds the Schema.org JSON-LD field and related config to an entity bundle.
   *
   * Creates field storage (always up-to-date), field instance, AI automator,
   * and form/view display components. Steps 3–5 are skipped if the field
   * instance already existed.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node').
   * @param string $bundle
   *   The bundle machine name (e.g. 'page').
   */
  public function addFieldToEntity(string $entity_type_id, string $bundle): void;

}
```

- [ ] **Step 4: Create the builder implementation**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Creates and manages the Schema.org JSON-LD field on entity bundles.
 */
final class AiSchemaDotOrgJsonLdBuilder implements AiSchemaDotOrgJsonLdBuilderInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdBuilder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addFieldToEntity(string $entity_type_id, string $bundle): void {
    $this->createFieldStorage($entity_type_id);
    $created = $this->createField($entity_type_id, $bundle);
    if (!$created) {
      return;
    }
    $this->createAutomator($entity_type_id, $bundle);
    $this->addFormDisplayComponent($entity_type_id, $bundle);
    $this->addViewDisplayComponent($entity_type_id, $bundle);
  }

  /**
   * Creates or updates the field storage config.
   */
  private function createFieldStorage(string $entity_type_id): void {
    $storage_id = $entity_type_id . '.' . self::FIELD_NAME;
    $field_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load($storage_id);

    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => self::FIELD_NAME,
        'entity_type' => $entity_type_id,
        'type' => 'json_native',
        'cardinality' => 1,
        'translatable' => TRUE,
      ]);
    }

    $field_storage->save();
  }

  /**
   * Creates the field instance if it does not already exist.
   *
   * @return bool
   *   TRUE if the field was created, FALSE if it already existed.
   */
  private function createField(string $entity_type_id, string $bundle): bool {
    $field_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME;
    $existing = $this->entityTypeManager
      ->getStorage('field_config')
      ->load($field_id);

    if ($existing) {
      return FALSE;
    }

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'label' => 'Schema.org JSON-LD',
      'required' => FALSE,
      'translatable' => TRUE,
    ])->save();

    return TRUE;
  }

  /**
   * Creates the AI automator config entity.
   */
  private function createAutomator(string $entity_type_id, string $bundle): void {
    $automator_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME . '.default';
    $existing = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load($automator_id);

    if ($existing) {
      return;
    }

    $prompt = $this->configFactory
      ->get('ai_schemadotorg_jsonld.settings')
      ->get('prompt');

    $this->entityTypeManager->getStorage('ai_automator')->create([
      'id' => $automator_id,
      'label' => 'Schema.org JSON-LD Default',
      'rule' => 'llm_json_native_field',
      'input_mode' => 'token',
      'weight' => 100,
      'worker_type' => 'field_widget_actions',
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => self::FIELD_NAME,
      'edit_mode' => FALSE,
      'base_field' => 'revision_log',
      'prompt' => '',
      'token' => $prompt,
      'plugin_config' => [
        'automator_enabled' => 1,
        'automator_rule' => 'llm_json_native_field',
        'automator_mode' => 'token',
        'automator_base_field' => 'revision_log',
        'automator_prompt' => '',
        'automator_token' => $prompt,
        'automator_edit_mode' => 0,
        'automator_label' => 'Schema.org JSON-LD Default',
        'automator_weight' => '100',
        'automator_worker_type' => 'field_widget_actions',
        'automator_ai_provider' => 'default_json',
      ],
    ])->save();
  }

  /**
   * Adds the field to the default form display.
   */
  private function addFormDisplayComponent(string $entity_type_id, string $bundle): void {
    $display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_type_id . '.' . $bundle . '.default');

    if (!$display) {
      return;
    }

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $widget_type = $this->moduleHandler->moduleExists('json_field_widget')
      ? 'json_editor'
      : 'json_textarea';

    $action_uuid = $this->uuid->generate();

    $display->setComponent(self::FIELD_NAME, [
      'type' => $widget_type,
      'weight' => 99,
      'third_party_settings' => [
        'field_widget_actions' => [
          $action_uuid => [
            'plugin_id' => 'automator_json',
            'enabled' => TRUE,
            'weight' => 0,
            'button_label' => 'Generate Schema.org JSON-LD',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Adds the field to the default view display.
   */
  private function addViewDisplayComponent(string $entity_type_id, string $bundle): void {
    $display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entity_type_id . '.' . $bundle . '.default');

    if (!$display) {
      return;
    }

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $display->setComponent(self::FIELD_NAME, [
      'type' => 'pretty',
      'weight' => 99,
    ])->save();
  }

}
```

- [ ] **Step 5: Run test to confirm it passes**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
```

Expected: PASS. If the cascade delete assertion fails, note it in a comment — this is a potential bug in `ai_automators` where orphaned automators remain after field deletion.

- [ ] **Step 6: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilderInterface.php \
        web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBuilder.php \
        web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Kernel/AiSchemaDotOrgJsonLdBuilderTest.php
git commit -m "feat: add AiSchemaDotOrgJsonLdBuilder service with kernel test

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 3: Event Subscriber

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/EventSubscriber/AiSchemaDotOrgJsonLdEventSubscriber.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Unit/AiSchemaDotOrgJsonLdEventSubscriberTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Unit/AiSchemaDotOrgJsonLdEventSubscriberTest.php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_automators\Event\ValuesChangeEvent;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\EventSubscriber\AiSchemaDotOrgJsonLdEventSubscriber;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Tests AiSchemaDotOrgJsonLdEventSubscriber.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdEventSubscriberTest extends UnitTestCase {

  /**
   * The event subscriber under test.
   */
  private AiSchemaDotOrgJsonLdEventSubscriber $subscriber;

  /**
   * The messenger mock.
   */
  private MessengerInterface $messenger;

  /**
   * The logger mock.
   */
  private LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->logger);

    $this->subscriber = new AiSchemaDotOrgJsonLdEventSubscriber(
      $this->messenger,
      $logger_factory,
    );
  }

  /**
   * Builds a ValuesChangeEvent for the JSON-LD field.
   */
  private function buildEvent(string $raw_value): ValuesChangeEvent {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getName')
      ->willReturn(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);

    $entity = $this->createMock(ContentEntityInterface::class);

    return new ValuesChangeEvent(
      [['value' => $raw_value]],
      $entity,
      $field_definition,
      [],
    );
  }

  /**
   * Tests that clean JSON is returned unchanged.
   */
  public function testValidJson(): void {
    $event = $this->buildEvent('{"@type":"WebPage"}');
    $this->messenger->expects($this->never())->method('addWarning');
    $this->subscriber->onValuesChange($event);

    // Check that the value is returned unchanged.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests JSON wrapped in markdown fences is extracted.
   */
  public function testJsonInMarkdownFence(): void {
    $event = $this->buildEvent("```json\n{\"@type\":\"WebPage\"}\n```");
    $this->subscriber->onValuesChange($event);

    // Check that the extracted value equals the inner JSON.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests JSON surrounded by explanatory text is extracted.
   */
  public function testJsonWithSurroundingText(): void {
    $event = $this->buildEvent('Here is the JSON: {"@type":"WebPage"} Hope that helps!');
    $this->subscriber->onValuesChange($event);

    // Check that only the JSON object is kept.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests that invalid JSON results in empty value with warnings.
   */
  public function testInvalidJson(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');

    $event = $this->buildEvent('{not valid json}');
    $this->subscriber->onValuesChange($event);

    // Check that the value is cleared.
    $this->assertSame('', $event->getValues()[0]['value']);
  }

  /**
   * Tests that a response with no JSON object triggers warnings.
   */
  public function testNoJsonFound(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');

    $event = $this->buildEvent('No JSON here at all');
    $this->subscriber->onValuesChange($event);

    // Check that the value is cleared.
    $this->assertSame('', $event->getValues()[0]['value']);
  }

  /**
   * Tests that unrelated fields are ignored.
   */
  public function testUnrelatedFieldIsIgnored(): void {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getName')->willReturn('field_other');

    $entity = $this->createMock(ContentEntityInterface::class);
    $event = new ValuesChangeEvent(
      [['value' => 'garbage']],
      $entity,
      $field_definition,
      [],
    );

    $this->messenger->expects($this->never())->method('addWarning');
    $this->subscriber->onValuesChange($event);

    // Check that the value is untouched.
    $this->assertSame('garbage', $event->getValues()[0]['value']);
  }

}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Unit/AiSchemaDotOrgJsonLdEventSubscriberTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create the event subscriber**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/EventSubscriber/AiSchemaDotOrgJsonLdEventSubscriber.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_automators\Event\ValuesChangeEvent;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extracts and validates JSON from AI automator responses for the JSON-LD field.
 */
final class AiSchemaDotOrgJsonLdEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    private readonly MessengerInterface $messenger,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ValuesChangeEvent::EVENT_NAME => 'onValuesChange',
    ];
  }

  /**
   * Cleans the AI response value for the JSON-LD field.
   */
  public function onValuesChange(ValuesChangeEvent $event): void {
    if ($event->getFieldDefinition()->getName() !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }

    $values = $event->getValues();
    $processed = [];
    foreach ($values as $value) {
      $raw = trim($value['value'] ?? '');
      $processed[] = ['value' => $this->extractJson($raw)];
    }

    $event->setValues($processed);
  }

  /**
   * Extracts and validates a JSON object from a raw string.
   *
   * @param string $raw
   *   The raw string from the AI response.
   *
   * @return string
   *   The extracted JSON string, or empty string on failure.
   */
  private function extractJson(string $raw): string {
    if ($raw === '') {
      return '';
    }

    if (str_starts_with($raw, '{') && str_ends_with($raw, '}')) {
      $json = $raw;
    }
    else {
      $start = strpos($raw, '{');
      $end = strrpos($raw, '}');

      if ($start === FALSE || $end === FALSE) {
        $this->loggerFactory->get('ai_schemadotorg_jsonld')
          ->warning('Could not find JSON object boundaries in AI response.');
        $this->messenger->addWarning(
          $this->t('The AI response did not contain a valid JSON object. The field has been left empty.')
        );
        return '';
      }

      $json = substr($raw, $start, $end - $start + 1);
    }

    try {
      json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      return $json;
    }
    catch (\JsonException $e) {
      $this->loggerFactory->get('ai_schemadotorg_jsonld')
        ->warning('Invalid JSON in AI response: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addWarning(
        $this->t('The AI response contained invalid JSON. The field has been left empty.')
      );
      return '';
    }
  }

}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Unit/AiSchemaDotOrgJsonLdEventSubscriberTest.php
```

Expected: All 6 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/EventSubscriber/ \
        web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Unit/
git commit -m "feat: add AiSchemaDotOrgJsonLdEventSubscriber with unit tests

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 4: Breadcrumb Service

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbListInterface.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbList.php`

- [ ] **Step 1: Create the interface**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbListInterface.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Interface for the AI Schema.org JSON-LD breadcrumb list service.
 */
interface AiSchemaDotOrgJsonLdBreadcrumbListInterface {

  /**
   * Builds a BreadcrumbList JSON-LD array for the current page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Metadata for cache bubbling.
   *
   * @return array|null
   *   A BreadcrumbList JSON-LD array, or NULL if no breadcrumb applies.
   */
  public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): ?array;

}
```

- [ ] **Step 2: Create the implementation**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbList.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

// Note: RouteMatchInterface is used only as a parameter type in build(), not injected.

/**
 * Builds a BreadcrumbList JSON-LD array for the current page.
 *
 * Modelled on SchemaDotOrgJsonLdBreadcrumbManager but without any dependency
 * on the schemadotorg module. Named BreadcrumbList (not BreadcrumbBuilder) to
 * signal it produces data, not a Drupal breadcrumb object.
 */
final class AiSchemaDotOrgJsonLdBreadcrumbList implements AiSchemaDotOrgJsonLdBreadcrumbListInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdBreadcrumbList object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface $breadcrumb
   *   The chain breadcrumb builder.
   */
  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly ChainBreadcrumbBuilderInterface $breadcrumb,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): ?array {
    if (!$this->breadcrumb->applies($route_match)) {
      return NULL;
    }

    $breadcrumb = $this->breadcrumb->build($route_match);
    $links = $breadcrumb->getLinks();
    if (empty($links)) {
      return NULL;
    }

    $bubbleable_metadata->addCacheableDependency($breadcrumb);

    $items = [];
    $position = 1;
    foreach ($links as $link) {
      $id = $link->getUrl()->setAbsolute()->toString();
      $text = $link->getText();
      if (is_array($text)) {
        $text = $this->renderer->renderInIsolation($text);
      }

      $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'item' => [
          '@id' => $id,
          'name' => (string) $text,
        ],
      ];
      $position++;
    }

    // Append the current route's node as the final list item.
    $node = $route_match->getParameter('node');
    if ($node) {
      $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'item' => [
          '@id' => Url::fromRouteMatch($route_match)->setAbsolute()->toString(),
          'name' => $node->label(),
        ],
      ];
    }

    return [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => $items,
    ];
  }

}
```

- [ ] **Step 3: Clear caches and verify service resolves**

```bash
ddev drush cr
ddev drush ev "print_r(\Drupal::service('ai_schemadotorg_jsonld.breadcrumb_list'));"
```

Expected: Service object printed without errors.

- [ ] **Step 4: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbListInterface.php \
        web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdBreadcrumbList.php
git commit -m "feat: add AiSchemaDotOrgJsonLdBreadcrumbList service

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 5: Token Resolver

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolverInterface.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolver.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.token.inc`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests AiSchemaDotOrgJsonLdTokenResolver.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdTokenResolverTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'token',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Tests the [node:ai_schemadotorg_jsonld:content] token.
   */
  public function testContentToken(): void {
    $body_text = 'The quick brown fox jumps over the lazy dog.';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'body' => ['value' => $body_text, 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $node->save();

    /** @var \Drupal\Core\Utility\Token $token_service */
    $token_service = $this->container->get('token');
    $result = $token_service->replace(
      '[node:ai_schemadotorg_jsonld:content]',
      ['node' => $node],
      ['clear' => TRUE]
    );

    // Check that the rendered output contains expected body text.
    $this->assertStringContainsString($body_text, $result);

    // Check that root-relative URLs have been converted to absolute URLs.
    $this->assertStringNotContainsString('href="/', $result);
    $this->assertStringNotContainsString('src="/', $result);

    // Check that rendering was performed as anonymous (no admin markup).
    $this->assertStringNotContainsString('contextual-links', $result);
    $this->assertStringNotContainsString('edit-in-place', $result);
  }

}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php
```

Expected: FAIL — token not defined.

- [ ] **Step 3: Create the interface**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolverInterface.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\node\NodeInterface;

/**
 * Interface for the AI Schema.org JSON-LD token resolver service.
 */
interface AiSchemaDotOrgJsonLdTokenResolverInterface {

  /**
   * Resolves the [node:ai_schemadotorg_jsonld:content] token value.
   *
   * Renders the node as the anonymous user in the site default theme,
   * then post-processes the HTML for LLM consumption.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to render.
   *
   * @return string
   *   The post-processed HTML of the rendered node.
   */
  public function resolve(NodeInterface $node): string;

}
```

- [ ] **Step 4: Create the token resolver implementation**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolver.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders a node as the anonymous user for use in AI prompts.
 */
final class AiSchemaDotOrgJsonLdTokenResolver implements AiSchemaDotOrgJsonLdTokenResolverInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdTokenResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $themeInitialization
   *   The theme initialization service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RendererInterface $renderer,
    private readonly RequestStack $requestStack,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeInitializationInterface $themeInitialization,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(NodeInterface $node): string {
    // Switch to anonymous user.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Switch to site default theme.
    $default_theme = $this->configFactory->get('system.theme')->get('default');
    $active_theme = $this->themeInitialization->initTheme($default_theme);
    $original_theme = $this->themeManager->getActiveTheme();
    $this->themeManager->setActiveTheme($active_theme);

    try {
      $view_builder = $this->entityTypeManager->getViewBuilder('node');
      $build = $view_builder->view($node, 'default');
      $html = (string) $this->renderer->renderInIsolation($build);
    }
    finally {
      // Always restore account and theme.
      $this->accountSwitcher->switchBack();
      $this->themeManager->setActiveTheme($original_theme);
    }

    return $this->postProcess($html);
  }

  /**
   * Post-processes the rendered HTML for LLM consumption.
   *
   * - Strips outer wrapping <div><div>...</div></div> pairs (single child only).
   * - Converts root-relative href and src attributes to absolute URLs.
   * - Preserves semantic markup.
   *
   * @param string $html
   *   The raw rendered HTML.
   *
   * @return string
   *   The post-processed HTML.
   */
  private function postProcess(string $html): string {
    $html = $this->stripOuterWrappingDivs($html);
    $html = $this->absolutizeUrls($html);
    return $html;
  }

  /**
   * Removes outer <div><div>...</div></div> pairs with a single direct child.
   */
  private function stripOuterWrappingDivs(string $html): string {
    $trimmed = trim($html);
    // Match an outer <div> whose only direct child is another <div>.
    if (preg_match('/^<div[^>]*>\s*(<div[\s\S]*<\/div>)\s*<\/div>$/s', $trimmed, $matches)) {
      return trim($matches[1]);
    }
    return $trimmed;
  }

  /**
   * Converts root-relative href and src attributes to absolute URLs.
   */
  private function absolutizeUrls(string $html): string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return $html;
    }
    $base = $request->getSchemeAndHttpHost();

    // Replace root-relative hrefs and srcs (starting with /).
    $html = preg_replace('/\bhref="(\/[^"]*)"/', 'href="' . $base . '$1"', $html);
    $html = preg_replace('/\bsrc="(\/[^"]*)"/', 'src="' . $base . '$1"', $html);

    return $html;
  }

}
```

- [ ] **Step 5: Create the token.inc file**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.token.inc

/**
 * @file
 * Token definitions for the AI Schema.org JSON-LD module.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;

/**
 * Implements hook_token_info().
 */
function ai_schemadotorg_jsonld_token_info(): array {
  return [
    'tokens' => [
      'node' => [
        'ai_schemadotorg_jsonld:content' => [
          'name' => t('AI Schema.org JSON-LD: Full content'),
          'description' => t('Renders the node as the anonymous user in the site default theme for use in AI prompts.'),
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function ai_schemadotorg_jsonld_tokens(string $type, array $tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];

  if ($type !== 'node' || empty($data['node'])) {
    return $replacements;
  }

  $node = $data['node'];
  if (!$node instanceof NodeInterface) {
    return $replacements;
  }

  foreach ($tokens as $name => $original) {
    if ($name === 'ai_schemadotorg_jsonld:content') {
      /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolverInterface $resolver */
      $resolver = \Drupal::service('ai_schemadotorg_jsonld.token_resolver');
      $replacements[$original] = $resolver->resolve($node);
    }
  }

  return $replacements;
}
```

- [ ] **Step 6: Run test to confirm it passes**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolverInterface.php \
        web/modules/sandbox/ai_schemadotorg_jsonld/src/AiSchemaDotOrgJsonLdTokenResolver.php \
        web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.token.inc \
        web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdTokenResolverTest.php
git commit -m "feat: add AiSchemaDotOrgJsonLdTokenResolver and token.inc with functional test

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 6: Settings Form

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Form/AiSchemaDotOrgJsonLdSettingsForm.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;

/**
 * Tests AiSchemaDotOrgJsonLdSettingsForm.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
  }

  /**
   * Tests the settings form.
   */
  public function testSettingsForm(): void {
    // Check that the settings form loads.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the content types fieldset lists available node bundles.
    $this->assertSession()->elementExists('css', 'fieldset');
    $this->assertSession()->checkboxExists('bundles[page]');

    // Check that the Additional settings details element is present.
    $this->assertSession()->elementExists('css', 'details');
    $this->assertSession()->fieldExists('prompt');
    $this->assertSession()->fieldExists('default_jsonld');
    $this->assertSession()->fieldExists('breadcrumb_jsonld');

    // Select page bundle and save.
    $this->submitForm(['bundles[page]' => TRUE], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Check that page is persisted in bundles config.
    $config = $this->config('ai_schemadotorg_jsonld.settings');
    $this->assertContains('page', $config->get('bundles'));

    // Check that field was created.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'Field was created after saving form.');

    // Reload and check page is pre-checked and disabled.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->checkboxChecked('bundles[page]');
    $this->assertSession()->elementAttributeContains(
      'css',
      'input[name="bundles[page]"]',
      'disabled',
      'disabled'
    );

    // Check that Operations column shows Edit and Delete links for page.
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Delete');
  }

}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php
```

Expected: FAIL — form class not found.

- [ ] **Step 3: Create the settings form**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/Form/AiSchemaDotOrgJsonLdSettingsForm.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Url;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Schema.org JSON-LD settings.
 */
final class AiSchemaDotOrgJsonLdSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The builder service.
   */
  protected AiSchemaDotOrgJsonLdBuilderInterface $builder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->builder = $container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_schemadotorg_jsonld_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_schemadotorg_jsonld.settings');
    $configured_bundles = $config->get('bundles') ?? [];

    // --- Content types fieldset ---
    $form['bundles_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content types'),
    ];

    $header = [
      'label' => $this->t('Content type'),
      'machine_name' => $this->t('Machine name'),
      'operations' => $this->t('Operations'),
    ];

    $options = [];
    $disabled_bundles = [];

    foreach ($this->getNodeTypeOptions() as $bundle => $label) {
      $has_field = (bool) $this->entityTypeManager
        ->getStorage('field_config')
        ->load('node.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);

      $operations = '';
      if ($has_field) {
        $edit_url = Url::fromRoute('entity.field_config.node_field_edit_form', [
          'node_type' => $bundle,
          'field_config' => 'node.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
        ]);
        $delete_url = Url::fromRoute('entity.field_config.node_field_delete_form', [
          'node_type' => $bundle,
          'field_config' => 'node.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
        ], ['query' => ['destination' => '/admin/config/ai/schemadotorg-jsonld']]);

        $operations = $this->t('<a href=":edit">Edit</a> | <a href=":delete">Delete</a>', [
          ':edit' => $edit_url->toString(),
          ':delete' => $delete_url->toString(),
        ]);

        $disabled_bundles[] = $bundle;
      }

      $options[$bundle] = [
        'label' => $label,
        'machine_name' => $bundle,
        'operations' => ['data' => ['#markup' => $operations]],
      ];
    }

    $form['bundles_fieldset']['bundles'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#default_value' => array_fill_keys(array_unique(array_merge($configured_bundles, $disabled_bundles)), TRUE),
      '#after_build' => [[$this, 'disableExistingBundleCheckboxes']],
    ];

    // Store disabled bundles in form state for use in after_build and submit.
    $form_state->set('disabled_bundles', $disabled_bundles);

    // --- Additional settings ---
    $form['additional_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional settings'),
      '#open' => FALSE,
    ];

    $form['additional_settings']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default prompt'),
      '#description' => $this->t('Token-based prompt sent to the LLM. Use <code>[node:ai_schemadotorg_jsonld:content]</code> to include the full rendered node.'),
      '#rows' => 20,
      '#config_target' => new ConfigTarget('ai_schemadotorg_jsonld.settings', 'prompt'),
    ];

    $default_jsonld_type = $this->moduleHandler->moduleExists('json_field_widget')
      ? 'json_editor'
      : 'textarea';

    $form['additional_settings']['default_jsonld'] = [
      '#type' => $default_jsonld_type,
      '#title' => $this->t('Default JSON-LD'),
      '#description' => $this->t('Static JSON-LD injected into every page (e.g. Organization or WebSite schema). Leave blank to disable.'),
      '#config_target' => new ConfigTarget('ai_schemadotorg_jsonld.settings', 'default_jsonld'),
      '#element_validate' => [[$this, 'validateJson']],
    ];

    if ($default_jsonld_type === 'json_editor') {
      $form['additional_settings']['default_jsonld']['#attached']['library'][] = 'json_field_widget/json_editor.widget';
    }

    $form['additional_settings']['breadcrumb_jsonld'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include breadcrumb JSON-LD'),
      '#description' => $this->t('Attach a BreadcrumbList JSON-LD block to each page.'),
      '#config_target' => new ConfigTarget('ai_schemadotorg_jsonld.settings', 'breadcrumb_jsonld'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * After-build callback: disables checkboxes for already-configured bundles.
   */
  public function disableExistingBundleCheckboxes(array $element, FormStateInterface $form_state): array {
    $disabled_bundles = $form_state->get('disabled_bundles') ?? [];
    foreach ($disabled_bundles as $bundle) {
      if (isset($element[$bundle])) {
        $element[$bundle]['#disabled'] = TRUE;
      }
    }
    return $element;
  }

  /**
   * Element validate callback: ensures the default_jsonld value is valid JSON.
   */
  public function validateJson(array &$element, FormStateInterface $form_state): void {
    $value = trim($element['#value']);
    if ($value === '') {
      return;
    }
    try {
      json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      $form_state->setError($element, $this->t('Default JSON-LD contains invalid JSON: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('ai_schemadotorg_jsonld.settings');
    $disabled_bundles = $form_state->get('disabled_bundles') ?? [];
    $selected_bundles = array_filter($form_state->getValue('bundles'));

    // Ensure already-configured bundles are always included.
    $merged_bundles = array_unique(array_merge(
      array_keys($selected_bundles),
      $disabled_bundles,
    ));
    sort($merged_bundles);

    // Add field to newly selected bundles.
    $current_bundles = $config->get('bundles') ?? [];
    $new_bundles = array_diff($merged_bundles, $current_bundles, $disabled_bundles);
    foreach ($new_bundles as $bundle) {
      $this->builder->addFieldToEntity('node', $bundle);
    }

    $config->set('bundles', $merged_bundles)->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns an array of node type labels keyed by machine name.
   *
   * @return string[]
   *   Node type labels keyed by machine name.
   */
  private function getNodeTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $type) {
      $options[$type->id()] = $type->label();
    }
    natcasesort($options);
    return $options;
  }

}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/Form/ \
        web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php
git commit -m "feat: add AiSchemaDotOrgJsonLdSettingsForm with functional test

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 7: Hooks

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Hook/AiSchemaDotOrgJsonLdHooks.php`

- [ ] **Step 1: Create the hooks class**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/src/Hook/AiSchemaDotOrgJsonLdHooks.php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the ai_schemadotorg_jsonld module.
 */
final class AiSchemaDotOrgJsonLdHooks {

  /**
   * Constructs an AiSchemaDotOrgJsonLdHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList
   *   The breadcrumb list service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList,
  ) {}

  /**
   * Implements hook_field_widget_action_info_alter().
   */
  #[Hook('field_widget_action_info_alter')]
  public function fieldWidgetActionInfoAlter(array &$definitions): void {
    if (!$this->moduleHandler->moduleExists('json_field_widget')) {
      return;
    }
    if (!isset($definitions['automator_json'])) {
      return;
    }
    if (!in_array('json_editor', $definitions['automator_json']['widget_types'], TRUE)) {
      $definitions['automator_json']['widget_types'][] = 'json_editor';
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $config = $this->configFactory->get('ai_schemadotorg_jsonld.settings');

    // Attach site-wide default JSON-LD.
    $default_jsonld = $config->get('default_jsonld');
    if (!empty($default_jsonld)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $default_jsonld,
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_default',
      ];
    }

    // Attach breadcrumb JSON-LD.
    if ($config->get('breadcrumb_jsonld')) {
      $bubbleable_metadata = new BubbleableMetadata();
      $breadcrumb_data = $this->breadcrumbList->build($this->routeMatch, $bubbleable_metadata);
      if ($breadcrumb_data !== NULL) {
        $bubbleable_metadata->applyTo($attachments);
        $attachments['#attached']['html_head'][] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#value' => json_encode($breadcrumb_data),
            '#attributes' => ['type' => 'application/ld+json'],
          ],
          'ai_schemadotorg_jsonld_breadcrumb',
        ];
      }
    }

    // Attach node field JSON-LD on canonical node routes.
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return;
    }
    if (!$node->hasField(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)) {
      return;
    }
    $field_value = $node->get(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->value;
    if (empty($field_value)) {
      return;
    }

    $attachments['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => $field_value,
        '#attributes' => ['type' => 'application/ld+json'],
      ],
      'ai_schemadotorg_jsonld_node_' . $node->id(),
    ];
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $widget_id = $context['widget']->getPluginId();
    $field_name = $context['items']->getFieldDefinition()->getName();

    if ($field_name !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }

    $allowed_widgets = ['json_textarea'];
    if ($this->moduleHandler->moduleExists('json_field_widget')) {
      $allowed_widgets[] = 'json_editor';
    }

    if (!in_array($widget_id, $allowed_widgets, TRUE)) {
      return;
    }

    $field_widget_complete_form['copy_jsonld'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-schemadotorg-jsonld-copy']],
      'button' => [
        '#type' => 'button',
        '#value' => t('Copy JSON-LD'),
        '#attributes' => [
          'class' => ['ai-schemadotorg-jsonld-copy-button', 'button--extrasmall'],
          'data-field-name' => $field_name,
        ],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['ai-schemadotorg-jsonld-copy-message']],
        '#plain_text' => t('JSON-LD copied to clipboard…'),
      ],
      '#attached' => ['library' => ['ai_schemadotorg_jsonld/copy']],
    ];
  }

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return AccessResult::neutral();
    }

    if ($operation === 'edit' && $items !== NULL) {
      $entity = $items->getEntity();
      if ($entity instanceof FieldableEntityInterface && $entity->isNew()) {
        return AccessResult::forbidden()
          ->addCacheableDependency($entity)
          ->setReason('Cannot edit Schema.org JSON-LD on unsaved entities.');
      }
    }

    if ($operation === 'view' && $items !== NULL) {
      $entity = $items->getEntity();
      if ($entity instanceof FieldableEntityInterface && !$entity->access('update', $account)) {
        return AccessResult::forbidden()
          ->cachePerUser()
          ->addCacheableDependency($entity);
      }
    }

    return AccessResult::neutral();
  }

}
```

- [ ] **Step 2: Clear caches and verify hooks register**

```bash
ddev drush cr
ddev drush ev "\Drupal::moduleHandler()->invokeAll('page_attachments', [&\$a]);"
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/src/Hook/
git commit -m "feat: add AiSchemaDotOrgJsonLdHooks with page_attachments, field_access, and widget hooks

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 8: Copy Button Assets

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.libraries.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/js/ai_schemadotorg_jsonld.copy.js`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/css/ai_schemadotorg_jsonld.copy.css`

- [ ] **Step 1: Create libraries.yml**

```yaml
# web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.libraries.yml
copy:
  js:
    js/ai_schemadotorg_jsonld.copy.js: {}
  css:
    component:
      css/ai_schemadotorg_jsonld.copy.css: {}
  dependencies:
    - core/drupal
    - core/once
```

- [ ] **Step 2: Create the copy JS**

Adapted from `schemadotorg_jsonld_preview.js`, targeting the widget field value instead of a hidden input:

```javascript
/* eslint-disable strict, no-undef, no-use-before-define */

/**
 * @file
 * AI Schema.org JSON-LD copy-to-clipboard behaviour.
 */

((Drupal, once) => {
  /**
   * AI Schema.org JSON-LD copy button.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.aiSchemaDotOrgJsonLdCopy = {
    attach: function attach(context) {
      once(
        'ai-schemadotorg-jsonld-copy',
        '.ai-schemadotorg-jsonld-copy',
        context,
      ).forEach((container) => {
        const button = container.querySelector('.ai-schemadotorg-jsonld-copy-button');
        const message = container.querySelector('.ai-schemadotorg-jsonld-copy-message');
        const fieldName = button ? button.dataset.fieldName : null;

        if (!button || !message || !fieldName) {
          return;
        }

        const textarea = context.querySelector
          ? context.querySelector(`[name="${fieldName}[0][value]"], textarea[data-drupal-selector*="${fieldName}"]`)
          : null;

        message.addEventListener('transitionend', hideMessage);

        button.addEventListener('click', (event) => {
          const value = textarea ? textarea.value : '';
          if (window.navigator.clipboard && value) {
            window.navigator.clipboard.writeText(
              `<script type="application/ld+json">\n${value}\n<\/script>`
            );
          }

          showMessage();
          Drupal.announce(Drupal.t('JSON-LD copied to clipboard…'));
          event.preventDefault();
        });

        function showMessage() {
          message.style.display = 'inline-block';
          // eslint-disable-next-line
          setTimeout(() => { message.style.opacity = '0'; }, 1500);
        }

        function hideMessage() {
          message.style.display = 'none';
          message.style.opacity = '1';
        }
      });
    },
  };
})(Drupal, once);
```

- [ ] **Step 3: Create the copy CSS**

```css
/* web/modules/sandbox/ai_schemadotorg_jsonld/css/ai_schemadotorg_jsonld.copy.css */

.ai-schemadotorg-jsonld-copy {
  margin-top: 0.5rem;
}

.ai-schemadotorg-jsonld-copy-message {
  display: none;
  margin-left: 0.5rem;
  opacity: 1;
  transition: opacity 0.5s ease;
  font-style: italic;
}
```

- [ ] **Step 4: Verify library loads on the settings form**

```bash
ddev drush cr
```

Visit `https://drupal-playground.ddev.site/admin/config/ai/schemadotorg-jsonld` and edit a node with `field_schemadotorg_jsonld`. Confirm the "Copy JSON-LD" button appears and clicking it copies to clipboard.

- [ ] **Step 5: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.libraries.yml \
        web/modules/sandbox/ai_schemadotorg_jsonld/js/ \
        web/modules/sandbox/ai_schemadotorg_jsonld/css/
git commit -m "feat: add copy-to-clipboard JS/CSS assets for JSON-LD field widget

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 9: Page Attachments Functional Test

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php
// web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;

/**
 * Tests that JSON-LD is attached to the page header.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdPageAttachmentsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Add the JSON-LD field to the page bundle.
    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToEntity('node', 'page');
  }

  /**
   * Tests JSON-LD tags in the page head.
   */
  public function testPageAttachments(): void {
    $default_jsonld = '{"@context":"https://schema.org","@type":"Organization","name":"Test Site"}';

    // Configure default JSON-LD and enable breadcrumb JSON-LD.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('default_jsonld', $default_jsonld)
      ->set('breadcrumb_jsonld', TRUE)
      ->save();

    // Create a node with a field_schemadotorg_jsonld value.
    $node_jsonld = '{"@context":"https://schema.org","@type":"WebPage","name":"Test page"}';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'status' => 1,
      AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME => ['value' => $node_jsonld],
    ]);
    $node->save();

    // Visit the node canonical URL.
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the default JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $default_jsonld . '</script>'
    );

    // Check that the node JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );

    // Visit a non-node page and check that node-specific tag is absent.
    $this->drupalGet('/');
    $this->assertSession()->responseNotContains(
      'ai_schemadotorg_jsonld_node_' . $node->id()
    );
    // Default JSON-LD should still appear on all pages.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $default_jsonld . '</script>'
    );
  }

}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php
```

Expected: FAIL — hooks not yet producing output.

- [ ] **Step 3: Clear caches and re-run after Task 7 hooks are in place**

```bash
ddev drush cr
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPageAttachmentsTest.php
git commit -m "test: add AiSchemaDotOrgJsonLdPageAttachmentsTest

AI-assisted by Claude Sonnet 4.6"
```

---

## Task 10: Code Quality and README

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/README.md`

- [ ] **Step 1: Run code-fix across the module**

```bash
ddev code-fix web/modules/sandbox/ai_schemadotorg_jsonld/
```

Review any changes. Re-run to confirm clean:

```bash
ddev code-review web/modules/sandbox/ai_schemadotorg_jsonld/
```

Expected: No errors or warnings.

- [ ] **Step 2: Run all tests**

```bash
ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/
```

Expected: All tests PASS.

- [ ] **Step 3: Create README.md**

```markdown
# AI Schema.org JSON-LD

Adds a `field_schemadotorg_jsonld` (`json_native`) field to selected content types, generates Schema.org JSON-LD via AI Automators, and injects the output into page headers as `<script type="application/ld+json">` tags.

## Requirements

- Drupal ^11.3
- [AI](https://www.drupal.org/project/ai) ^1.3 (with `ai_automators` submodule)
- [Field Widget Actions](https://www.drupal.org/project/field_widget_actions) ^1.3
- [JSON Field](https://www.drupal.org/project/json_field) ^1.7
- **Optional:** `json_field:json_field_widget` — enables the `json_editor` widget

## Installation

1. Enable the module: `drush en ai_schemadotorg_jsonld -y`
2. Configure an AI provider at `/admin/config/ai/settings`.
3. Go to `/admin/config/ai/schemadotorg-jsonld`, select content types, and save.

## Configuration

| Setting | Description |
|---|---|
| Content types | Selects which node bundles get the `field_schemadotorg_jsonld` field. |
| Prompt | The token-based prompt sent to the LLM. Includes `[node:ai_schemadotorg_jsonld:content]` by default. |
| Default JSON-LD | Site-wide JSON-LD injected on every page (e.g. `Organization` or `WebSite` schema). |
| Include breadcrumb JSON-LD | Attaches a `BreadcrumbList` JSON-LD block to each page. |

## Tokens

| Token | Description |
|---|---|
| `[node:ai_schemadotorg_jsonld:content]` | Renders the node as the anonymous user in the site default theme. Used in the AI prompt to give the LLM the full page content. |

## Architecture

- **`AiSchemaDotOrgJsonLdBuilder`** — Creates field storage, field instance, AI automator config, and form/view display components for a given entity type and bundle.
- **`AiSchemaDotOrgJsonLdBreadcrumbList`** — Builds a `BreadcrumbList` JSON-LD array from the current page's breadcrumb.
- **`AiSchemaDotOrgJsonLdTokenResolver`** — Renders a node as the anonymous user and post-processes the HTML (absolutize URLs, strip wrapper divs) for LLM consumption.
- **`AiSchemaDotOrgJsonLdEventSubscriber`** — Extracts and validates JSON from AI responses before the value is saved to the field.
- **`AiSchemaDotOrgJsonLdHooks`** — `hook_page_attachments` for JSON-LD header injection; `hook_entity_field_access` for access control; `hook_field_widget_complete_form_alter` for the Copy JSON-LD button.
```

- [ ] **Step 4: Final commit**

```bash
git add web/modules/sandbox/ai_schemadotorg_jsonld/README.md
git commit -m "docs: add README for ai_schemadotorg_jsonld module

AI-assisted by Claude Sonnet 4.6"
```
