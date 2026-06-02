# ClinicalTrials.gov Import Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a four-step import wizard (Find → Review → Configure → Import) inside the `clinical_trials_gov` module that lets administrators query ClinicalTrials.gov, configure a destination content type, and run a full-sync Drupal migration.

**Architecture:** Separate-route wizard; all state in `clinical_trials_gov.settings` config (`query`, `type`, `fields`). Two new services — `ClinicalTrialsGovEntityManager` (manages node type, field types, and deterministic field-name generation) and `ClinicalTrialsGovMigrationManager` (builds the `migrate_plus` migration config entity) — own all business logic. A custom `ClinicalTrialsGovSource` migrate source plugin feeds paginated API results to the migration.

**Tech Stack:** Drupal 10/11, Migrate (core), `migrate_tools` (MigrateBatchExecutable), `migrate_plus` (config-entity migrations), ClinicalTrials.gov API v2.

---

## File Map

**New files to create:**
- `web/modules/custom/clinical_trials_gov/clinical_trials_gov.permissions.yml`
- `web/modules/custom/clinical_trials_gov/clinical_trials_gov.routing.yml`
- `web/modules/custom/clinical_trials_gov/clinical_trials_gov.links.menu.yml`
- `web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml`
- `web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovController.php`
- `web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovReviewController.php`
- `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovFindForm.php`
- `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovConfigForm.php`
- `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovImportForm.php`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManagerInterface.php`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManagerInterface.php`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManager.php`
- `web/modules/custom/clinical_trials_gov/src/Plugin/migrate/source/ClinicalTrialsGovSource.php`
- `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
- `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php`
- `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php`
- `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`

**Files to modify:**
- `web/modules/custom/clinical_trials_gov/clinical_trials_gov.info.yml` — add `migrate`, `migrate_tools`, `migrate_plus`, `custom_field`, and `json_field` dependencies
- `web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml` — register two new services
- `web/modules/custom/clinical_trials_gov/src/Element/ClinicalTrialsGovStudiesQuery.php` — add `#excluded_fields` support
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilder.php` — add `buildStudiesList()`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilderInterface.php` — add `buildStudiesList()` signature
- `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovReportStudiesController.php` — delegate to `ClinicalTrialsGovBuilder::buildStudiesList()`

---

## Field Rules

- Required fields:
  - NCT Number — selected by default, disabled, stored as a custom field
  - Title — selected by default, disabled, mapped to node `title`
  - Description — selected by default, disabled, stored as a custom field
- Existing fields on the destination content type are disabled and cannot be unselected.
- Drupal field machine names must be deterministic and fit within 32 characters. Long names are truncated and suffixed with a stable hash.
- Field creation must resolve a Drupal field type from the ClinicalTrials.gov metadata row before creating storage/config entities.

### Field type mapping

| ClinicalTrials.gov metadata | Drupal destination |
|---|---|
| `TEXT` / enum / identifier with `maxChars <= 255` | `string` |
| `TEXT` / markup / long text with `maxChars > 255` or unknown long content | `text_long` |
| `BOOLEAN` | `boolean` |
| `NUMERIC` integer | `integer` |
| `DATE` with normalized full date or datetime value | `datetime` |
| `DATE` with `PartialDate`, or `STRUCT` with `PartialDateStruct` | `json` via `json_field` |
| Whitelisted `STRUCT` / `STRUCT[]` values with explicit sub-columns | `custom` via `custom_field` |

### Cardinality and enum rules

- API metadata `type` defines cardinality:
  - `foo` => single-value field
  - `foo[]` => multi-value field
- `isEnum: true` rows use Drupal `list_string` fields, not plain text fields
- Enum allowed values come from `ClinicalTrialsGovManager::getEnums()`
- Step 3 should show field type, cardinality, and enum status for every selectable row

### Canonical mapping rules

| API pattern | Drupal field | Cardinality | Notes |
|---|---|---|---|
| `protocolSection.identificationModule.briefTitle` | node `title` | 1 | Required |
| `protocolSection.identificationModule.nctId` | `string` | 1 | Required |
| `protocolSection.descriptionModule.briefSummary` | `text_long` | 1 | Required |
| Enum without `[]` | `list_string` | 1 | Allowed values from enum definition |
| Enum with `[]` | `list_string` | Unlimited | Allowed values from enum definition |
| Text-like scalar without `[]` | `string` or `text_long` | 1 | Based on length/content |
| Text-like type with `[]` | `string` or `text_long` | Unlimited | Multi-value |
| Boolean scalar | `boolean` | 1 | Normalize source value |
| Boolean with `[]` | `boolean` | Unlimited | Normalize each item |
| Integer scalar | `integer` | 1 | Normalize source value |
| Integer with `[]` | `integer` | Unlimited | Normalize each item |
| `PartialDate` / `PartialDateStruct` | `json` via `json_field` | Based on `[]` | Preserve structured partial date |
| Whitelisted `STRUCT` / `STRUCT[]` | `custom` via `custom_field` | Based on `[]` | Use explicit per-structure mapping |
| Unsupported container / struct pattern | not selectable | n/a | Disabled in Step 3 |

### Structured field whitelist

Supported in Phase 2:

- `Organization`
- `ExpandedAccessInfo`
- `EnrollmentInfo`
- `Contact[]`
- `Official[]`
- `Reference[]`
- `SeeAlsoLink[]`
- `AvailIpd[]`

Always handled by `json_field`, not `custom_field`:

- `PartialDate`
- `PartialDateStruct`

Explicitly unsupported in Phase 2:

- `resultsSection.*`
- `derivedSection.*`
- `documentSection.*`
- `annotationSection.*`
- `ArmGroup[]`
- `Intervention[]`
- `Outcome[]`
- `Location[]`
- `SecondaryIdInfo[]`
- `Sponsor[]`

---

## Task 1: Add `#excluded_fields` to ClinicalTrialsGovStudiesQuery

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Element/ClinicalTrialsGovStudiesQuery.php`

- [ ] **Step 1: Add `#excluded_fields` to `getInfo()`**

In `ClinicalTrialsGovStudiesQuery::getInfo()`, add the property so the element accepts an exclusion list:

```php
public function getInfo(): array {
  return [
    '#input' => TRUE,
    '#default_value' => '',
    '#excluded_fields' => [],
    '#process' => [[static::class, 'processStudiesQuery']],
    '#element_validate' => [[static::class, 'validateStudiesQuery']],
    '#theme_wrappers' => ['form_element'],
  ];
}
```

- [ ] **Step 2: Filter excluded fields in `processStudiesQuery()`**

In `processStudiesQuery()`, skip any definition whose `key` is in `$element['#excluded_fields']`:

```php
public static function processStudiesQuery(
  array $element,
  FormStateInterface $form_state,
  array &$complete_form,
): array {
  $defaults = static::parseQueryString($element['#default_value'] ?? '');
  $manager = \Drupal::service('clinical_trials_gov.manager');
  $field_definitions = static::fieldDefinitions($manager->getEnum('Status'));
  $excluded = $element['#excluded_fields'] ?? [];

  $element['#attached']['library'][] = 'clinical_trials_gov/studies_query';

  foreach ($field_definitions as $definition) {
    if (in_array($definition['key'], $excluded)) {
      continue;
    }
    $name = static::apiKeyToElementName($definition['key']);
    $element[$name] = static::buildFieldElement(
      $definition,
      $defaults[$definition['key']] ?? '',
    );
  }

  return $element;
}
```

- [ ] **Step 3: Verify the element test still passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovStudiesQueryTest.php
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Element/ClinicalTrialsGovStudiesQuery.php
git commit -m "feat: add #excluded_fields support to ClinicalTrialsGovStudiesQuery element"
```

---

## Task 2: Extract `buildStudiesList` into ClinicalTrialsGovBuilder

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilderInterface.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilder.php`
- Modify: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovReportStudiesController.php`

- [ ] **Step 1: Add `buildStudiesList()` to `ClinicalTrialsGovBuilderInterface`**

```php
/**
 * Builds a studies results table render array.
 *
 * @param array $studies
 *   Raw studies array from ClinicalTrialsGovManager::getStudies()['studies'].
 * @param string|null $study_route
 *   Route name for study detail links. NULL renders NCT IDs as plain text.
 */
public function buildStudiesList(array $studies, ?string $study_route = NULL): array;
```

- [ ] **Step 2: Implement `buildStudiesList()` in `ClinicalTrialsGovBuilder`**

Add the method to `ClinicalTrialsGovBuilder`. The logic is extracted from `ClinicalTrialsGovReportStudiesController::buildResultsTable()`:

```php
public function buildStudiesList(array $studies, ?string $study_route = NULL): array {
  $rows = [];
  foreach ($studies as $study) {
    $identification = $study['protocolSection']['identificationModule'] ?? [];
    $status_module = $study['protocolSection']['statusModule'] ?? [];
    $design_module = $study['protocolSection']['designModule'] ?? [];
    $conditions_module = $study['protocolSection']['conditionsModule'] ?? [];

    $nct_id = $identification['nctId'] ?? '';
    $title = $identification['briefTitle'] ?? '';
    $status = $status_module['overallStatus'] ?? '';
    $phases = implode(', ', $design_module['phases'] ?? []);
    $conditions = implode(', ', $conditions_module['conditions'] ?? []);

    if ($nct_id !== '' && $study_route !== NULL) {
      $nct_cell = $this->t('<a href=":url">@nct</a>', [
        ':url' => Url::fromRoute($study_route, ['nctId' => $nct_id])->toString(),
        '@nct' => $nct_id,
      ]);
    }
    else {
      $nct_cell = $nct_id;
    }

    $rows[] = [$nct_cell, $title, $status, $phases, $conditions];
  }

  return [
    '#type' => 'table',
    '#header' => [
      $this->t('NCT ID'),
      $this->t('Title'),
      $this->t('Overall status'),
      $this->t('Phases'),
      $this->t('Conditions'),
    ],
    '#rows' => $rows,
  ];
}
```

Add `use Drupal\Core\Url;` to `ClinicalTrialsGovBuilder.php`.

- [ ] **Step 3: Update `ClinicalTrialsGovReportStudiesController` to delegate**

Replace `$build['results'] = $this->buildResultsTable($studies);` with:

```php
$build['results'] = $this->builder->buildStudiesList($studies, 'clinical_trials_gov_report.study');
```

Add `ClinicalTrialsGovBuilderInterface $builder` as a constructor argument (after `$manager`):

```php
public function __construct(
  protected ClinicalTrialsGovManagerInterface $manager,
  protected ClinicalTrialsGovBuilderInterface $builder,
  protected DateFormatterInterface $dateFormatter,
) {}

public static function create(ContainerInterface $container): static {
  return new static(
    $container->get('clinical_trials_gov.manager'),
    $container->get('clinical_trials_gov.builder'),
    $container->get('date.formatter'),
  );
}
```

Add `use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;` to the controller.

Remove the now-unused `buildResultsTable()` method from the controller.

- [ ] **Step 4: Verify report tests still pass**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/tests/
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilderInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilder.php \
        web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovReportStudiesController.php
git commit -m "refactor: extract buildStudiesList() into ClinicalTrialsGovBuilder"
```

---

## Task 3: Module Infrastructure

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.info.yml`
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml`
- Create: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.permissions.yml`
- Create: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.routing.yml`
- Create: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.links.menu.yml`
- Create: `web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml`

- [ ] **Step 1: Update `clinical_trials_gov.info.yml`**

```yaml
name: "ClinicalTrials.gov"
type: module
description: "Services and components for the ClinicalTrials.gov API v2 integration."
package: ClinicalTrials.gov
core_version_requirement: ^10.3 || ^11
dependencies:
  - drupal:migrate
  - migrate_tools:migrate_tools
  - migrate_plus:migrate_plus
  - custom_field:custom_field
  - json_field:json_field
```

- [ ] **Step 2: Add new services to `clinical_trials_gov.services.yml`**

Append to the existing file:

```yaml
  clinical_trials_gov.entity_manager:
    class: Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManager
    autowire: true
  Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface: "@clinical_trials_gov.entity_manager"

  clinical_trials_gov.migration_manager:
    class: Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManager
    autowire: true
  Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface: "@clinical_trials_gov.migration_manager"
```

- [ ] **Step 3: Create `clinical_trials_gov.permissions.yml`**

```yaml
administer clinical_trials_gov:
  title: 'Administer ClinicalTrials.gov'
  description: 'Configure and run the ClinicalTrials.gov import wizard.'
  restrict access: true
```

- [ ] **Step 4: Create `clinical_trials_gov.routing.yml`**

```yaml
clinical_trials_gov.index:
  path: '/admin/config/services/clinical-trials-gov'
  defaults:
    _controller: '\Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovController::index'
    _title: 'ClinicalTrials.gov'
  requirements:
    _permission: 'administer clinical_trials_gov'

clinical_trials_gov.find:
  path: '/admin/config/services/clinical-trials-gov/find'
  defaults:
    _form: '\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovFindForm'
    _title: 'Find'
  requirements:
    _permission: 'administer clinical_trials_gov'

clinical_trials_gov.review:
  path: '/admin/config/services/clinical-trials-gov/review'
  defaults:
    _controller: '\Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovReviewController::index'
    _title: 'Review'
  requirements:
    _permission: 'administer clinical_trials_gov'

clinical_trials_gov.configure:
  path: '/admin/config/services/clinical-trials-gov/configure'
  defaults:
    _form: '\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovConfigForm'
    _title: 'Configure'
  requirements:
    _permission: 'administer clinical_trials_gov'

clinical_trials_gov.import:
  path: '/admin/config/services/clinical-trials-gov/import'
  defaults:
    _form: '\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovImportForm'
    _title: 'Import'
  requirements:
    _permission: 'administer clinical_trials_gov'
```

- [ ] **Step 5: Create `clinical_trials_gov.links.menu.yml`**

```yaml
clinical_trials_gov.index:
  title: 'ClinicalTrials.gov'
  description: 'Configure and run the ClinicalTrials.gov import wizard.'
  route_name: clinical_trials_gov.index
  parent: system.admin_config_services
  weight: 10
```

- [ ] **Step 6: Create `config/schema/clinical_trials_gov.schema.yml`**

```yaml
clinical_trials_gov.settings:
  type: config_object
  label: 'ClinicalTrials.gov settings'
  mapping:
    query:
      type: string
      label: 'Query string'
    type:
      type: string
      label: 'Content type machine name'
    fields:
      type: sequence
      label: 'Selected field keys'
      sequence:
        type: string
        label: 'Field key'
```

- [ ] **Step 7: Verify the site still loads**

```bash
ddev drush cr && ddev drush status
```

Expected: no errors, cache rebuilt.

- [ ] **Step 8: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/clinical_trials_gov.info.yml \
        web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml \
        web/modules/custom/clinical_trials_gov/clinical_trials_gov.permissions.yml \
        web/modules/custom/clinical_trials_gov/clinical_trials_gov.routing.yml \
        web/modules/custom/clinical_trials_gov/clinical_trials_gov.links.menu.yml \
        web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml
git commit -m "feat: add routing, permissions, config schema, and service registrations for import wizard"
```

---

## Task 4: Index Controller

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovController.php`

- [ ] **Step 1: Create `ClinicalTrialsGovController`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Renders the ClinicalTrials.gov import wizard index page.
 */
class ClinicalTrialsGovController extends ControllerBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Renders the wizard index page listing all four steps.
   */
  public function index(): array {
    $config = $this->configFactory->get('clinical_trials_gov.settings');
    $query = $config->get('query') ?? '';
    $type = $config->get('type') ?? '';
    $fields = $config->get('fields') ?? [];
    $import_ready = ($query !== '' && $type !== '' && !empty($fields));

    $steps = [
      [
        'number' => 1,
        'keyword' => $this->t('Find'),
        'description' => $this->t('Save the search query into configuration.'),
        'route' => 'clinical_trials_gov.find',
        'enabled' => TRUE,
      ],
      [
        'number' => 2,
        'keyword' => $this->t('Review'),
        'description' => $this->t('Review the trials returned by the saved query.'),
        'route' => 'clinical_trials_gov.review',
        'enabled' => ($query !== ''),
      ],
      [
        'number' => 3,
        'keyword' => $this->t('Configure'),
        'description' => $this->t('Create the trial content type and configure field mappings.'),
        'route' => 'clinical_trials_gov.configure',
        'enabled' => TRUE,
      ],
      [
        'number' => 4,
        'keyword' => $this->t('Import'),
        'description' => $this->t('Review the import summary and run the migration.'),
        'route' => 'clinical_trials_gov.import',
        'enabled' => $import_ready,
      ],
    ];

    $rows = [];
    foreach ($steps as $step) {
      $link = $step['enabled']
        ? $this->t('<a href=":url">@keyword</a>', [
          ':url' => Url::fromRoute($step['route'])->toString(),
          '@keyword' => $step['keyword'],
        ])
        : $step['keyword'];

      $rows[] = [
        $step['number'],
        $link,
        $step['description'],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Step'),
        $this->t('Action'),
        $this->t('Description'),
      ],
      '#rows' => $rows,
    ];
  }

}
```

- [ ] **Step 2: Verify the page loads**

```bash
ddev drush cr
```

Visit `https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov` as admin and confirm the four-step table renders.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovController.php
git commit -m "feat: add ClinicalTrialsGovController index page for import wizard"
```

---

## Task 4.5: Field Type and Field Name Resolution

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManagerInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovConfigForm.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`

- [ ] **Step 1: Add deterministic field-name generation**

Implement a helper in `ClinicalTrialsGovEntityManager` that:

- normalizes a metadata key to a Drupal-safe field machine name
- prefixes with `field_`
- truncates names that exceed 32 characters
- appends a stable hash suffix when truncation is required

- [ ] **Step 2: Add field-type resolution from metadata**

Implement a resolver that maps metadata rows to Drupal field types:

- Title metadata key maps to node `title`
- enum rows resolve to `list_string` with allowed values from `getEnums()`
- `TEXT` / identifier values resolve to `string` or `text_long`
- `BOOLEAN` resolves to `boolean`
- `NUMERIC` resolves to `integer`
- normalized full dates resolve to `datetime`
- `PartialDate` and `PartialDateStruct` resolve to `json`
- whitelisted `STRUCT` / `STRUCT[]` values resolve to `custom` field definitions
- `type` values ending in `[]` resolve to unlimited cardinality

- [ ] **Step 3: Surface field type information in Step 3**

Update `ClinicalTrialsGovConfigForm` so each row shows the resolved Drupal field type, cardinality, and enum status, keeps required/existing fields disabled, and disables unsupported `STRUCT` patterns with an explanatory note.

- [ ] **Step 4: Verify the resolver in kernel tests**

Add coverage for:

- generated field names shorter than 32 characters
- generated field names longer than 32 characters
- stable output for the same API key
- field type resolution for text, enum, boolean, integer, normalized date, partial date, and struct rows
- cardinality resolution for scalar and `[]` metadata types
- allowed-value extraction for enum rows
- whitelist handling for supported and unsupported structure keys

- [ ] **Step 5: Add migration mapping coverage**

Extend migration manager tests to verify:

- title maps to node `title`
- enum fields map to `list_string` destinations with allowed values configured at field creation time
- multi-value source rows stay multi-value in process mappings
- partial dates map to `json_field` destinations
- whitelisted `STRUCT` / `STRUCT[]` rows map to `custom` field destinations with generated column definitions

---

## Task 5: Find Form

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovFindForm.php`

- [ ] **Step 1: Create `ClinicalTrialsGovFindForm`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Step 1 of the import wizard: saves the search query to configuration.
 */
class ClinicalTrialsGovFindForm extends ConfigFormBase {

  public function __construct(
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_find_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['clinical_trials_gov.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('clinical_trials_gov.settings');

    $form['query'] = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#title' => $this->t('Search query'),
      '#default_value' => $config->get('query') ?? '',
      '#excluded_fields' => ['pageSize', 'pageToken', 'fields', 'sort'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('clinical_trials_gov.settings')
      ->set('query', $form_state->getValue('query'))
      ->save();

    $this->migrationManager->updateMigration();

    $form_state->setRedirect('clinical_trials_gov.review');
  }

}
```

- [ ] **Step 2: Verify the form loads**

Visit `https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/find` and confirm the query builder element renders.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovFindForm.php
git commit -m "feat: add ClinicalTrialsGovFindForm (Step 1 of import wizard)"
```

---

## Task 6: Review Controller

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovReviewController.php`

- [ ] **Step 1: Create `ClinicalTrialsGovReviewController`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Step 2 of the import wizard: displays a paginated review of the saved query.
 */
class ClinicalTrialsGovReviewController extends ControllerBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
  ) {}

  /**
   * Renders the review page.
   */
  public function index(): array {
    $config = $this->configFactory->get('clinical_trials_gov.settings');
    $query = $config->get('query') ?? '';

    if ($query === '') {
      return [
        '#markup' => $this->t('No query saved. <a href=":url">Go to Find</a> to save a query first.', [
          ':url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
        ]),
      ];
    }

    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
    $parameters['countTotal'] = 'true';

    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];
    $total = $response['totalCount'] ?? NULL;

    $build = [
      '#type' => 'container',
    ];

    if ($total !== NULL) {
      $build['summary'] = [
        '#type' => 'item',
        '#markup' => $this->t('@total trials match the saved query.', ['@total' => $total]),
      ];
    }

    $build['results'] = $this->builder->buildStudiesList(
      $studies,
      \Drupal::moduleHandler()->moduleExists('clinical_trials_gov_report')
        ? 'clinical_trials_gov_report.study'
        : NULL,
    );

    if (isset($response['nextPageToken'])) {
      $next_parameters = $parameters;
      $next_parameters['pageToken'] = $response['nextPageToken'];
      $build['pager'] = [
        '#type' => 'item',
        '#markup' => $this->t('<a href=":url" class="button">Next page &#8250;</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov.review', [], ['query' => $next_parameters])->toString(),
        ]),
      ];
    }

    $build['continue'] = [
      '#type' => 'item',
      '#markup' => $this->t('<a href=":url" class="button button--primary">Continue to Configure &#8250;</a>', [
        ':url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
      ]),
    ];

    return $build;
  }

}
```

- [ ] **Step 2: Verify the review page loads**

Save a query via Find, then visit `/admin/config/services/clinical-trials-gov/review` and confirm the results table and continue link render.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Controller/ClinicalTrialsGovReviewController.php
git commit -m "feat: add ClinicalTrialsGovReviewController (Step 2 of import wizard)"
```

---

## Task 7: ClinicalTrialsGovEntityManager (TDD)

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManagerInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`

- [ ] **Step 1: Write the failing kernel test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovEntityManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovEntityManagerTest extends KernelTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'field',
    'node',
    'text',
    'user',
    'system',
  ];

  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node']);
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
  }

  public function testEntityManager(): void {
    // Check that contentTypeExists() returns FALSE before creation.
    $this->assertFalse($this->entityManager->contentTypeExists('trial'));

    // Check that createContentType() creates the node type.
    $this->entityManager->createContentType('trial', 'Trial', 'A clinical trial.');
    $node_type = NodeType::load('trial');
    $this->assertNotNull($node_type);
    $this->assertSame('Trial', $node_type->label());
    $this->assertTrue($this->entityManager->contentTypeExists('trial'));

    // Check that createContentType() is idempotent (no error on re-call).
    $this->entityManager->createContentType('trial', 'Trial', 'A clinical trial.');

    // Check that getFieldMachineName() produces a consistent machine name.
    $field_name = $this->entityManager->getFieldMachineName('protocolSection.identificationModule.nctId');
    $this->assertSame('ct_nct_id', $field_name);
    $this->assertLessThanOrEqual(32, strlen($field_name));

    // Check that createFields() creates field storage and field config.
    $this->entityManager->createFields('trial', ['protocolSection.identificationModule.nctId']);
    $storage = FieldStorageConfig::loadByName('node', 'ct_nct_id');
    $this->assertNotNull($storage);
    $config = FieldConfig::loadByName('node', 'trial', 'ct_nct_id');
    $this->assertNotNull($config);
    $this->assertSame('string', $storage->getType());

    // Check that createFields() is idempotent (no error when field exists).
    $this->entityManager->createFields('trial', ['protocolSection.identificationModule.nctId']);
  }

}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
```

Expected: FAIL — `ClinicalTrialsGovEntityManager` class not found.

- [ ] **Step 3: Create `ClinicalTrialsGovEntityManagerInterface`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Manages the content type and field entities for the import wizard.
 */
interface ClinicalTrialsGovEntityManagerInterface {

  /**
   * Returns TRUE if the given node type machine name already exists.
   */
  public function contentTypeExists(string $type): bool;

  /**
   * Creates the node type if it does not already exist.
   */
  public function createContentType(string $type, string $label, string $description): void;

  /**
   * Creates field storage and field config for each API field key, skipping existing fields.
   *
   * @param string $type
   *   The node type machine name.
   * @param array $fields
   *   API field keys in dot-notation (e.g. 'protocolSection.identificationModule.nctId').
   */
  public function createFields(string $type, array $fields): void;

  /**
   * Returns the Drupal field machine name for a given API field key.
   *
   * Converts the last segment of the dot-notation key from camelCase to
   * snake_case and prefixes with 'ct_'. Truncates to 32 characters.
   */
  public function getFieldMachineName(string $field_key): string;

}
```

- [ ] **Step 4: Create `ClinicalTrialsGovEntityManager`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Creates and manages node type and field entities for the import wizard.
 */
class ClinicalTrialsGovEntityManager implements ClinicalTrialsGovEntityManagerInterface {

  /**
   * Maps ClinicalTrials.gov API field types to Drupal field types.
   */
  protected array $fieldTypeMap = [
    'STRING' => 'string',
    'INTEGER' => 'integer',
    'FLOAT' => 'decimal',
    'BOOLEAN' => 'boolean',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function contentTypeExists(string $type): bool {
    return (bool) $this->entityTypeManager->getStorage('node_type')->load($type);
  }

  /**
   * {@inheritdoc}
   */
  public function createContentType(string $type, string $label, string $description): void {
    if ($this->contentTypeExists($type)) {
      return;
    }
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => $type,
      'name' => $label,
      'description' => $description,
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createFields(string $type, array $fields): void {
    foreach ($fields as $field_key) {
      $field_name = $this->getFieldMachineName($field_key);
      $field_type = $this->resolveFieldType($field_key);

      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $field_type,
        ])->save();
      }

      if (!FieldConfig::loadByName('node', $type, $field_name)) {
        $metadata = $this->manager->getStudyFieldMetadata($field_key);
        $label = $metadata['title'] ?? $field_name;
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'bundle' => $type,
          'label' => $label,
        ])->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMachineName(string $field_key): string {
    $parts = explode('.', $field_key);
    $last = end($parts);
    // Convert camelCase to snake_case.
    $snake = strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($last)));
    $name = 'ct_' . $snake;
    // Truncate to Drupal's 32-char field name limit.
    return substr($name, 0, 32);
  }

  /**
   * Returns the Drupal field type for the given API field key.
   */
  protected function resolveFieldType(string $field_key): string {
    $metadata = $this->manager->getStudyFieldMetadata($field_key);
    $api_type = strtoupper((string) ($metadata['type'] ?? 'STRING'));
    return $this->fieldTypeMap[$api_type] ?? 'string';
  }

}
```

- [ ] **Step 5: Run the test to confirm it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManagerInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php \
        web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
git commit -m "feat: add ClinicalTrialsGovEntityManager service with kernel test"
```

---

## Task 8: ClinicalTrialsGovMigrationManager (TDD)

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManagerInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManager.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php`

- [ ] **Step 1: Write the failing kernel test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovMigrationManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovMigrationManagerTest extends KernelTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'field',
    'migrate',
    'migrate_plus',
    'node',
    'system',
    'user',
  ];

  protected ClinicalTrialsGovMigrationManagerInterface $migrationManager;

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['migrate_plus']);
    $this->container->get('config.factory')
      ->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('type', 'trial')
      ->set('fields', ['protocolSection.identificationModule.nctId'])
      ->save();
    $this->migrationManager = $this->container->get('clinical_trials_gov.migration_manager');
  }

  public function testUpdateMigration(): void {
    $this->migrationManager->updateMigration();

    $config = $this->container->get('config.factory')
      ->get('migrate_plus.migration.clinical_trials_gov');

    // Check that the migration config entity has the correct structure.
    $this->assertSame('clinical_trials_gov', $config->get('id'));
    $this->assertSame('clinical_trials_gov', $config->get('source.plugin'));
    $this->assertSame('query.cond=cancer', $config->get('source.query'));
    $this->assertSame('entity:node', $config->get('destination.plugin'));
    $this->assertSame('trial', $config->get('destination.default_bundle'));

    // Check that the process map contains the expected field mapping.
    $process = $config->get('process');
    $this->assertArrayHasKey('ct_nct_id', $process);
    $this->assertSame('protocolSection.identificationModule.nctId', $process['ct_nct_id']);

    // Check that calling updateMigration() again overwrites cleanly.
    $this->container->get('config.factory')
      ->getEditable('clinical_trials_gov.settings')
      ->set('type', 'clinical_trial')
      ->save();
    $this->migrationManager->updateMigration();
    $config = $this->container->get('config.factory')
      ->get('migrate_plus.migration.clinical_trials_gov');
    $this->assertSame('clinical_trial', $config->get('destination.default_bundle'));
  }

}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php
```

Expected: FAIL — `ClinicalTrialsGovMigrationManager` class not found.

- [ ] **Step 3: Create `ClinicalTrialsGovMigrationManagerInterface`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Builds and persists the migrate_plus migration config entity.
 */
interface ClinicalTrialsGovMigrationManagerInterface {

  /**
   * Reads current settings config and writes (or overwrites) the migration entity.
   *
   * Builds migrate_plus.migration.clinical_trials_gov from the saved query,
   * type, and fields values in clinical_trials_gov.settings.
   */
  public function updateMigration(): void;

}
```

- [ ] **Step 4: Create `ClinicalTrialsGovMigrationManager`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Builds and persists the migrate_plus migration config entity.
 */
class ClinicalTrialsGovMigrationManager implements ClinicalTrialsGovMigrationManagerInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function updateMigration(): void {
    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    $query = (string) ($settings->get('query') ?? '');
    $type = (string) ($settings->get('type') ?? '');
    $fields = (array) ($settings->get('fields') ?? []);

    $process = [];
    foreach ($fields as $field_key) {
      $field_name = $this->entityManager->getFieldMachineName($field_key);
      $process[$field_name] = $field_key;
    }

    $migration_data = [
      'id' => 'clinical_trials_gov',
      'label' => 'ClinicalTrials.gov',
      'migration_tags' => ['clinical_trials_gov'],
      'source' => [
        'plugin' => 'clinical_trials_gov',
        'query' => $query,
      ],
      'process' => $process,
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => $type,
      ],
      'migration_dependencies' => [],
      'langcode' => 'en',
    ];

    $this->configFactory
      ->getEditable('migrate_plus.migration.clinical_trials_gov')
      ->setData($migration_data)
      ->save();
  }

}
```

- [ ] **Step 5: Run the test to confirm it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManagerInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovMigrationManager.php \
        web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php
git commit -m "feat: add ClinicalTrialsGovMigrationManager service with kernel test"
```

---

## Task 9: ClinicalTrialsGovSource Plugin (TDD)

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Plugin/migrate/source/ClinicalTrialsGovSource.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php`

- [ ] **Step 1: Write the failing kernel test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovSource.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSourceTest extends KernelTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
    'migrate_plus',
    'system',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['migrate_plus']);
  }

  public function testSource(): void {
    // Build a minimal migration using the source plugin.
    $definition = [
      'id' => 'test_source',
      'migration_tags' => [],
      'source' => [
        'plugin' => 'clinical_trials_gov',
        'query' => 'query.cond=cancer',
      ],
      'process' => [],
      'destination' => ['plugin' => 'null'],
      'migration_dependencies' => [],
    ];

    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);

    $source = $migration->getSourcePlugin();

    // Check that the source plugin initializes correctly.
    $this->assertNotEmpty($source->getIds());
    $this->assertArrayHasKey('nctId', $source->getIds());

    // Check that iterating the source yields rows with nctId keys.
    $rows = [];
    foreach ($source as $row) {
      $rows[] = $row->getSource();
    }
    $this->assertNotEmpty($rows, 'Source plugin yielded at least one row.');
    $this->assertArrayHasKey('nctId', $rows[0]);
  }

}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
```

Expected: FAIL — source plugin not found.

- [ ] **Step 3: Create `ClinicalTrialsGovSource`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\migrate\source;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin that yields studies from the ClinicalTrials.gov API.
 *
 * @MigrateSource(
 *   id = "clinical_trials_gov",
 *   source_module = "clinical_trials_gov"
 * )
 */
class ClinicalTrialsGovSource extends SourcePluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MigrationInterface $migration,
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MigrationInterface $migration = NULL,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('clinical_trials_gov.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'nctId' => ['type' => 'string'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return ['nctId' => 'NCT ID'];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'ClinicalTrialsGov';
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $query = (string) ($this->configuration['query'] ?? '');
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);

    $next_page_token = NULL;
    do {
      if ($next_page_token !== NULL) {
        $parameters['pageToken'] = $next_page_token;
      }
      $response = $this->manager->getStudies($parameters);
      $studies = $response['studies'] ?? [];

      foreach ($studies as $study) {
        $nct_id = $study['protocolSection']['identificationModule']['nctId'] ?? '';
        if ($nct_id === '') {
          continue;
        }
        $flat = $this->manager->getStudy($nct_id);
        $flat['nctId'] = $nct_id;
        yield $flat;
      }

      $next_page_token = $response['nextPageToken'] ?? NULL;
    } while ($next_page_token !== NULL);
  }

}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Plugin/migrate/source/ClinicalTrialsGovSource.php \
        web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
git commit -m "feat: add ClinicalTrialsGovSource migrate plugin with kernel test"
```

---

## Task 10: Configure Form

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovConfigForm.php`

- [ ] **Step 1: Create `ClinicalTrialsGovConfigForm`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Step 3 of the import wizard: creates the content type and configures field mappings.
 */
class ClinicalTrialsGovConfigForm extends ConfigFormBase {

  /**
   * Required API field keys that are always selected and cannot be deselected.
   */
  protected const REQUIRED_FIELDS = [
    'protocolSection.identificationModule.nctId',
    'protocolSection.identificationModule.briefTitle',
  ];

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $clinicalTrialsGovManager,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['clinical_trials_gov.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('clinical_trials_gov.settings');
    $type = $config->get('type') ?? 'trial';
    $saved_fields = $config->get('fields') ?? [];
    $type_exists = $this->entityManager->contentTypeExists($type);

    // Part 1 — Content type.
    $form['content_type'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content type'),
    ];

    if ($type_exists) {
      $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($type);
      $form['content_type']['info'] = [
        '#type' => 'item',
        '#markup' => $this->t('Type: <strong>@label</strong> (<code>@type</code>)', [
          '@label' => $node_type->label(),
          '@type' => $type,
        ]),
      ];
      $form['content_type']['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit content type'),
            'url' => Url::fromRoute('entity.node_type.edit_form', ['node_type' => $type]),
          ],
          'fields' => [
            'title' => $this->t('Manage fields'),
            'url' => Url::fromRoute('entity.node_type.field_ui_fields', ['node_type' => $type]),
          ],
        ],
      ];
      $form['type'] = ['#type' => 'hidden', '#value' => $type];
    }
    else {
      $form['content_type']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => 'Trial',
        '#required' => TRUE,
      ];
      $form['content_type']['type'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Machine name'),
        '#default_value' => $type,
        '#machine_name' => [
          'exists' => '\Drupal\node\Entity\NodeType::load',
          'source' => ['content_type', 'label'],
        ],
        '#required' => TRUE,
      ];
      $form['content_type']['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#rows' => 3,
      ];
    }

    // Part 2 — Field mapping.
    $form['fields_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field mapping'),
    ];

    $metadata = $this->clinicalTrialsGovManager->getStudyMetadata();
    $options = [];
    $disabled = [];
    $defaults = array_fill_keys(static::REQUIRED_FIELDS, TRUE);

    foreach ($saved_fields as $field_key) {
      $defaults[$field_key] = TRUE;
    }

    // Group fields by 'piece' from metadata.
    $grouped = [];
    foreach ($metadata as $key => $info) {
      if (!empty($info['children'])) {
        // Skip parent nodes; only leaf fields are selectable.
        continue;
      }
      $piece = $info['piece'] ?? 'general';
      $grouped[$piece][$key] = $info;
    }

    $header = [
      $this->t('Select'),
      $this->t('Field'),
      $this->t('Type'),
      $this->t('Description'),
      $this->t('Operations'),
    ];

    foreach ($grouped as $group_label => $group_fields) {
      $group_options = [];
      foreach ($group_fields as $field_key => $info) {
        $field_name = $this->entityManager->getFieldMachineName($field_key);
        $field_exists = $type_exists && \Drupal\field\Entity\FieldConfig::loadByName('node', $type, $field_name) !== NULL;

        $operations = [];
        if ($field_exists) {
          $operations['edit'] = [
            'title' => $this->t('Edit field'),
            'url' => Url::fromRoute('entity.field_config.node_field_edit_form', [
              'node_type' => $type,
              'field_config' => 'node.' . $type . '.' . $field_name,
            ]),
          ];
          $disabled[] = $field_key;
        }

        $group_options[$field_key] = [
          'field' => $info['title'] ?: $info['name'],
          'type' => $info['type'],
          'description' => ['data' => ['#markup' => Html::escape($info['description'])]],
          'operations' => ['data' => ['#type' => 'operations', '#links' => $operations]],
        ];

        if (in_array($field_key, static::REQUIRED_FIELDS)) {
          $disabled[] = $field_key;
          $defaults[$field_key] = TRUE;
        }
      }

      $form['fields_section']['fields_' . $group_label] = [
        '#type' => 'tableselect',
        '#caption' => ucfirst($group_label),
        '#header' => $header,
        '#options' => $group_options,
        '#default_value' => array_intersect_key($defaults, $group_options),
        '#disabled' => array_intersect(array_keys($group_options), $disabled),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('clinical_trials_gov.settings');

    // Determine type (hidden or machine_name element).
    $type = $form_state->getValue('type') ?? $form_state->getValue(['content_type', 'type']);
    $label = $form_state->getValue(['content_type', 'label']) ?? '';
    $description = $form_state->getValue(['content_type', 'description']) ?? '';

    // Gather all selected fields across groups.
    $fields = [];
    $all_values = $form_state->getValues();
    foreach ($all_values as $key => $value) {
      if (str_starts_with($key, 'fields_') && is_array($value)) {
        foreach ($value as $field_key => $selected) {
          if ($selected) {
            $fields[] = $field_key;
          }
        }
      }
    }
    // Always include required fields.
    $fields = array_unique(array_merge(static::REQUIRED_FIELDS, $fields));

    $config->set('type', $type)->set('fields', $fields)->save();

    $this->entityManager->createContentType($type, $label, $description);
    $this->entityManager->createFields($type, $fields);
    $this->migrationManager->updateMigration();

    $form_state->setRedirect('clinical_trials_gov.import');
  }

}
```

Add `use Drupal\Component\Utility\Html;` at the top of the file.

- [ ] **Step 2: Verify the form loads and submits**

Visit `/admin/config/services/clinical-trials-gov/configure`, fill in label/machine name, select fields, and submit. Confirm the content type and fields are created and the page redirects to the Import step.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovConfigForm.php
git commit -m "feat: add ClinicalTrialsGovConfigForm (Step 3 of import wizard)"
```

---

## Task 11: Import Form

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovImportForm.php`

- [ ] **Step 1: Create `ClinicalTrialsGovImportForm`**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateBatchExecutable;

/**
 * Step 4 of the import wizard: shows import summary and triggers migration.
 */
class ClinicalTrialsGovImportForm extends FormBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory->get('clinical_trials_gov.settings');
    $query = $config->get('query') ?? '';
    $type = $config->get('type') ?? '';
    $fields = $config->get('fields') ?? [];

    $import_ready = ($query !== '' && $type !== '' && !empty($fields));

    $form['summary'] = [
      '#type' => 'table',
      '#caption' => $this->t('Import summary'),
      '#header' => [$this->t('Setting'), $this->t('Value')],
      '#rows' => [
        [$this->t('Query'), $query ?: $this->t('Not set')],
        [$this->t('Content type'), $type ?: $this->t('Not set')],
        [$this->t('Fields selected'), count($fields)],
      ],
    ];

    // Show stats from a previous migration run if available.
    $migration_config = $this->configFactory->get('migrate_plus.migration.clinical_trials_gov');
    if (!$migration_config->isNew()) {
      $migration = $this->entityTypeManager
        ->getStorage('migration')
        ->load('clinical_trials_gov');
      if ($migration) {
        $map = $migration->getIdMap();
        $form['stats'] = [
          '#type' => 'table',
          '#caption' => $this->t('Previous run statistics'),
          '#header' => [$this->t('Metric'), $this->t('Count')],
          '#rows' => [
            [$this->t('Processed'), $map->processedCount()],
            [$this->t('Imported'), $map->importedCount()],
            [$this->t('Failed'), $map->errorCount()],
          ],
        ];
      }
    }

    if (!$import_ready) {
      $form['not_ready'] = [
        '#type' => 'item',
        '#markup' => $this->t('Complete the <a href=":find">Find</a> and <a href=":configure">Configure</a> steps before importing.', [
          ':find' => Url::fromRoute('clinical_trials_gov.find')->toString(),
          ':configure' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
        ]),
      ];
      return $form;
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $migration = $this->entityTypeManager
      ->getStorage('migration')
      ->load('clinical_trials_gov');

    if (!$migration) {
      $this->messenger()->addError($this->t('Migration definition not found. Save the Configure step first.'));
      return;
    }

    $executable = new MigrateBatchExecutable(
      $migration,
      new MigrateMessage(),
      ['limit' => 0, 'update' => 1, 'force' => 0, 'sync' => 1],
    );
    $executable->batchImport();
  }

}
```

- [ ] **Step 2: Verify the Import page renders the summary**

Visit `/admin/config/services/clinical-trials-gov/import` and confirm the summary table and "Run import" button appear.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovImportForm.php
git commit -m "feat: add ClinicalTrialsGovImportForm (Step 4 of import wizard)"
```

---

## Task 12: Functional Test

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php`

- [ ] **Step 1: Create the functional test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for the ClinicalTrials.gov import wizard.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovTest extends BrowserTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'field',
    'field_ui',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'node',
    'text',
    'user',
  ];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser(['administer clinical_trials_gov']);
    $this->drupalLogin($account);
  }

  public function testImportWizard(): void {
    // Check that the index page lists all four steps.
    $this->drupalGet('admin/config/services/clinical-trials-gov');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Find');
    $this->assertSession()->pageTextContains('Review');
    $this->assertSession()->pageTextContains('Configure');
    $this->assertSession()->pageTextContains('Import');

    // Check that the Find form saves the query to config.
    $this->drupalGet('admin/config/services/clinical-trials-gov/find');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->addressEquals('admin/config/services/clinical-trials-gov/review');

    // Check that the Review page renders (uses stub, so always has results).
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Continue to Configure');

    // Check that the Configure form creates the content type and saves fields.
    $this->drupalGet('admin/config/services/clinical-trials-gov/configure');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('content_type[label]');
    $this->assertSession()->fieldExists('content_type[type]');

    $this->submitForm([
      'content_type[label]' => 'Trial',
      'content_type[type]' => 'trial',
    ], 'Save configuration');
    $this->assertSession()->addressEquals('admin/config/services/clinical-trials-gov/import');

    // Check that the Import page shows the summary and is ready to run.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('trial');
    $this->assertSession()->buttonExists('Run import');

    // Check that the index page now shows Import as an active link.
    $this->drupalGet('admin/config/services/clinical-trials-gov');
    $this->assertSession()->linkExists('Import');
  }

}
```

- [ ] **Step 2: Run the functional test**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
```

Expected: PASS.

- [ ] **Step 3: Run the full module test suite**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/
```

Expected: all tests pass.

- [ ] **Step 4: Run code review**

```bash
ddev code-review web/modules/custom/clinical_trials_gov/
```

Fix any reported issues.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
git commit -m "test: add ClinicalTrialsGovTest functional test for import wizard"
```
