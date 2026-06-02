# AI Schema.org JSON-LD Devel Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add core prompt editing UX for Schema.org JSON-LD automators and a companion `ai_schemadotorg_jsonld_devel` module that logs prompt/response pairs to a dedicated database table with admin UI for viewing, downloading, and clearing logs.

**Architecture:** Keep prompt editing in `ai_schemadotorg_jsonld` because it is part of the normal field editing workflow. Implement logging, log storage, settings injection, and log administration in a new optional `ai_schemadotorg_jsonld_devel` module that alters the core settings form and subscribes to AI events only when enabled. Use Drupal routes, local tasks, a dedicated database table, and focused functional/unit coverage following existing module patterns.

**Tech Stack:** Drupal 11 module development, OOP hooks, Config API, Form API, Controller API, EventSubscriberInterface, database schema/install hooks, BrowserTestBase, KernelTestBase, PHPUnit, DDEV

---

## File Structure

Core module files:
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Hook/AiSchemaDotOrgJsonLdFieldHooks.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Form/AiSchemaDotOrgJsonLdPromptForm.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.links.task.yml`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.routing.yml`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.services.yml`
- Create or modify: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`

Devel module files:
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.info.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.module`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.install`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.routing.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.links.task.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.services.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/config/install/ai_schemadotorg_jsonld_devel.settings.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/config/schema/ai_schemadotorg_jsonld_devel.schema.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/AiSchemaDotOrgJsonLdDevelLogStorage.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/AiSchemaDotOrgJsonLdDevelLogStorageInterface.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/Controller/AiSchemaDotOrgJsonLdDevelLogs.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/EventSubscriber/AiSchemaDotOrgJsonLdDevelEventSubscriber.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/Form/AiSchemaDotOrgJsonLdDevelClearForm.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`

Shared responsibilities:
- `AiSchemaDotOrgJsonLdPromptForm` edits only the `Automator Prompt (Token)` value for the matching `ai_automator`.
- `AiSchemaDotOrgJsonLdFieldHooks` adds the modal prompt edit link beside the JSON-LD widget when the entity already exists.
- `AiSchemaDotOrgJsonLdDevelLogStorage` owns inserts, reads, CSV rows, and truncate operations for `ai_schemadotorg_jsonld_logs`.
- `AiSchemaDotOrgJsonLdDevelEventSubscriber` logs entity type, entity id, prompt, and response only when the devel setting is enabled.
- `AiSchemaDotOrgJsonLdDevelLogs` builds the admin page and CSV download response.

### Task 1: Core prompt form route

**Files:**
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.routing.yml`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/ai_schemadotorg_jsonld.services.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Form/AiSchemaDotOrgJsonLdPromptForm.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`

- [ ] **Step 1: Write the failing functional test for the prompt form route**

```php
public function testPromptFormUpdatesAutomatorPrompt(): void {
  $this->drupalLogin($this->rootUser);
  $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/prompt/node/page');
  $this->assertSession()->statusCodeEquals(200);
  $this->assertSession()->pageTextContains('Automator Prompt (Token)');
  $this->assertSession()->fieldNotExists('Label');
}
```

- [ ] **Step 2: Run the new functional test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`
Expected: FAIL because the route and form class do not exist yet.

- [ ] **Step 3: Implement the minimal prompt form route and form class**

```yaml
ai_schemadotorg_jsonld_devel.prompt:
  path: '/admin/config/ai/schemadotorg-jsonld/prompt/{entity_type}/{bundle}'
  defaults:
    _form: '\Drupal\ai_schemadotorg_jsonld\Form\AiSchemaDotOrgJsonLdPromptForm'
    _title: 'Edit prompt'
  requirements:
    _permission: 'administer site configuration'
```

```php
class AiSchemaDotOrgJsonLdPromptForm extends FormBase {

  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = '', string $bundle = ''): array {
    $automator = $this->loadAutomator($entity_type, $bundle);
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Automator Prompt (Token)'),
      '#default_value' => (string) $automator->get('data')->get('prompt'),
      '#required' => TRUE,
    ];
    return $form;
  }
}
```

- [ ] **Step 4: Run the prompt form functional test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`
Expected: PASS

- [ ] **Step 5: Refactor the form for actual automator persistence and modal detection**

```php
protected function isModalRequest(): bool {
  $dialog_type = (string) $this->requestStack->getCurrentRequest()->query->get('_wrapper_format');
  return str_contains($dialog_type, 'drupal_modal');
}

public function submitForm(array &$form, FormStateInterface $form_state): void {
  $automator = $this->loadAutomator($this->entityTypeId, $this->bundle);
  $data = $automator->get('data');
  $data['prompt'] = $form_state->getValue('prompt');
  $automator->set('data', $data)->save();
  $this->messenger()->addStatus($this->t('The automator prompt has been updated.'));
}
```

- [ ] **Step 6: Run the functional test again and extend it for modal submit behavior**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`
Expected: PASS after adding assertions that modal requests close and show the status message.

### Task 2: Core widget link to edit prompt in a modal

**Files:**
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/src/Hook/AiSchemaDotOrgJsonLdFieldHooks.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`

- [ ] **Step 1: Write the failing functional test for the widget edit prompt link**

```php
public function testJsonLdWidgetHasEditPromptLink(): void {
  $this->drupalLogin($this->rootUser);
  $this->drupalGet($this->nodeEditPath);
  $this->assertSession()->linkByHrefExists('/admin/config/ai/schemadotorg-jsonld/prompt/node/page');
  $this->assertSession()->elementAttributeContains('css', 'a.use-ajax', 'data-dialog-type', 'modal');
}
```

- [ ] **Step 2: Run the targeted functional test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`
Expected: FAIL because the widget does not yet add an edit prompt link.

- [ ] **Step 3: Implement the link in the existing field widget complete form alter**

```php
$field_widget_complete_form['edit_prompt'] = [
  '#type' => 'link',
  '#title' => $this->t('Edit prompt'),
  '#url' => Url::fromRoute('ai_schemadotorg_jsonld_devel.prompt', [
    'entity_type' => $entity->getEntityTypeId(),
    'bundle' => $entity->bundle(),
  ]),
  '#attributes' => [
    'class' => ['use-ajax', 'button', 'button--extrasmall'],
    'data-dialog-type' => 'modal',
    'data-dialog-options' => Json::encode(['width' => 900]),
  ],
];
```

- [ ] **Step 4: Run the functional test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`
Expected: PASS

- [ ] **Step 5: Verify existing saved-entity and unsaved-entity widget behavior still passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`
Expected: PASS with the unsaved-entity warning still rendered.

### Task 3: Scaffold the devel module and its settings integration

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.info.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.module`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/config/install/ai_schemadotorg_jsonld_devel.settings.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/config/schema/ai_schemadotorg_jsonld_devel.schema.yml`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`

- [ ] **Step 1: Write the failing functional test for the devel settings form alter**

```php
public function testDevelopmentSettingsAreAddedToCoreForm(): void {
  $this->drupalLogin($this->rootUser);
  $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
  $this->assertSession()->pageTextContains('Development settings');
  $this->assertSession()->checkboxExists('Enable prompt and response logging');
}
```

- [ ] **Step 2: Run the devel settings functional test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`
Expected: FAIL because the devel module and form alter do not exist yet.

- [ ] **Step 3: Create the devel module info, config, and form alter**

```yaml
name: 'AI Schema.org JSON-LD Devel'
type: module
description: 'Development tools for AI Schema.org JSON-LD prompts and logs.'
core_version_requirement: ^11
package: AI
dependencies:
  - drupal:ai_schemadotorg_jsonld
```

```php
function ai_schemadotorg_jsonld_devel_form_ai_schemadotorg_jsonld_settings_alter(array &$form, FormStateInterface $form_state): void {
  $config = \Drupal::config('ai_schemadotorg_jsonld_devel.settings');
  $form['development_settings'] = [
    '#type' => 'details',
    '#title' => t('Development settings'),
    '#open' => FALSE,
  ];
  $form['development_settings']['enable'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable prompt and response logging'),
    '#default_value' => (bool) $config->get('enable'),
    '#description' => t('Store AI Schema.org JSON-LD prompt and response pairs in the development log table for debugging and prompt tuning.'),
    '#config_target' => 'ai_schemadotorg_jsonld_devel.settings:enable',
  ];
}
```

- [ ] **Step 4: Run the devel settings test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`
Expected: PASS

- [ ] **Step 5: Add a submit assertion so the test verifies the config value persists**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`
Expected: PASS with confirmation that `ai_schemadotorg_jsonld_devel.settings:enable` changes after form submit.

### Task 4: Add dedicated log table storage

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.install`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/AiSchemaDotOrgJsonLdDevelLogStorageInterface.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/AiSchemaDotOrgJsonLdDevelLogStorage.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.services.yml`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`

- [ ] **Step 1: Write the failing kernel test for inserting and loading log rows**

```php
public function testLogStorageInsertAndLoad(): void {
  $this->logStorage->insert([
    'entity_type' => 'node',
    'entity_id' => '1',
    'prompt' => 'Prompt text',
    'response' => '{"@type":"Thing"}',
  ]);
  $rows = $this->logStorage->loadAll();
  $this->assertCount(1, $rows);
  $this->assertSame('node', $rows[0]['entity_type']);
}
```

- [ ] **Step 2: Run the kernel test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`
Expected: FAIL because the table and storage service do not exist yet.

- [ ] **Step 3: Implement the schema and storage service**

```php
function ai_schemadotorg_jsonld_devel_schema(): array {
  return [
    'ai_schemadotorg_jsonld_logs' => [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'entity_type' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'entity_id' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
        'prompt' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'response' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'created' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'entity' => ['entity_type', 'entity_id'],
        'created' => ['created'],
      ],
    ],
  ];
}
```

```php
interface AiSchemaDotOrgJsonLdDevelLogStorageInterface {
  public function insert(array $values): void;
  public function loadAll(): array;
  public function truncate(): void;
}
```

- [ ] **Step 4: Run the kernel test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`
Expected: PASS

- [ ] **Step 5: Extend the kernel test for CSV rows and clear behavior**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`
Expected: PASS after assertions for returned column order and empty results after `truncate()`.

### Task 5: Log prompt and response through the devel event subscriber

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/EventSubscriber/AiSchemaDotOrgJsonLdDevelEventSubscriber.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.services.yml`

- [ ] **Step 1: Write the failing unit test for logging an AI response**

```php
public function testSubscriberStoresPromptAndResponseWhenEnabled(): void {
  $event = $this->createPostGenerateResponseEvent('Prompt text', 'Response text', ['entity_type:node', 'entity_id:99']);
  $this->subscriber->onPostGenerateResponse($event);
  $this->logStorage->expects($this->once())->method('insert');
}
```

- [ ] **Step 2: Run the unit test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`
Expected: FAIL because the subscriber does not exist yet.

- [ ] **Step 3: Implement the subscriber with config gating and tag parsing**

```php
public function onPostGenerateResponse(PostGenerateResponseEvent $event): void {
  if (!$this->configFactory->get('ai_schemadotorg_jsonld_devel.settings')->get('enable')) {
    return;
  }
  if (!$this->isJsonLdAutomatorRequest($event->getTags())) {
    return;
  }
  $this->logStorage->insert([
    'entity_type' => $this->extractTagValue($event->getTags(), 'entity_type'),
    'entity_id' => $this->extractTagValue($event->getTags(), 'entity_id'),
    'prompt' => $this->extractPrompt($event),
    'response' => $this->extractResponse($event),
  ]);
}
```

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`
Expected: PASS

- [ ] **Step 5: Add tests for disabled logging and non-JSON-LD requests**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`
Expected: PASS with no insert when the setting is off or tags do not match the JSON-LD automator field.

### Task 6: Add the logs admin page, CSV download, and clear form

**Files:**
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.routing.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/ai_schemadotorg_jsonld_devel.links.task.yml`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/Controller/AiSchemaDotOrgJsonLdDevelLogs.php`
- Create: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/src/Form/AiSchemaDotOrgJsonLdDevelClearForm.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`

- [ ] **Step 1: Write the failing functional test for the logs local task and page**

```php
public function testLogsPageShowsRowsAndOperations(): void {
  $this->seedLogRow();
  $this->drupalLogin($this->rootUser);
  $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/logs');
  $this->assertSession()->statusCodeEquals(200);
  $this->assertSession()->pageTextContains('Logs');
  $this->assertSession()->linkExists('Download CSV');
  $this->assertSession()->linkExists('Clear logs');
}
```

- [ ] **Step 2: Run the logs functional test to verify it fails**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`
Expected: FAIL because the routes and controller do not exist yet.

- [ ] **Step 3: Implement the logs routes, local task, controller, and clear form**

```yaml
ai_schemadotorg_jsonld_devel.logs:
  path: '/admin/config/ai/schemadotorg-jsonld/logs'
  defaults:
    _controller: '\Drupal\ai_schemadotorg_jsonld_devel\Controller\AiSchemaDotOrgJsonLdDevelLogs::index'
    _title: 'AI Schema.org JSON-LD'
  requirements:
    _permission: 'administer site configuration'
```

```php
public function index(): array {
  return [
    'operations' => [
      '#type' => 'operations',
      '#links' => [
        'download' => ['title' => $this->t('Download CSV'), 'url' => Url::fromRoute('ai_schemadotorg_jsonld_devel.logs.download')],
        'clear' => ['title' => $this->t('Clear logs'), 'url' => Url::fromRoute('ai_schemadotorg_jsonld_devel.logs.clear')],
      ],
    ],
    'table' => [
      '#type' => 'table',
      '#header' => [$this->t('Entity type'), $this->t('Entity id'), $this->t('Prompt'), $this->t('Response'), $this->t('Created')],
      '#rows' => $this->buildRows($this->logStorage->loadAll()),
      '#empty' => $this->t('No logs available.'),
    ],
  ];
}
```

- [ ] **Step 4: Run the logs functional test to verify it passes**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`
Expected: PASS

- [ ] **Step 5: Extend the functional test for CSV download and clear confirmation**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`
Expected: PASS with assertions for CSV headers, response content, confirmation form text, and empty table after clearing.

### Task 7: Run module-level verification and finish

**Files:**
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld/README.md`
- Modify: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/README.md`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php`
- Test: `web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`

- [ ] **Step 1: Write the README updates**

```md
## Development tools

- The core module provides an `Edit prompt` modal link from the JSON-LD field widget for saved entities.
- The optional `ai_schemadotorg_jsonld_devel` module adds prompt/response logging, a logs admin page, CSV export, and a clear action.
```

- [ ] **Step 2: Run the focused PHPUnit suite**

Run: `ddev phpunit web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdPromptFormTest.php web/modules/sandbox/ai_schemadotorg_jsonld/tests/src/Functional/AiSchemaDotOrgJsonLdSettingsFormTest.php web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelSettingsFormTest.php web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Functional/AiSchemaDotOrgJsonLdDevelLogsTest.php web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Kernel/AiSchemaDotOrgJsonLdDevelLogStorageTest.php web/modules/sandbox/ai_schemadotorg_jsonld_devel/tests/src/Unit/AiSchemaDotOrgJsonLdDevelEventSubscriberTest.php`
Expected: PASS

- [ ] **Step 3: Run code review on both modules**

Run: `ddev code-review web/modules/sandbox/ai_schemadotorg_jsonld web/modules/sandbox/ai_schemadotorg_jsonld_devel`
Expected: PASS with no PHPCS, PHPStan, cspell, ESLint, or stylelint errors.

- [ ] **Step 4: Review the diff for unexpected changes**

Run: `git diff -- web/modules/sandbox/ai_schemadotorg_jsonld web/modules/sandbox/ai_schemadotorg_jsonld_devel docs/superpowers/plans/2026-04-18-ai-schemadotorg-jsonld-devel-module.md`
Expected: Only prompt editing, devel logging, tests, docs, and the saved plan file are changed.

## Self-Review

- Spec coverage: The plan covers the core prompt route, widget modal link, devel settings injection, dedicated log table, logging subscriber, logs page, CSV download, clear form, and verification.
- Placeholder scan: No `TODO`, `TBD`, or deferred implementation placeholders remain in the task list.
- Type consistency: The plan consistently uses `AiSchemaDotOrgJsonLdPromptForm`, `AiSchemaDotOrgJsonLdDevelEventSubscriber`, `AiSchemaDotOrgJsonLdDevelLogStorage`, `ai_schemadotorg_jsonld_devel.settings`, and `ai_schemadotorg_jsonld_logs`.
