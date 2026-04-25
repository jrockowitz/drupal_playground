# ClinicalTrials.gov Phase 1 — Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a `clinical_trials_gov` Drupal module (services + form element + builder) with a `clinical_trials_gov_report` submodule that provides an admin report at `/admin/reports/status/clinical-trials-gov` for browsing ClinicalTrials.gov studies.

**Architecture:** The main module provides three services (`Api`, `Manager`, `Builder`) and a composite form element (`ClinicalTrialsGovStudiesQuery`). A `clinical_trials_gov_report` submodule (nested at `modules/`) provides the two controllers and search form. A `clinical_trials_gov_test` submodule provides a stub manager and JSON fixtures for all tests.

**Tech Stack:** Drupal 10/11, PHP 8.3, Guzzle HTTP, PHPUnit (Unit, Kernel, Functional), ClinicalTrials.gov API v2.

**Spec:** `docs/superpowers/specs/2026-04-25-clinical-trials-gov-phase1-design.md`

---

## File Map

```
web/modules/custom/clinical_trials_gov/
├── README.md
├── clinical_trials_gov.info.yml
├── clinical_trials_gov.services.yml
├── test/                                              ← moved from clinicaltrialsgov/
├── src/
│   ├── ClinicalTrialsGovApiInterface.php
│   ├── ClinicalTrialsGovApi.php
│   ├── ClinicalTrialsGovManagerInterface.php
│   ├── ClinicalTrialsGovManager.php
│   ├── ClinicalTrialsGovBuilderInterface.php
│   ├── ClinicalTrialsGovBuilder.php
│   └── Element/
│       └── ClinicalTrialsGovStudiesQuery.php
├── tests/src/
│   ├── Unit/
│   │   ├── ClinicalTrialsGovApiTest.php
│   │   └── ClinicalTrialsGovManagerTest.php
│   └── Kernel/
│       ├── ClinicalTrialsGovBuilderTest.php
│       └── ClinicalTrialsGovStudiesQueryTest.php
└── modules/
    ├── clinical_trials_gov_report/
    │   ├── clinical_trials_gov_report.info.yml
    │   ├── clinical_trials_gov_report.routing.yml
    │   ├── clinical_trials_gov_report.links.menu.yml
    │   ├── src/
    │   │   ├── Form/
    │   │   │   └── ClinicalTrialsGovStudiesSearchForm.php
    │   │   └── Controller/
    │   │       ├── ClinicalTrialsGovReportController.php
    │   │       └── ClinicalTrialsGovStudyController.php
    │   └── tests/src/Functional/
    │       └── ClinicalTrialsGovReportTest.php
    └── clinical_trials_gov_test/
        ├── clinical_trials_gov_test.info.yml
        ├── clinical_trials_gov_test.services.yml
        ├── fixtures/
        │   ├── studies.json
        │   ├── study-NCT001.json        ← RECRUITING, full data
        │   ├── study-NCT002.json        ← COMPLETED, hasResults true
        │   ├── study-NCT003.json        ← sparse data, missing modules
        │   ├── metadata.json
        │   ├── enums.json
        │   └── search-areas.json
        └── src/
            └── ClinicalTrialsGovManagerStub.php
```

---

## Task 1: Main module scaffold

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.info.yml`
- Create: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml`
- Create: `web/modules/custom/clinical_trials_gov/README.md`
- Move: `web/modules/custom/clinicaltrialsgov/test/` → `web/modules/custom/clinical_trials_gov/test/`

- [ ] **Step 1: Create the module directory and info file**

```yaml
# web/modules/custom/clinical_trials_gov/clinical_trials_gov.info.yml
name: 'ClinicalTrials.gov'
type: module
description: 'Services and components for the ClinicalTrials.gov API v2 integration.'
package: Custom
core_version_requirement: ^10.3 || ^11
```

- [ ] **Step 2: Create services.yml with interface aliases for autowire**

```yaml
# web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml
services:
  clinical_trials_gov.api:
    class: Drupal\clinical_trials_gov\ClinicalTrialsGovApi
    autowire: true
    arguments: ['@http_client']
  Drupal\clinical_trials_gov\ClinicalTrialsGovApiInterface: '@clinical_trials_gov.api'

  clinical_trials_gov.manager:
    class: Drupal\clinical_trials_gov\ClinicalTrialsGovManager
    autowire: true
  Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface: '@clinical_trials_gov.manager'

  clinical_trials_gov.builder:
    class: Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder
    autowire: true
  Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface: '@clinical_trials_gov.builder'
```

- [ ] **Step 3: Create README.md**

```markdown
# ClinicalTrials.gov

Drupal integration with the [ClinicalTrials.gov API v2](https://clinicaltrials.gov/data-api/api).

## Submodules

- **clinical_trials_gov_report** — Admin report at `/admin/reports/status/clinical-trials-gov`
- **clinical_trials_gov_test** — Stub manager and JSON fixtures for testing

## Services

| Service | Interface | Description |
|---|---|---|
| `clinical_trials_gov.api` | `ClinicalTrialsGovApiInterface` | Low-level HTTP client |
| `clinical_trials_gov.manager` | `ClinicalTrialsGovManagerInterface` | Fetches and organises API data |
| `clinical_trials_gov.builder` | `ClinicalTrialsGovBuilderInterface` | Converts study data to render arrays |

## Development

The `test/` directory contains a standalone PHP explorer for the API (proof-of-concept). Run it directly via a PHP web server — it has no Drupal dependency.
```

- [ ] **Step 4: Move the existing POC test directory**

```bash
mv web/modules/custom/clinicaltrialsgov/test web/modules/custom/clinical_trials_gov/test
```

- [ ] **Step 5: Enable the module to verify it loads**

```bash
ddev drush en clinical_trials_gov
```

Expected: `The following module(s) will be enabled: clinical_trials_gov` then success. No errors.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/
git commit -m "feat: scaffold clinical_trials_gov module"
```

---

## Task 2: ClinicalTrialsGovApi service

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovApiInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovApi.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovApiTest.php`

- [ ] **Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov API HTTP client.
 */
interface ClinicalTrialsGovApiInterface {

  /**
   * Performs a GET request to the API.
   *
   * @param string $path
   *   The API path, e.g. '/studies' or '/studies/metadata'.
   * @param array $parameters
   *   Query parameters to include in the request.
   *
   * @return array
   *   Decoded JSON response, or an empty array if the response is null.
   */
  public function get(string $path, array $parameters = []): array;

}
```

- [ ] **Step 2: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovApi.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovApi
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovApiTest extends UnitTestCase {

  protected ClinicalTrialsGovApi $api;
  protected ClientInterface $httpClient;

  protected function setUp(): void {
    parent::setUp();
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->api = new ClinicalTrialsGovApi($this->httpClient);
  }

  /**
   * Tests that get() returns decoded JSON from a successful response.
   *
   * @covers ::get
   */
  public function testGetReturnsDecodedJson(): void {
    $data = ['studies' => [['nctId' => 'NCT001']], 'totalCount' => 1];
    $this->httpClient
      ->expects($this->once())
      ->method('request')
      ->with('GET', 'https://clinicaltrials.gov/api/v2/studies', [
        'query' => ['query.cond' => 'cancer'],
        'headers' => ['Accept' => 'application/json'],
      ])
      ->willReturn(new Response(200, [], json_encode($data)));

    $result = $this->api->get('/studies', ['query.cond' => 'cancer']);

    // Check that the decoded JSON is returned as-is.
    $this->assertSame($data, $result);
  }

  /**
   * Tests that get() returns an empty array when the API returns JSON null.
   *
   * @covers ::get
   */
  public function testGetReturnsEmptyArrayForNullResponse(): void {
    $this->httpClient
      ->method('request')
      ->willReturn(new Response(200, [], 'null'));

    $result = $this->api->get('/version');

    // Check that null JSON is normalised to an empty array.
    $this->assertSame([], $result);
  }

  /**
   * Tests that get() with no parameters omits the query string.
   *
   * @covers ::get
   */
  public function testGetWithNoParameters(): void {
    $this->httpClient
      ->expects($this->once())
      ->method('request')
      ->with('GET', 'https://clinicaltrials.gov/api/v2/version', [
        'query' => [],
        'headers' => ['Accept' => 'application/json'],
      ])
      ->willReturn(new Response(200, [], '{"dataTimestamp":"2024-01-01"}'));

    $result = $this->api->get('/version');

    // Check that the decoded response is returned.
    $this->assertSame(['dataTimestamp' => '2024-01-01'], $result);
  }

}
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovApiTest.php
```

Expected: FAIL — class `ClinicalTrialsGovApi` not found.

- [ ] **Step 4: Implement the class**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use GuzzleHttp\ClientInterface;

/**
 * Low-level HTTP client for the ClinicalTrials.gov API v2.
 */
class ClinicalTrialsGovApi implements ClinicalTrialsGovApiInterface {

  const BASE_URL = 'https://clinicaltrials.gov/api/v2';

  public function __construct(
    protected ClientInterface $httpClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $parameters = []): array {
    $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
      'query' => $parameters,
      'headers' => ['Accept' => 'application/json'],
    ]);
    return json_decode((string) $response->getBody(), TRUE) ?? [];
  }

}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovApiTest.php
```

Expected: 3 tests, 3 assertions, OK.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovApiInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovApi.php \
        web/modules/custom/clinical_trials_gov/tests/
git commit -m "feat: add ClinicalTrialsGovApi service with unit tests"
```

---

## Task 3: ClinicalTrialsGovManager service

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovManagerInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovManager.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php`

- [ ] **Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov manager service.
 */
interface ClinicalTrialsGovManagerInterface {

  /**
   * Fetches a list of studies from the API.
   *
   * @param array $parameters
   *   Raw API parameters assembled from a query string.
   *
   * @return array
   *   Raw API response: ['studies' => [...], 'nextPageToken' => '...', 'totalCount' => N].
   */
  public function getStudies(array $parameters): array;

  /**
   * Fetches a single study by NCT ID and returns a flat Index-field array.
   *
   * Associative arrays (objects) in the response are recursed into using
   * dot-notation keys. Lists and scalar values are stored as-is.
   *
   * @param string $nct_id
   *   The NCT ID, e.g. 'NCT04001699'.
   *
   * @return array
   *   Flat array keyed by Index field paths.
   */
  public function getStudy(string $nct_id): array;

  /**
   * Fetches and flattens the full study metadata tree.
   *
   * Cached statically for the request lifetime.
   *
   * @return array
   *   Flat array keyed by Index field path. Each value has keys:
   *   key, name, piece, title, type, sourceType, description, children.
   */
  public function getStudyMetadata(): array;

  /**
   * Returns metadata for a single Index field path.
   *
   * @param string $index_field
   *   Dot-notation Index field path.
   *
   * @return array|null
   *   Metadata array, or NULL if not found.
   */
  public function getStudyFieldMetadata(string $index_field): ?array;

  /**
   * Fetches all enumeration types and their allowed values.
   *
   * Cached statically for the request lifetime.
   *
   * @return array
   *   Raw response from /studies/enums.
   */
  public function getEnums(): array;

  /**
   * Returns allowed values for a single enum type.
   *
   * @param string $enum_type
   *   The enum type name, e.g. 'OverallStatus'.
   *
   * @return array
   *   List of allowed string values, or an empty array if not found.
   */
  public function getEnum(string $enum_type): array;

}
```

- [ ] **Step 2: Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApiInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovManager.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovManager
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovManagerTest extends UnitTestCase {

  protected ClinicalTrialsGovManager $manager;
  protected ClinicalTrialsGovApiInterface $api;

  protected function setUp(): void {
    parent::setUp();
    $this->api = $this->createMock(ClinicalTrialsGovApiInterface::class);
    $this->manager = new ClinicalTrialsGovManager($this->api);
  }

  /**
   * Tests that getStudies() returns the raw API response unchanged.
   *
   * @covers ::getStudies
   */
  public function testGetStudiesReturnsRawResponse(): void {
    $response = [
      'studies' => [['protocolSection' => ['identificationModule' => ['nctId' => 'NCT001']]]],
      'totalCount' => 1,
    ];
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies', ['query.cond' => 'cancer', 'countTotal' => 'true'])
      ->willReturn($response);

    $result = $this->manager->getStudies(['query.cond' => 'cancer', 'countTotal' => 'true']);

    // Check that the raw response is returned with no transformation.
    $this->assertSame($response, $result);
  }

  /**
   * Tests that getStudy() flattens nested objects to dot-notation keys.
   *
   * @covers ::getStudy
   */
  public function testGetStudyFlattensNestedObjects(): void {
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies/NCT001')
      ->willReturn([
        'protocolSection' => [
          'identificationModule' => [
            'nctId' => 'NCT001',
            'briefTitle' => 'A Test Study',
          ],
          'conditionsModule' => [
            'conditions' => ['Cancer', 'Leukemia'],
          ],
        ],
        'hasResults' => FALSE,
      ]);

    $result = $this->manager->getStudy('NCT001');

    // Check that nested assoc arrays are flattened to dot-notation keys.
    $this->assertSame('NCT001', $result['protocolSection.identificationModule.nctId']);
    $this->assertSame('A Test Study', $result['protocolSection.identificationModule.briefTitle']);

    // Check that lists are stored as-is (not flattened further).
    $this->assertSame(['Cancer', 'Leukemia'], $result['protocolSection.conditionsModule.conditions']);

    // Check that top-level scalars are preserved.
    $this->assertFalse($result['hasResults']);

    // Check that no intermediate keys exist (only leaves).
    $this->assertArrayNotHasKey('protocolSection', $result);
    $this->assertArrayNotHasKey('protocolSection.identificationModule', $result);
  }

  /**
   * Tests that getStudyMetadata() flattens the metadata tree.
   *
   * @covers ::getStudyMetadata
   */
  public function testGetStudyMetadataFlattensTree(): void {
    $this->api
      ->method('get')
      ->with('/studies/metadata')
      ->willReturn([
        [
          'name' => 'protocolSection',
          'piece' => 'ProtocolSection',
          'title' => 'Protocol Section',
          'type' => 'StdStudy',
          'sourceType' => 'STRUCT',
          'description' => 'Top-level protocol section.',
          'children' => [
            [
              'name' => 'identificationModule',
              'piece' => 'Identification',
              'title' => 'Identification Module',
              'type' => 'IdModule',
              'sourceType' => 'STRUCT',
              'description' => 'Study identification data.',
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getStudyMetadata();

    // Check that the top-level section is keyed by its name.
    $this->assertArrayHasKey('protocolSection', $result);
    $this->assertSame('STRUCT', $result['protocolSection']['sourceType']);

    // Check that a nested module is keyed by its dotted path.
    $this->assertArrayHasKey('protocolSection.identificationModule', $result);
    $this->assertSame('Identification Module', $result['protocolSection.identificationModule']['title']);

    // Check that children are recorded as dotted paths.
    $this->assertContains('protocolSection.identificationModule', $result['protocolSection']['children']);
  }

  /**
   * Tests that getEnum() returns values for a named enum type.
   *
   * @covers ::getEnum
   */
  public function testGetEnumReturnsAllowedValues(): void {
    $this->api
      ->method('get')
      ->with('/studies/enums')
      ->willReturn([
        ['type' => 'OverallStatus', 'values' => ['RECRUITING', 'COMPLETED', 'TERMINATED']],
        ['type' => 'Phase', 'values' => ['PHASE1', 'PHASE2', 'PHASE3']],
      ]);

    $result = $this->manager->getEnum('OverallStatus');

    // Check that the correct enum values are returned.
    $this->assertSame(['RECRUITING', 'COMPLETED', 'TERMINATED'], $result);
  }

  /**
   * Tests that getEnum() returns an empty array for an unknown type.
   *
   * @covers ::getEnum
   */
  public function testGetEnumReturnsEmptyArrayForUnknownType(): void {
    $this->api->method('get')->willReturn([]);

    // Check that an empty array is returned when the type is not found.
    $this->assertSame([], $this->manager->getEnum('UnknownType'));
  }

}
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
```

Expected: FAIL — class `ClinicalTrialsGovManager` not found.

- [ ] **Step 4: Implement the class**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Fetches and organises data from the ClinicalTrials.gov API.
 */
class ClinicalTrialsGovManager implements ClinicalTrialsGovManagerInterface {

  public function __construct(
    protected ClinicalTrialsGovApiInterface $api,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStudies(array $parameters): array {
    return $this->api->get('/studies', $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudy(string $nct_id): array {
    $data = $this->api->get('/studies/' . $nct_id);
    return $this->flattenStudy($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyMetadata(): array {
    static $metadata = NULL;
    if ($metadata === NULL) {
      $data = $this->api->get('/studies/metadata');
      $metadata = $this->flattenMetadata(is_array($data) ? $data : []);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyFieldMetadata(string $index_field): ?array {
    return $this->getStudyMetadata()[$index_field] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnums(): array {
    static $enums = NULL;
    if ($enums === NULL) {
      $enums = $this->api->get('/studies/enums');
    }
    return is_array($enums) ? $enums : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnum(string $enum_type): array {
    foreach ($this->getEnums() as $enum) {
      if (!is_array($enum)) {
        continue;
      }
      if (($enum['type'] ?? '') === $enum_type) {
        return is_array($enum['values'] ?? NULL) ? $enum['values'] : [];
      }
    }
    return [];
  }

  /**
   * Recursively flattens nested study data to dot-notation Index field keys.
   *
   * Associative arrays (objects) are recursed into. Lists and scalar values
   * are stored as-is under their full dotted key path.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }
    return [$prefix => $data];
  }

  /**
   * Recursively flattens the metadata tree to dot-notation name-path rows.
   */
  protected function flattenMetadata(array $items, string $prefix = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $key = ($prefix !== '' && $name !== '') ? $prefix . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if ($child_name === '') {
          continue;
        }
        $children[] = ($key !== '') ? $key . '.' . $child_name : $child_name;
      }
      $rows[$key] = [
        'key' => $key,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $key);
      }
    }
    return $rows;
  }

}
```

**Note on `getEnum()` structure:** The plan assumes the `/studies/enums` response is an array of `['type' => '...', 'values' => [...]]` objects. Verify this against the downloaded fixture in Task 4 and adjust if the shape differs.

- [ ] **Step 5: Run tests to verify they pass**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
```

Expected: 5 tests, OK.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovManagerInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovManager.php \
        web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
git commit -m "feat: add ClinicalTrialsGovManager service with unit tests"
```

---

## Task 4: Test module and fixture files

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/clinical_trials_gov_test.info.yml`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/clinical_trials_gov_test.services.yml`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/src/ClinicalTrialsGovManagerStub.php`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/fixtures/*.json` (7 files)

- [ ] **Step 1: Download fixture files from the live API**

Run these curl commands and save the output. Replace the NCT IDs with three real studies from `https://clinicaltrials.gov` — pick one RECRUITING with full data, one COMPLETED with `hasResults: true`, and one with sparse data.

```bash
FIXTURES=web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/fixtures
mkdir -p $FIXTURES

# List page (pick 3 studies with different statuses)
curl -s "https://clinicaltrials.gov/api/v2/studies?query.cond=cancer&pageSize=3&countTotal=true" \
  | python3 -m json.tool > $FIXTURES/studies.json

# Single study — RECRUITING (replace NCT_RECRUITING with an actual NCT ID from the list above)
curl -s "https://clinicaltrials.gov/api/v2/studies/NCT_RECRUITING" \
  | python3 -m json.tool > $FIXTURES/study-NCT001.json

# Single study — COMPLETED with hasResults (replace NCT_COMPLETED)
curl -s "https://clinicaltrials.gov/api/v2/studies/NCT_COMPLETED" \
  | python3 -m json.tool > $FIXTURES/study-NCT002.json

# Single study — sparse data (replace NCT_SPARSE)
curl -s "https://clinicaltrials.gov/api/v2/studies/NCT_SPARSE" \
  | python3 -m json.tool > $FIXTURES/study-NCT003.json

# Metadata, enums, search-areas
curl -s "https://clinicaltrials.gov/api/v2/studies/metadata" \
  | python3 -m json.tool > $FIXTURES/metadata.json
curl -s "https://clinicaltrials.gov/api/v2/studies/enums" \
  | python3 -m json.tool > $FIXTURES/enums.json
curl -s "https://clinicaltrials.gov/api/v2/studies/search-areas" \
  | python3 -m json.tool > $FIXTURES/search-areas.json
```

After downloading, open `enums.json` and verify the structure of each enum entry. If it differs from `['type' => '...', 'values' => [...]]`, update `ClinicalTrialsGovManager::getEnum()` accordingly.

**Important:** The three NCT IDs chosen for `study-NCT001.json`, `study-NCT002.json`, and `study-NCT003.json` must also appear in the `studies.json` list response. Either pick IDs that appear in the list you downloaded, or re-download `studies.json` filtered to those specific IDs:

```bash
curl -s "https://clinicaltrials.gov/api/v2/studies?filter.ids=NCT001_ID|NCT002_ID|NCT003_ID&countTotal=true" \
  | python3 -m json.tool > $FIXTURES/studies.json
```

Also note the three actual NCT IDs used and update the stub (Step 3) to map them correctly.

- [ ] **Step 2: Create the test module info and services files**

```yaml
# clinical_trials_gov_test.info.yml
name: 'ClinicalTrials.gov Test'
type: module
description: 'Stub manager and fixtures for ClinicalTrials.gov testing.'
package: Testing
core_version_requirement: ^10.3 || ^11
hidden: true
dependencies:
  - clinical_trials_gov:clinical_trials_gov
```

```yaml
# clinical_trials_gov_test.services.yml
services:
  clinical_trials_gov.manager:
    class: Drupal\clinical_trials_gov_test\ClinicalTrialsGovManagerStub
```

- [ ] **Step 3: Create the stub**

Replace `NCT001_ID`, `NCT002_ID`, `NCT003_ID` with the actual NCT IDs used in Step 1.

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_test;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;

/**
 * Stub manager that returns fixture data for testing.
 *
 * Installed by clinical_trials_gov_test to replace clinical_trials_gov.manager
 * in the service container, eliminating live API calls in all test types.
 */
class ClinicalTrialsGovManagerStub implements ClinicalTrialsGovManagerInterface {

  /**
   * Map of NCT ID to fixture filename (without extension).
   */
  protected array $studyFixtureMap = [
    'NCT001_ID' => 'study-NCT001',
    'NCT002_ID' => 'study-NCT002',
    'NCT003_ID' => 'study-NCT003',
  ];

  /**
   * {@inheritdoc}
   */
  public function getStudies(array $parameters): array {
    return $this->loadFixture('studies');
  }

  /**
   * {@inheritdoc}
   */
  public function getStudy(string $nct_id): array {
    $fixture_name = $this->studyFixtureMap[$nct_id] ?? NULL;
    if ($fixture_name === NULL) {
      return [];
    }
    $data = $this->loadFixture($fixture_name);
    return $this->flattenStudy($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyMetadata(): array {
    static $metadata = NULL;
    if ($metadata === NULL) {
      $metadata = $this->flattenMetadata($this->loadFixture('metadata'));
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyFieldMetadata(string $index_field): ?array {
    return $this->getStudyMetadata()[$index_field] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnums(): array {
    return $this->loadFixture('enums');
  }

  /**
   * {@inheritdoc}
   */
  public function getEnum(string $enum_type): array {
    foreach ($this->getEnums() as $enum) {
      if (!is_array($enum)) {
        continue;
      }
      if (($enum['type'] ?? '') === $enum_type) {
        return is_array($enum['values'] ?? NULL) ? $enum['values'] : [];
      }
    }
    return [];
  }

  /**
   * Loads and decodes a fixture JSON file by name.
   */
  protected function loadFixture(string $name): array {
    $path = dirname(__DIR__) . '/fixtures/' . $name . '.json';
    if (!file_exists($path)) {
      return [];
    }
    return json_decode(file_get_contents($path), TRUE) ?? [];
  }

  /**
   * Mirrors ClinicalTrialsGovManager::flattenStudy().
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }
    return [$prefix => $data];
  }

  /**
   * Mirrors ClinicalTrialsGovManager::flattenMetadata().
   */
  protected function flattenMetadata(array $items, string $prefix = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $key = ($prefix !== '' && $name !== '') ? $prefix . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if ($child_name === '') {
          continue;
        }
        $children[] = ($key !== '') ? $key . '.' . $child_name : $child_name;
      }
      $rows[$key] = [
        'key' => $key,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $key);
      }
    }
    return $rows;
  }

}
```

- [ ] **Step 4: Enable the test module to verify it loads**

```bash
ddev drush en clinical_trials_gov_test
```

Expected: success, no errors.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/
git commit -m "feat: add clinical_trials_gov_test module with stub and fixtures"
```

---

## Task 5: ClinicalTrialsGovBuilder service

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilderInterface.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilder.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovBuilderTest.php`

- [ ] **Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov builder service.
 */
interface ClinicalTrialsGovBuilderInterface {

  /**
   * Converts a flat Index-field study array into a Drupal render array.
   *
   * Fields with sourceType STRUCT in the metadata become #type => details.
   * Leaf fields become #type => item. A collapsed Raw data details widget
   * containing the full flat table is appended at the end.
   *
   * @param array $study
   *   Flat array keyed by Index field paths, as returned by
   *   ClinicalTrialsGovManagerInterface::getStudy().
   *
   * @return array
   *   Drupal render array using only native elements (no custom CSS).
   */
  public function buildStudy(array $study): array;

}
```

- [ ] **Step 2: Write the failing kernel test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovBuilder.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovBuilderTest extends KernelTestBase {

  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
  ];

  protected ClinicalTrialsGovBuilderInterface $builder;

  protected function setUp(): void {
    parent::setUp();
    $this->builder = $this->container->get('clinical_trials_gov.builder');
  }

  /**
   * Tests that buildStudy() produces the expected render array structure.
   */
  public function testBuildStudy(): void {
    // Replace 'NCT001_ID' with the actual NCT ID used in study-NCT001.json
    // (set in Task 4).
    $nct_id = 'NCT001_ID';

    $study = $this->container->get('clinical_trials_gov.manager')->getStudy($nct_id);
    $this->assertNotEmpty($study, 'Stub returned a non-empty study array.');

    $build = $this->builder->buildStudy($study);

    // Check that the top-level element is a container.
    $this->assertSame('container', $build['#type']);

    // Check that at least one top-level section exists as a details element.
    $has_details = FALSE;
    foreach ($build as $key => $value) {
      if (is_array($value) && ($value['#type'] ?? '') === 'details') {
        $has_details = TRUE;
        break;
      }
    }
    $this->assertTrue($has_details, 'At least one top-level details element expected.');

    // Check that the raw data details widget is appended.
    $this->assertArrayHasKey('raw_data', $build);
    $this->assertSame('details', $build['raw_data']['#type']);
    $this->assertArrayHasKey('table', $build['raw_data']);
    $this->assertSame('table', $build['raw_data']['table']['#type']);

    // Check that the raw data table has the expected column headers.
    $header_labels = array_map(
      fn($header) => (string) $header,
      $build['raw_data']['table']['#header']
    );
    $this->assertContains('Field path', $header_labels);
    $this->assertContains('Value', $header_labels);

    // Check that the raw data table has one row per study field.
    $this->assertCount(count($study), $build['raw_data']['table']['#rows']);
  }

}
```

**Note:** `studyFixtureMap` is `protected` in the stub. Either make it `public` for the test or hardcode one of the NCT IDs from Task 4 directly in the test (replace `$nct_id` with the actual string, e.g. `'NCT04280705'`).

- [ ] **Step 3: Run the test to verify it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovBuilderTest.php
```

Expected: FAIL — class `ClinicalTrialsGovBuilder` not found.

- [ ] **Step 4: Implement the class**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Converts ClinicalTrials.gov API study data into Drupal render arrays.
 */
class ClinicalTrialsGovBuilder implements ClinicalTrialsGovBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildStudy(array $study): array {
    $metadata = $this->manager->getStudyMetadata();
    $nested = $this->nestStudy($study);

    $build = ['#type' => 'container'];
    foreach ($nested as $section_key => $section_data) {
      $build[$section_key] = $this->buildNode($section_key, $section_data, $metadata);
    }
    $build['raw_data'] = $this->buildRawDataTable($study, $metadata);

    return $build;
  }

  /**
   * Re-nests a flat dot-notation array into a nested associative array.
   */
  protected function nestStudy(array $flat): array {
    $nested = [];
    foreach ($flat as $key => $value) {
      $this->setNestedValue($nested, explode('.', $key), $value);
    }
    return $nested;
  }

  /**
   * Sets a value at the given key path inside a nested array.
   */
  protected function setNestedValue(array &$array, array $keys, mixed $value): void {
    $key = array_shift($keys);
    if (empty($keys)) {
      $array[$key] = $value;
      return;
    }
    if (!isset($array[$key]) || !is_array($array[$key])) {
      $array[$key] = [];
    }
    $this->setNestedValue($array[$key], $keys, $value);
  }

  /**
   * Recursively builds a render element for a node in the nested study tree.
   *
   * Nodes with sourceType STRUCT in the metadata become #type => details.
   * All other nodes become #type => item.
   */
  protected function buildNode(string $key, mixed $value, array $metadata): array {
    $field_metadata = $metadata[$key] ?? [];
    $is_struct = ($field_metadata['sourceType'] ?? '') === 'STRUCT';
    $title = $field_metadata['title'] ?? $key;

    if ($is_struct && is_array($value) && !array_is_list($value)) {
      $build = [
        '#type' => 'details',
        '#title' => $this->t('@title', ['@title' => $title]),
        '#open' => TRUE,
      ];
      foreach ($value as $child_key => $child_value) {
        $build[$child_key] = $this->buildNode($key . '.' . $child_key, $child_value, $metadata);
      }
      return $build;
    }

    return [
      '#type' => 'item',
      '#title' => $this->t('@title', ['@title' => $title]),
      '#markup' => $this->renderValue($value),
    ];
  }

  /**
   * Renders a leaf value to a safe HTML string.
   */
  protected function renderValue(mixed $value): string {
    if ($value === NULL) {
      return '—';
    }
    if (is_bool($value)) {
      return (string) ($value ? $this->t('Yes') : $this->t('No'));
    }
    if (is_array($value)) {
      if (empty($value)) {
        return '—';
      }
      if (array_is_list($value)) {
        $items = array_map(
          fn($item) => '<li>' . htmlspecialchars(is_scalar($item) ? (string) $item : json_encode($item)) . '</li>',
          $value
        );
        return '<ul>' . implode('', $items) . '</ul>';
      }
      return htmlspecialchars(json_encode($value));
    }
    return htmlspecialchars((string) $value);
  }

  /**
   * Builds the collapsed raw data table showing all flat key-value pairs.
   */
  protected function buildRawDataTable(array $study, array $metadata): array {
    $rows = [];
    foreach ($study as $key => $value) {
      $field_metadata = $metadata[$key] ?? [];
      $rows[] = [
        htmlspecialchars($key),
        htmlspecialchars($field_metadata['name'] ?? ''),
        htmlspecialchars($field_metadata['piece'] ?? ''),
        htmlspecialchars($field_metadata['title'] ?? ''),
        htmlspecialchars($field_metadata['sourceType'] ?? ''),
        $this->renderValue($value),
      ];
    }
    return [
      '#type' => 'details',
      '#title' => $this->t('Raw data'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field path'),
          $this->t('Name'),
          $this->t('Piece'),
          $this->t('Title'),
          $this->t('Source type'),
          $this->t('Value'),
        ],
        '#rows' => $rows,
      ],
    ];
  }

}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovBuilderTest.php
```

Expected: 1 test, OK.

- [ ] **Step 6: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilderInterface.php \
        web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovBuilder.php \
        web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovBuilderTest.php
git commit -m "feat: add ClinicalTrialsGovBuilder service with kernel test"
```

---

## Task 6: ClinicalTrialsGovStudiesQuery form element

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Element/ClinicalTrialsGovStudiesQuery.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovStudiesQueryTest.php`

- [ ] **Step 1: Write the failing kernel test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovStudiesQuery form element.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovStudiesQueryTest extends KernelTestBase {

  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
  ];

  /**
   * Tests the full process → validate lifecycle of the element.
   */
  public function testProcessAndValidate(): void {
    $element = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => 'query.cond=cancer&filter.overallStatus=RECRUITING',
      '#parents' => ['studies_query'],
      '#array_parents' => ['studies_query'],
    ];
    $form_state = new FormState();
    $complete_form = [];

    $processed = ClinicalTrialsGovStudiesQuery::processStudiesQuery(
      $element,
      $form_state,
      $complete_form
    );

    // Check that query parameter sub-elements are built.
    $this->assertArrayHasKey('query_parameters', $processed);
    $this->assertArrayHasKey('query__cond', $processed['query_parameters']);

    // Check that the default value is populated into the sub-element.
    $this->assertSame('cancer', $processed['query_parameters']['query__cond']['#default_value']);

    // Check that filter sub-elements are built.
    $this->assertArrayHasKey('filters', $processed);
    $this->assertArrayHasKey('filter__overallStatus', $processed['filters']);
    $this->assertSame('RECRUITING', $processed['filters']['filter__overallStatus']['#default_value']);

    // Check that pagination sub-elements are built.
    $this->assertArrayHasKey('pagination', $processed);

    // Simulate submission: set #value on each relevant sub-element.
    $processed['query_parameters']['query__cond']['#value'] = 'cancer';
    $processed['filters']['filter__overallStatus']['#value'] = 'RECRUITING';
    $processed['pagination']['pageSize']['#value'] = '';

    ClinicalTrialsGovStudiesQuery::validateStudiesQuery($processed, $form_state, $complete_form);

    $assembled = $form_state->getValue(['studies_query']);

    // Check that non-empty values are assembled into the query string.
    $this->assertStringContainsString('query.cond=cancer', $assembled);
    $this->assertStringContainsString('filter.overallStatus=RECRUITING', $assembled);

    // Check that empty values are omitted.
    $this->assertStringNotContainsString('pageSize', $assembled);
  }

  /**
   * Tests that parseQueryString() preserves dot-notation keys.
   */
  public function testParseQueryStringPreservesDots(): void {
    $result = ClinicalTrialsGovStudiesQuery::parseQueryString(
      'query.cond=heart+disease&filter.overallStatus=RECRUITING&pageSize=20'
    );

    // Check that dot-notation keys survive the parse (unlike parse_str).
    $this->assertArrayHasKey('query.cond', $result);
    $this->assertSame('heart disease', $result['query.cond']);
    $this->assertArrayHasKey('filter.overallStatus', $result);
    $this->assertArrayHasKey('pageSize', $result);
    $this->assertSame('20', $result['pageSize']);
  }

  /**
   * Tests that an empty query string returns an empty array.
   */
  public function testParseQueryStringReturnsEmptyForEmptyInput(): void {
    // Check that an empty string produces an empty array.
    $this->assertSame([], ClinicalTrialsGovStudiesQuery::parseQueryString(''));
  }

}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovStudiesQueryTest.php
```

Expected: FAIL — class `ClinicalTrialsGovStudiesQuery` not found.

- [ ] **Step 3: Implement the form element**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Composite form element for the ClinicalTrials.gov /studies query interface.
 *
 * Accepts a raw query string as #default_value and returns a raw query string
 * from its #element_validate callback.
 *
 * Usage:
 * @code
 * $form['query'] = [
 *   '#type' => 'clinical_trials_gov_studies_query',
 *   '#default_value' => 'query.cond=cancer&filter.overallStatus=RECRUITING',
 * ];
 * @endcode
 *
 * @FormElement("clinical_trials_gov_studies_query")
 */
class ClinicalTrialsGovStudiesQuery extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#input' => TRUE,
      '#default_value' => '',
      '#process' => [[static::class, 'processStudiesQuery']],
      '#element_validate' => [[static::class, 'validateStudiesQuery']],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Parses a query string while preserving dot-notation keys.
   *
   * PHP's parse_str() and $_GET convert dots to underscores. This method
   * splits on '&' and '=' manually to preserve keys like 'query.cond'.
   */
  public static function parseQueryString(string $query_string): array {
    $result = [];
    if ($query_string === '') {
      return $result;
    }
    foreach (explode('&', $query_string) as $pair) {
      [$raw_key, $raw_value] = array_pad(explode('=', $pair, 2), 2, '');
      $key = urldecode($raw_key);
      if ($key !== '') {
        $result[$key] = urldecode($raw_value);
      }
    }
    return $result;
  }

  /**
   * Converts an API key like 'query.cond' to a safe element name 'query__cond'.
   */
  public static function apiKeyToElementName(string $key): string {
    return str_replace('.', '__', $key);
  }

  /**
   * Converts an element name 'query__cond' back to an API key 'query.cond'.
   */
  public static function elementNameToApiKey(string $name): string {
    return str_replace('__', '.', $name);
  }

  /**
   * Builds sub-elements from the query string #default_value.
   */
  public static function processStudiesQuery(
    array $element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): array {
    $defaults = static::parseQueryString($element['#default_value'] ?? '');
    $manager = \Drupal::service('clinical_trials_gov.manager');

    $query_keys = [
      'query.cond', 'query.term', 'query.locn', 'query.titles',
      'query.intr', 'query.outc', 'query.spons', 'query.lead', 'query.id',
    ];
    $filter_keys = ['filter.overallStatus', 'filter.geo', 'filter.ids', 'filter.advanced', 'aggFilters'];
    $pagination_keys = ['pageSize', 'pageToken', 'countTotal', 'sort'];

    $element['query_parameters'] = [
      '#type' => 'details',
      '#title' => t('Query parameters'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($query_keys))),
    ];
    foreach (static::queryParameterDefinitions() as $definition) {
      $name = static::apiKeyToElementName($definition['key']);
      $element['query_parameters'][$name] = static::buildTextField(
        $definition['label'],
        $definition['description'],
        $defaults[$definition['key']] ?? '',
      );
    }

    $element['filters'] = [
      '#type' => 'details',
      '#title' => t('Filters'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($filter_keys))),
    ];
    $overall_status_options = ['' => t('- Any -')];
    foreach ($manager->getEnum('OverallStatus') as $status) {
      $overall_status_options[$status] = $status;
    }
    $element['filters']['filter__overallStatus'] = [
      '#type' => 'select',
      '#title' => t('Overall status'),
      '#description' => t('See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#options' => $overall_status_options,
      '#default_value' => $defaults['filter.overallStatus'] ?? '',
    ];
    $element['filters']['filter__geo'] = static::buildTextField('Geographic filter', 'e.g. distance(39.0035,-77.1088,50mi)', $defaults['filter.geo'] ?? '');
    $element['filters']['filter__ids'] = static::buildTextField('NCT ID filter', 'Pipe-separated NCT IDs', $defaults['filter.ids'] ?? '');
    $element['filters']['filter__advanced'] = static::buildTextField('Advanced filter', 'Essie expression syntax', $defaults['filter.advanced'] ?? '');
    $element['filters']['aggFilters'] = static::buildTextField('Aggregation filters', 'e.g. phase:phase2,studyType:int', $defaults['aggFilters'] ?? '');

    $element['pagination'] = [
      '#type' => 'details',
      '#title' => t('Pagination and sort'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($pagination_keys))),
    ];
    $element['pagination']['pageSize'] = [
      '#type' => 'number',
      '#title' => t('Page size'),
      '#description' => t('Results per page (1–1000). Default: 10. See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#min' => 1,
      '#max' => 1000,
      '#default_value' => $defaults['pageSize'] ?? '',
    ];
    $element['pagination']['pageToken'] = static::buildTextField('Page token', 'Pagination cursor from previous response', $defaults['pageToken'] ?? '');
    $element['pagination']['countTotal'] = [
      '#type' => 'select',
      '#title' => t('Count total'),
      '#description' => t('Include total match count in response. See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#options' => ['' => t('- Default -'), 'true' => t('Yes'), 'false' => t('No')],
      '#default_value' => $defaults['countTotal'] ?? '',
    ];
    $element['pagination']['sort'] = static::buildTextField('Sort', 'Field and direction, e.g. LastUpdatePostDate:desc', $defaults['sort'] ?? '');

    return $element;
  }

  /**
   * Assembles sub-element values into a query string and sets it on $form_state.
   */
  public static function validateStudiesQuery(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): void {
    $parts = [];
    foreach (['query_parameters', 'filters', 'pagination'] as $group) {
      if (!isset($element[$group])) {
        continue;
      }
      foreach ($element[$group] as $name => $child) {
        if (!is_array($child) || !array_key_exists('#value', $child)) {
          continue;
        }
        $value = trim((string) $child['#value']);
        if ($value === '') {
          continue;
        }
        $parts[] = rawurlencode(static::elementNameToApiKey($name)) . '=' . rawurlencode($value);
      }
    }
    $form_state->setValueForElement($element, implode('&', $parts));
  }

  /**
   * Returns the ordered definitions for the query parameter group.
   */
  protected static function queryParameterDefinitions(): array {
    return [
      ['key' => 'query.cond',   'label' => 'Condition or disease',     'description' => 'e.g. cancer, heart disease'],
      ['key' => 'query.term',   'label' => 'Other search terms',        'description' => 'Full-text search across all fields'],
      ['key' => 'query.locn',   'label' => 'Location terms',            'description' => 'e.g. Boston, MA'],
      ['key' => 'query.titles', 'label' => 'Title or acronym',          'description' => ''],
      ['key' => 'query.intr',   'label' => 'Intervention or treatment', 'description' => ''],
      ['key' => 'query.outc',   'label' => 'Outcome measure',           'description' => ''],
      ['key' => 'query.spons',  'label' => 'Sponsor or collaborator',   'description' => ''],
      ['key' => 'query.lead',   'label' => 'Lead sponsor',              'description' => ''],
      ['key' => 'query.id',     'label' => 'NCT number or study ID',    'description' => 'e.g. NCT04001699'],
    ];
  }

  /**
   * Builds a textfield sub-element with a link to the API documentation.
   */
  protected static function buildTextField(string $label, string $description, string $default_value): array {
    $description_text = $description
      ? t('@description. See <a href=":url">API documentation</a>.', [
        '@description' => $description,
        ':url' => 'https://clinicaltrials.gov/data-api/api',
      ])
      : t('See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']);
    return [
      '#type' => 'textfield',
      '#title' => t('@label', ['@label' => $label]),
      '#description' => $description_text,
      '#default_value' => $default_value,
    ];
  }

}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovStudiesQueryTest.php
```

Expected: 3 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/src/Element/ClinicalTrialsGovStudiesQuery.php \
        web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovStudiesQueryTest.php
git commit -m "feat: add ClinicalTrialsGovStudiesQuery form element with kernel tests"
```

---

## Task 7: Report submodule scaffold and search form

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/clinical_trials_gov_report.info.yml`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/clinical_trials_gov_report.routing.yml`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/clinical_trials_gov_report.links.menu.yml`
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Form/ClinicalTrialsGovStudiesSearchForm.php`

- [ ] **Step 1: Create the module info file**

```yaml
# clinical_trials_gov_report.info.yml
name: 'ClinicalTrials.gov Report'
type: module
description: 'Admin report for browsing ClinicalTrials.gov studies.'
package: Custom
core_version_requirement: ^10.3 || ^11
dependencies:
  - clinical_trials_gov:clinical_trials_gov
```

- [ ] **Step 2: Create routing.yml**

```yaml
# clinical_trials_gov_report.routing.yml
clinical_trials_gov_report.studies:
  path: /admin/reports/status/clinical-trials-gov
  defaults:
    _controller: '\Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportController::index'
    _title: 'ClinicalTrials.gov'
  requirements:
    _permission: 'access administration pages'

clinical_trials_gov_report.study:
  path: /admin/reports/status/clinical-trials-gov/{nctId}
  defaults:
    _controller: '\Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovStudyController::view'
    _title_callback: '\Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovStudyController::title'
  requirements:
    _permission: 'access administration pages'
    nctId: 'NCT\d+'
```

- [ ] **Step 3: Create links.menu.yml**

```yaml
# clinical_trials_gov_report.links.menu.yml
clinical_trials_gov_report.studies:
  title: 'ClinicalTrials.gov'
  description: 'Browse ClinicalTrials.gov clinical trial data.'
  route_name: clinical_trials_gov_report.studies
  parent: system.admin_reports
```

- [ ] **Step 4: Create the search form**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Search form for the ClinicalTrials.gov report.
 *
 * Contains a single ClinicalTrialsGovStudiesQuery element. On submit,
 * redirects to the studies report route with the assembled query string
 * as URL parameters.
 */
class ClinicalTrialsGovStudiesSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_studies_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['studies_query'] = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => $this->getRequest()->getQueryString() ?? '',
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query_string = (string) $form_state->getValue('studies_query');
    $parameters = \Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery::parseQueryString($query_string);
    $form_state->setRedirect('clinical_trials_gov_report.studies', [], ['query' => $parameters]);
  }

}
```

- [ ] **Step 5: Enable the submodule to verify routing**

```bash
ddev drush en clinical_trials_gov_report && ddev drush cr
```

- [ ] **Step 6: Visit the report page to verify it loads**

```bash
ddev uli
```

Open the one-time login link, then visit `/admin/reports/status/clinical-trials-gov`. Expected: a page titled "ClinicalTrials.gov" with no fatal errors (controllers not yet implemented — a 404 or empty page is fine at this stage).

- [ ] **Step 7: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/
git commit -m "feat: scaffold clinical_trials_gov_report with routing and search form"
```

---

## Task 8: ClinicalTrialsGovReportController

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovReportController.php`

- [ ] **Step 1: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\clinical_trials_gov_report\Form\ClinicalTrialsGovStudiesSearchForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders the ClinicalTrials.gov studies list report.
 */
class ClinicalTrialsGovReportController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * Renders the studies list page with the search form and results table.
   */
  public function index(Request $request): array {
    $build = [];
    $build['search_form'] = $this->formBuilder()->getForm(ClinicalTrialsGovStudiesSearchForm::class);

    $query_string = $request->getQueryString() ?? '';
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query_string);

    if (empty($parameters)) {
      return $build;
    }

    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];

    if (empty($studies)) {
      $build['empty'] = [
        '#type' => 'item',
        '#markup' => $this->t('No studies found.'),
      ];
      return $build;
    }

    $build['results'] = $this->buildResultsTable($studies);

    if (isset($response['totalCount'])) {
      $build['total'] = [
        '#type' => 'item',
        '#markup' => $this->t('Total results: @count', ['@count' => $response['totalCount']]),
      ];
    }

    if (isset($response['nextPageToken'])) {
      $next_parameters = $parameters;
      $next_parameters['pageToken'] = $response['nextPageToken'];
      $build['pager'] = [
        '#type' => 'item',
        '#markup' => $this->t('<a href=":url">Next page</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov_report.studies', [], ['query' => $next_parameters])->toString(),
        ]),
      ];
    }

    return $build;
  }

  /**
   * Builds the results table from a raw studies array.
   */
  protected function buildResultsTable(array $studies): array {
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

      $nct_link = $nct_id
        ? $this->t('<a href=":url">@nct</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov_report.study', ['nctId' => $nct_id])->toString(),
          '@nct' => $nct_id,
        ])
        : '';

      $rows[] = [$nct_link, $title, $status, $phases, $conditions];
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

}
```

- [ ] **Step 2: Visit the report and verify the form and results render**

```bash
ddev drush cr
```

Visit `/admin/reports/status/clinical-trials-gov` while logged in as admin. Submit the form with `query.cond=cancer`. Expected: a table of results with NCT ID links.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovReportController.php
git commit -m "feat: add ClinicalTrialsGovReportController"
```

---

## Task 9: ClinicalTrialsGovStudyController

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovStudyController.php`

- [ ] **Step 1: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the detail page for a single ClinicalTrials.gov study.
 */
class ClinicalTrialsGovStudyController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
  ) {}

  /**
   * Renders the study detail page.
   *
   * @param string $nctId
   *   The NCT ID from the route.
   */
  public function view(string $nctId): array {
    $study = $this->manager->getStudy($nctId);

    if (empty($study)) {
      return [
        '#type' => 'item',
        '#markup' => $this->t('Study @nct_id not found.', ['@nct_id' => $nctId]),
      ];
    }

    return $this->builder->buildStudy($study);
  }

  /**
   * Returns the page title from the study's brief title.
   *
   * @param string $nctId
   *   The NCT ID from the route.
   */
  public function title(string $nctId): string {
    $study = $this->manager->getStudy($nctId);
    return (string) ($study['protocolSection.identificationModule.briefTitle'] ?? $nctId);
  }

}
```

- [ ] **Step 2: Clear cache and verify the study detail page loads**

```bash
ddev drush cr
```

From the results table, click a linked NCT ID. Expected: a study detail page with nested `<details>` elements and a collapsed Raw data section at the bottom.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/src/Controller/ClinicalTrialsGovStudyController.php
git commit -m "feat: add ClinicalTrialsGovStudyController"
```

---

## Task 10: Functional test

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/tests/src/Functional/ClinicalTrialsGovReportTest.php`

- [ ] **Step 1: Write the functional test**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the ClinicalTrials.gov report.
 *
 * Uses clinical_trials_gov_test to replace the live API manager with a stub,
 * ensuring tests are deterministic and require no network access.
 *
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
#[RunTestsInSeparateProcesses]
class ClinicalTrialsGovReportTest extends BrowserTestBase {

  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'clinical_trials_gov_report',
  ];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
  }

  /**
   * Tests the full report flow: list page, search, and study detail.
   *
   * A single test method covers the whole flow so the Drupal install runs once.
   */
  public function testReportFlow(): void {
    // Check that the report page loads with the search form.
    $this->drupalGet('admin/reports/status/clinical-trials-gov');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'form');
    $this->assertSession()->fieldExists('studies_query[query_parameters][query__cond]');

    // Check that submitting the form shows a results table.
    $this->submitForm([
      'studies_query[query_parameters][query__cond]' => 'cancer',
    ], 'Search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');

    // Check that an NCT ID link is present in the results.
    // The stub returns fixture studies — look for any NCT link.
    $nct_link = $this->getSession()->getPage()->find('css', 'table a[href*="clinical-trials-gov/NCT"]');
    $this->assertNotNull($nct_link, 'An NCT ID link should appear in the results table.');

    // Check that following an NCT link loads the study detail page.
    $nct_link->click();
    $this->assertSession()->statusCodeEquals(200);

    // Check that the study detail page contains at least one details element.
    $this->assertSession()->elementExists('css', 'details');

    // Check that the raw data details widget is present.
    $this->assertSession()->pageTextContains('Raw data');
  }

}
```

- [ ] **Step 2: Run the functional test**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/tests/src/Functional/ClinicalTrialsGovReportTest.php
```

Expected: 1 test, OK. Functional tests are slow (30–60 s) — this is normal.

- [ ] **Step 3: Run the full module test suite to confirm nothing is broken**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/
```

Expected: all tests pass, no failures.

- [ ] **Step 4: Run code review**

```bash
ddev code-review web/modules/custom/clinical_trials_gov/
```

Fix any issues, then re-run to confirm clean.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_report/tests/
git commit -m "test: add ClinicalTrialsGovReportTest functional test"
```

---

## Task 11: Code fix and final verification

- [ ] **Step 1: Auto-fix formatting**

```bash
ddev code-fix web/modules/custom/clinical_trials_gov/
```

- [ ] **Step 2: Re-run full code review**

```bash
ddev code-review web/modules/custom/clinical_trials_gov/
```

Expected: no errors or warnings.

- [ ] **Step 3: Re-run the full test suite**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/
```

Expected: all tests pass.

- [ ] **Step 4: Smoke-test the live UI**

Log in as admin and perform the following:
1. Visit `/admin/reports/status/clinical-trials-gov` — form renders.
2. Search for `query.cond=cancer&countTotal=true` — results table shows with total count.
3. Click an NCT ID link — study detail page renders with nested details and a collapsed Raw data section.
4. Expand Raw data — table shows field path, name, piece, title, source type, and value columns.

- [ ] **Step 5: Final commit**

```bash
git add -p   # review and stage any remaining formatting changes
git commit -m "chore: apply code style fixes to clinical_trials_gov modules"
```
