# ClinicalTrials.gov Settings Task Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional Settings task to the ClinicalTrials.gov workflow so advanced users can configure the destination content type machine name and generated field prefix before structure creation, while Configure becomes a consumer of those settings.

**Architecture:** Add a dedicated `ClinicalTrialsGovSettingsForm` that owns `type` and `field_prefix` through `#config_target`, extend config install/schema and local-task routing, then update `ClinicalTrialsGovConfigForm`, `ClinicalTrialsGovController`, and `ClinicalTrialsGovEntityManager` so Configure reads the saved settings and generated field names honor the configured prefix. Cover the behavior with functional and kernel tests, following a red-green implementation order.

**Tech Stack:** Drupal 11 config forms, routing and local tasks, Drupal field API, PHPUnit BrowserTestBase, PHPUnit KernelTestBase

---

### Task 1: Add failing tests for Settings navigation and lock behavior

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`

- [ ] **Step 1: Write the failing functional assertions for the new Settings task**

Add assertions to `testWizardFlow()` covering:

```php
    $this->assertSession()->pageTextContains('Settings');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/settings');

    $this->drupalGet('admin/config/services/clinical-trials-gov/settings');

    // Check that Settings starts with editable defaults and guidance text.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('Content type machine name', 'trial');
    $this->assertSession()->fieldValueEquals('Field prefix', 'trial');
    $this->assertSession()->pageTextContains('trial');
    $this->assertSession()->pageTextContains('study');
    $this->assertSession()->pageTextContains('nct');
    $this->assertSession()->fieldNotExists('Content type machine name', ['disabled' => TRUE]);
```

Later in the same test, after Configure creates the destination structure, add:

```php
    $this->drupalGet('admin/config/services/clinical-trials-gov/settings');

    // Check that machine-name settings lock after structure creation.
    $this->assertSession()->fieldDisabled('Content type machine name');
    $this->assertSession()->fieldDisabled('Field prefix');
```

- [ ] **Step 2: Run the functional test to verify it fails**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
Expected: FAIL because the Settings route, task, and form do not exist yet.

### Task 2: Add failing tests for Configure messaging and configurable field prefix

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`

- [ ] **Step 1: Extend the functional test with the new Configure guidance message**

In `testWizardFlow()`, before the bundle is created, add:

```php
    $this->assertSession()->pageTextContains('Review the content type and fields that will be created below.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/settings');
    $this->assertSession()->linkExists('Settings');
```

Keep the assertion on the later Configure state focused on read-only bundle behavior.

- [ ] **Step 2: Extend the kernel test with a configurable field-prefix assertion**

Add a new kernel test method:

```php
  public function testGenerateFieldNameUsesConfiguredPrefix(): void {
    $this->assertSame('field_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));

    $this->config('clinical_trials_gov.settings')
      ->set('field_prefix', 'study')
      ->save();

    $this->container->get('config.factory')->reset('clinical_trials_gov.settings');
    $this->assertSame('field_study_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));
  }
```

Also add expectations in `testCreateContentTypeAndFields()` for the system link fields when the default prefix applies:

```php
    $this->assertSame('link', FieldStorageConfig::loadByName('node', 'field_trial_nct_url')?->getType());
    $this->assertSame('link', FieldStorageConfig::loadByName('node', 'field_trial_nct_api')?->getType());
```

- [ ] **Step 3: Run the targeted kernel test to verify it fails**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
Expected: FAIL because field names still use the old prefix behavior and system link fields are not generated with the configured prefix.

### Task 3: Implement Settings route, local task, config, and form

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.routing.yml`
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.links.task.yml`
- Modify: `web/modules/custom/clinical_trials_gov/config/install/clinical_trials_gov.settings.yml`
- Modify: `web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml`
- Create: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovSettingsForm.php`

- [ ] **Step 1: Add the new route and local task**

Add to `clinical_trials_gov.routing.yml`:

```yaml
clinical_trials_gov.settings:
  path: '/admin/config/services/clinical-trials-gov/settings'
  defaults:
    _form: '\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovSettingsForm'
    _title: 'ClinicalTrials.gov'
  requirements:
    _permission: 'administer clinical_trials_gov'
```

Add to `clinical_trials_gov.links.task.yml`:

```yaml
clinical_trials_gov.settings:
  route_name: clinical_trials_gov.settings
  base_route: clinical_trials_gov.index
  title: 'Settings'
```

- [ ] **Step 2: Add the new config key and schema**

Update `config/install/clinical_trials_gov.settings.yml`:

```yaml
query: ''
paths: { }
type: trial
field_prefix: trial
fields: { }
```

Update `config/schema/clinical_trials_gov.schema.yml`:

```yaml
    field_prefix:
      type: string
      label: 'Field machine-name prefix'
```

- [ ] **Step 3: Create the Settings form with `#config_target`**

Create `src/Form/ClinicalTrialsGovSettingsForm.php` with:

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\Core\Config\Plugin\Field\ConfigTarget;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClinicalTrialsGovSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'clinical_trials_gov_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $locked = $this->isStructureLocked();

    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content type machine name'),
      '#description' => $this->t('Machine name for the destination content type. Common values include trial, study, or nct. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => new ConfigTarget('clinical_trials_gov.settings', 'type'),
      '#required' => TRUE,
      '#disabled' => $locked,
    ];
    $form['field_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field prefix'),
      '#description' => $this->t('Prefix used when generating Drupal field machine names. Common values include trial, study, or nct. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => new ConfigTarget('clinical_trials_gov.settings', 'field_prefix'),
      '#required' => TRUE,
      '#disabled' => $locked,
    ];

    return parent::buildForm($form, $form_state);
  }

  protected function isStructureLocked(): bool {
    $config = $this->config('clinical_trials_gov.settings');
    $type = (string) ($config->get('type') ?? ClinicalTrialsGovEntityManagerInterface::DEFAULT_CONTENT_TYPE);
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);
    return ($node_type !== NULL);
  }

}
```

- [ ] **Step 4: Run the functional test to verify the new Settings route and form pass**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
Expected: still FAIL because Configure and field-prefix behavior are not updated yet, but the Settings route/form assertions should now pass.

### Task 4: Update Configure and overview behavior

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovController.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovConfigForm.php`

- [ ] **Step 1: Update the overview page to advertise the optional Settings task**

Add a new admin block entry to `ClinicalTrialsGovController::index()`:

```php
          'settings' => [
            'title' => $this->t('Settings'),
            'description' => $this->t('Advanced settings for the destination content type machine name and generated field prefix.'),
            'url' => Url::fromRoute('clinical_trials_gov.settings'),
          ],
```

Also update the introduction copy so it mentions Settings alongside the existing workflow tasks.

- [ ] **Step 2: Remove Configure ownership of the type machine name**

In `ClinicalTrialsGovConfigForm::buildForm()`:

- keep reading `$saved_type` from config
- when the node type does not exist, remove the editable `type` textfield
- replace it with a read-only item:

```php
      $form['content_type']['type'] = [
        '#type' => 'item',
        '#title' => $this->t('Machine name'),
        '#markup' => $saved_type,
      ];
```

- [ ] **Step 3: Add the new Configure guidance message**

When the configured bundle does not exist, add a message render array before the content-type inputs:

```php
    if ($node_type === NULL) {
      $form['settings_message'] = [
        '#type' => 'container',
        'message' => [
          '#markup' => (string) $this->t('Review the content type and fields that will be created below. Go to <a href=":url">Settings</a> to change the machine names and field prefix.', [
            ':url' => Url::fromRoute('clinical_trials_gov.settings')->toString(),
          ]),
        ],
      ];
    }
```

Place this above the `content_type` details element.

- [ ] **Step 4: Run the functional test to verify Configure now reads from Settings**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
Expected: still FAIL because field-prefix generation and lock behavior are not fully updated yet, but the new Configure messaging assertions should pass.

### Task 5: Make generated field names honor `field_prefix`

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php`

- [ ] **Step 1: Inject config factory and centralize the prefix lookup**

Update the constructor and imports:

```php
use Drupal\Core\Config\ConfigFactoryInterface;
```

```php
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}
```

Add a helper:

```php
  protected function getFieldPrefix(): string {
    $prefix = (string) ($this->configFactory->get('clinical_trials_gov.settings')->get('field_prefix') ?? ClinicalTrialsGovEntityManagerInterface::DEFAULT_CONTENT_TYPE);
    return ($prefix !== '') ? $prefix : ClinicalTrialsGovEntityManagerInterface::DEFAULT_CONTENT_TYPE;
  }
```

- [ ] **Step 2: Update generated field names to prepend the configured prefix**

Update `generateFieldName()`:

```php
  public function generateFieldName(string $path): string {
    $resolved_field_name = $this->fieldManager->resolveFieldDefinition($path)['field_name'];
    $suffix = preg_replace('/^field_/', '', $resolved_field_name) ?? $resolved_field_name;
    return $this->buildFieldNameFromSuffix($suffix);
  }
```

Add:

```php
  protected function buildFieldNameFromSuffix(string $suffix): string {
    $candidate = 'field_' . $this->getFieldPrefix() . '_' . $suffix;
    if (strlen($candidate) <= 32) {
      return $candidate;
    }

    $hash = substr(hash('sha256', $candidate), 0, 6);
    $prefix = 'field_' . $this->getFieldPrefix() . '_';
    $available_length = 32 - strlen($prefix) - 1 - strlen($hash);
    $trimmed_suffix = substr($suffix, 0, max(1, $available_length));
    return $prefix . $trimmed_suffix . '_' . $hash;
  }
```

- [ ] **Step 3: Update system link field definitions to use the configured prefix**

Replace the static `SYSTEM_LINK_FIELDS` constant with a method that derives:

```php
  protected function getSystemLinkFieldDefinitions(): array {
    return [
      $this->buildFieldNameFromSuffix('nct_url') => [
        'field_name' => $this->buildFieldNameFromSuffix('nct_url'),
        'label' => 'ClinicalTrials.gov URL',
        'description' => 'Canonical ClinicalTrials.gov study URL.',
        'field_type' => 'link',
        'storage_settings' => [],
        'instance_settings' => [
          'link_type' => LinkItemInterface::LINK_GENERIC,
          'title' => DRUPAL_DISABLED,
        ],
        'cardinality' => 1,
      ],
      $this->buildFieldNameFromSuffix('nct_api') => [
        'field_name' => $this->buildFieldNameFromSuffix('nct_api'),
        'label' => 'ClinicalTrials.gov API',
        'description' => 'ClinicalTrials.gov API endpoint for the imported study.',
        'field_type' => 'link',
        'storage_settings' => [],
        'instance_settings' => [
          'link_type' => LinkItemInterface::LINK_GENERIC,
          'title' => DRUPAL_DISABLED,
        ],
        'cardinality' => 1,
      ],
    ];
  }
```

- [ ] **Step 4: Run the kernel test to verify field-prefix generation passes**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
Expected: PASS for the field-prefix and system-link assertions, or fail only where the existing tests still need expectation updates.

### Task 6: Align tests and final verification

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`

- [ ] **Step 1: Update any remaining test expectations to the prefixed field names**

Adjust expectations such as:

```php
    $this->assertSame('field_trial_nct_id', $alias_name);
```

and:

```php
    $this->assertSame('string_textfield', $form_display->getComponent('field_trial_brief_title')['type'] ?? NULL);
```

Keep the updated expectations consistent with the final naming behavior chosen in `ClinicalTrialsGovEntityManager`.

- [ ] **Step 2: Run the targeted functional and kernel test suite**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
Expected: PASS with 0 failures.

- [ ] **Step 3: Run code review for the touched module files**

Run: `ddev code-review web/modules/custom/clinical_trials_gov`
Expected: exit 0 with no remaining errors for the changed files.
