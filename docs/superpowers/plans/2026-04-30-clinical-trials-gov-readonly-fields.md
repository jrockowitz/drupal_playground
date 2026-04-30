# ClinicalTrials.gov Readonly Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional readonly mode for imported ClinicalTrials.gov fields so mapped fields render with `readonly_field_widget`, the core node title input is hidden, and the generated `briefTitle` field remains visible as readonly.

**Architecture:** Extend `clinical_trials_gov.settings` and `ClinicalTrialsGovSettingsForm` with a `readonly` toggle that is only visible when `readonly_field_widget` is enabled. Implement OOP hooks in `ClinicalTrialsGovHooks` to switch mapped field widgets to `readonly_field_widget` via `hook_entity_form_display_alter()` and hide the node title element through a node-form alter scoped to the configured ClinicalTrials.gov bundle and title mapping. Cover the behavior with functional and kernel tests, then update README and AGENTS docs.

**Tech Stack:** Drupal 11 config forms, OOP hooks with `#[Hook]`, field widget config, BrowserTestBase, KernelTestBase, readonly_field_widget

---

### Task 1: Add failing tests for readonly settings and editor behavior

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`

- [ ] **Step 1: Extend the functional wizard test for the Settings toggle**

Add `readonly_field_widget` to `ClinicalTrialsGovTest::$modules`, then add assertions in `testWizardFlow()` after loading the Settings page:

```php
    $this->assertSession()->checkboxNotChecked('Read-only imported fields');
```

Later in the test, before saving Settings:

```php
    $this->getSession()->getPage()->checkField('Read-only imported fields');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertTrue((bool) $this->container->get('config.factory')->get('clinical_trials_gov.settings')->get('readonly'));
```

- [ ] **Step 2: Extend the functional wizard test for node edit readonly behavior**

After Configure has created the type and fields, create a trial node in the test and visit its edit form:

```php
    $node = \Drupal\node\Entity\Node::create([
      'type' => 'trial',
      'title' => 'Editable title',
      'field_trial_brief_title' => 'Readonly brief title',
      'field_trial_nct_id' => 'NCT05088187',
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
```

Assert:

```php
    $this->assertSession()->fieldNotExists('Title');
    $this->assertSession()->pageTextContains('Readonly brief title');
```

- [ ] **Step 3: Run the functional test to verify it fails**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
Expected: FAIL because the Settings form has no readonly checkbox and the node edit form still shows the core title field.

### Task 2: Add failing kernel coverage for form-display alteration

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovReadonlyHooksTest.php`

- [ ] **Step 1: Create a kernel test for readonly widget switching**

Create `ClinicalTrialsGovReadonlyHooksTest.php` with modules:

```php
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'readonly_field_widget',
    'node',
    'field',
    'text',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'json_field',
    'custom_field',
    'field_group',
  ];
```

Add a test that:

- creates the `trial` content type and generated fields
- saves `clinical_trials_gov.settings:fields` and `readonly`
- loads the `entity_form_display` for `node.trial.default`
- invokes the relevant form display builder path by getting the edit form display entity
- asserts the mapped field component widget type changes to `readonly_field_widget` only when readonly is enabled

- [ ] **Step 2: Add kernel coverage for title-hiding preconditions**

In the same test class, save mappings including:

```php
'field_trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
```

and assert the helper path that decides title hiding is active only in that case.

- [ ] **Step 3: Run the kernel test to verify it fails**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovReadonlyHooksTest.php`
Expected: FAIL because no hook logic exists yet to switch widgets or hide the title.

### Task 3: Add readonly config and Settings form support

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/config/install/clinical_trials_gov.settings.yml`
- Modify: `web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml`
- Modify: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovSettingsForm.php`

- [ ] **Step 1: Add the new config key and schema**

Update install config:

```yaml
readonly: false
```

Update schema:

```yaml
    readonly:
      type: boolean
      label: 'Read-only imported fields'
```

- [ ] **Step 2: Add the readonly checkbox to Settings**

Inject `ModuleHandlerInterface` into `ClinicalTrialsGovSettingsForm`, then add:

```php
    $form['readonly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read-only imported fields'),
      '#description' => $this->t('Display imported ClinicalTrials.gov fields as readonly on edit forms and hide the editable node title when the generated title mapping is present.'),
      '#config_target' => 'clinical_trials_gov.settings:readonly',
      '#access' => $this->moduleHandler->moduleExists('readonly_field_widget'),
    ];
```

Do not tie this checkbox to the machine-name lock.

- [ ] **Step 3: Run the functional test to verify the Settings toggle passes**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
Expected: still FAIL on edit-form readonly behavior, but the Settings checkbox assertions should pass.

### Task 4: Implement OOP hook logic for readonly widgets and hidden title

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Hook/ClinicalTrialsGovHooks.php`

- [ ] **Step 1: Inject the services needed by hooks**

Update `ClinicalTrialsGovHooks` to inject:

```php
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
```

with constructor:

```php
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}
```

- [ ] **Step 2: Add `hook_entity_form_display_alter()`**

Add:

```php
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    if (($context['entity_type'] ?? '') !== 'node') {
      return;
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    if (!$settings->get('readonly') || !$this->moduleHandler->moduleExists('readonly_field_widget')) {
      return;
    }

    $type = (string) ($settings->get('type') ?? '');
    if (($context['bundle'] ?? '') !== $type) {
      return;
    }

    foreach (array_keys(array_filter($settings->get('fields') ?? [], 'is_string')) as $field_name) {
      $component = $form_display->getComponent($field_name);
      if ($component === NULL) {
        continue;
      }
      $component['type'] = 'readonly_field_widget';
      $component['settings'] = [
        'label' => 'above',
        'formatter_type' => 'string',
        'formatter_settings' => [],
        'show_description' => FALSE,
        'error_validation' => TRUE,
      ];
      $form_display->setComponent($field_name, $component);
    }
  }
```

Adjust `formatter_type` per field type only as needed to make the tests pass; keep the first implementation minimal.

- [ ] **Step 3: Add `hook_form_node_form_alter()` to hide the title**

Add:

```php
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    if (!$settings->get('readonly') || !$this->moduleHandler->moduleExists('readonly_field_widget')) {
      return;
    }

    if ($entity->bundle() !== (string) ($settings->get('type') ?? '')) {
      return;
    }

    $mapped_paths = array_values(array_filter($settings->get('fields') ?? [], 'is_string'));
    if (!in_array('protocolSection.identificationModule.briefTitle', $mapped_paths, TRUE)) {
      return;
    }

    if (isset($form['title'])) {
      $form['title']['#access'] = FALSE;
    }
  }
```

- [ ] **Step 4: Run the focused tests to verify readonly behavior passes**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovReadonlyHooksTest.php`
Expected: PASS or only fail on mismatched formatter settings that need tightening.

### Task 5: Update docs and final expectations

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/README.md`
- Modify: `web/modules/custom/clinical_trials_gov/AGENTS.md`

- [ ] **Step 1: Update README.md**

Add short documentation covering:

- the optional readonly mode
- that it requires `readonly_field_widget`
- that it is toggled from Settings
- that it hides the editable node title while leaving the generated `briefTitle` field visible as readonly

- [ ] **Step 2: Update AGENTS.md**

Update the configuration and editing guidance sections to include:

- `clinical_trials_gov.settings:readonly`
- readonly integration conditions
- mapped fields only
- hidden node title + readonly `briefTitle`

- [ ] **Step 3: Run the full targeted verification set**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovReadonlyHooksTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
Expected: PASS with 0 failures.

Run: `ddev exec vendor/bin/phpcs web/modules/custom/clinical_trials_gov/src/Hook/ClinicalTrialsGovHooks.php web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovSettingsForm.php web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovReadonlyHooksTest.php web/modules/custom/clinical_trials_gov/README.md web/modules/custom/clinical_trials_gov/AGENTS.md`
Expected: exit 0 for the changed PHP files.
