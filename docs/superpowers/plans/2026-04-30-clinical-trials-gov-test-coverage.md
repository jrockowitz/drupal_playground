# ClinicalTrials.gov Test Coverage Improvements

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add targeted unit tests that cover unchecked branches in `ClinicalTrialsGovBuilder`, `ClinicalTrialsGovManager`, and `ClinicalTrialsGovConfigForm`.

**Architecture:** All new tests are PHPUnit unit tests (`tests/src/Unit/`) using mocks and testable subclasses that expose protected methods — no Drupal kernel bootstrap needed.

**Tech Stack:** PHPUnit 10, Drupal `UnitTestCase`, `#[Group]` attribute, `#[CoversClass]` attribute, `StringTranslationTrait` stub.

---

### Task 1: Unit tests for `ClinicalTrialsGovBuilder::buildValueElement()`

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovBuilder.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovBuilderTest.php`

The `buildValueElement()` method has 7 branches. None are directly covered by unit tests (the two kernel tests only assert table/row structure at a higher level).

- [ ] **Step 1: Create the testable subclass**

Create `TestClinicalTrialsGovBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder;

/**
 * Testable builder subclass that exposes protected methods.
 */
class TestClinicalTrialsGovBuilder extends ClinicalTrialsGovBuilder {

  /**
   * Exposes buildValueElement() for testing.
   */
  public function exposedBuildValueElement(mixed $value): array {
    return $this->buildValueElement($value);
  }

  /**
   * Exposes normalizeStringList() for testing.
   */
  public function exposedNormalizeStringList(mixed $values): array {
    return $this->normalizeStringList($values);
  }

  /**
   * Exposes buildAssociativeList() for testing.
   */
  public function exposedBuildAssociativeList(array $values): array {
    return $this->buildAssociativeList($values);
  }

}
```

- [ ] **Step 2: Write the failing test**

Create `ClinicalTrialsGovBuilderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder;

/**
 * Unit tests for ClinicalTrialsGovBuilder helper methods.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder
 */
#[Group('clinical_trials_gov')]
#[CoversClass(ClinicalTrialsGovBuilder::class)]
class ClinicalTrialsGovBuilderTest extends UnitTestCase {

  /**
   * The builder under test.
   */
  protected TestClinicalTrialsGovBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $manager = $this->createMock(ClinicalTrialsGovManagerInterface::class);
    $this->builder = new TestClinicalTrialsGovBuilder($manager);
    $this->builder->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests all branches of buildValueElement().
   *
   * @covers ::buildValueElement
   */
  public function testBuildValueElement(): void {
    // Check that NULL renders as the em-dash placeholder.
    $result = $this->builder->exposedBuildValueElement(NULL);
    $this->assertSame('—', $result['#markup']);

    // Check that boolean TRUE renders as "Yes".
    $result = $this->builder->exposedBuildValueElement(TRUE);
    $this->assertStringContainsString('Yes', (string) $result['#markup']);

    // Check that boolean FALSE renders as "No".
    $result = $this->builder->exposedBuildValueElement(FALSE);
    $this->assertStringContainsString('No', (string) $result['#markup']);

    // Check that a plain string passes through escaped markup.
    $result = $this->builder->exposedBuildValueElement('Hello <world>');
    $this->assertStringContainsString('Hello', (string) $result['#markup']);
    $this->assertStringNotContainsString('<world>', (string) $result['#markup']);

    // Check that an empty array renders as the em-dash placeholder.
    $result = $this->builder->exposedBuildValueElement([]);
    $this->assertSame('—', $result['#markup']);

    // Check that a list of scalars becomes an item_list.
    $result = $this->builder->exposedBuildValueElement(['Alpha', 'Beta']);
    $this->assertSame('item_list', $result['#theme']);
    $this->assertSame(['Alpha', 'Beta'], $result['#items']);

    // Check that a list of arrays becomes a nested table.
    $result = $this->builder->exposedBuildValueElement([
      ['name' => 'Alice', 'role' => 'PI'],
      ['name' => 'Bob', 'role' => 'Co-I'],
    ]);
    $this->assertSame('table', $result['#type']);
    $this->assertSame(['name', 'role'], $result['#header']);
    $this->assertCount(2, $result['#rows']);

    // Check that an associative array becomes a label-value item_list.
    $result = $this->builder->exposedBuildValueElement(['status' => 'Active', 'phase' => '2']);
    $this->assertSame('item_list', $result['#theme']);
    $this->assertCount(2, $result['#items']);
  }

  /**
   * Tests normalizeStringList() filters non-scalars and nulls.
   *
   * @covers ::normalizeStringList
   */
  public function testNormalizeStringList(): void {
    // Check that a non-array returns an empty list.
    $this->assertSame([], $this->builder->exposedNormalizeStringList('not-an-array'));

    // Check that null and array entries are filtered out.
    $result = $this->builder->exposedNormalizeStringList(['Alpha', NULL, ['nested'], 'Beta']);
    $this->assertSame(['Alpha', 'Beta'], $result);
  }

}
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovBuilderTest.php
```

Expected: FAIL — `TestClinicalTrialsGovBuilder.php` does not exist yet.

- [ ] **Step 4: Verify the subclass file was also created then re-run**

Both files should be in place. Run again:

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovBuilderTest.php
```

Expected: PASS — all 2 test methods pass.

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovBuilder.php
git add web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovBuilderTest.php
git commit -m "test(clinical_trials_gov): add unit tests for ClinicalTrialsGovBuilder helper methods

AI-assisted by Claude Code."
```

---

### Task 2: Unit tests for `ClinicalTrialsGovManager` caching

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php`

The existing test verifies return values but never checks that a second call does *not* hit the API. The P0 fix (empty-array caching) also has no regression test.

- [ ] **Step 1: Write the failing tests**

Add these test methods to `ClinicalTrialsGovManagerTest.php`, before the closing `}` of the class:

```php
  /**
   * Tests that getStudy() caches and returns the same value on a second call.
   *
   * @covers ::getStudy
   */
  public function testGetStudyCachesResult(): void {
    $raw = ['protocolSection' => ['identificationModule' => ['nctId' => 'NCT001']]];
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies/NCT001')
      ->willReturn($raw);

    // Check that the first and second calls return identical flattened data.
    $first = $this->manager->getStudy('NCT001');
    $second = $this->manager->getStudy('NCT001');
    $this->assertSame($first, $second);
  }

  /**
   * Tests that an empty metadata response is cached and not re-fetched.
   *
   * @covers ::getMetadataByPath
   */
  public function testGetMetadataByPathCachesEmptyResult(): void {
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies/metadata')
      ->willReturn([]);

    // Check that an empty response is stored and the API is not called again.
    $first = $this->manager->getMetadataByPath();
    $second = $this->manager->getMetadataByPath();
    $this->assertSame([], $first);
    $this->assertSame($first, $second);
  }

  /**
   * Tests that an empty enums response is cached and not re-fetched.
   *
   * @covers ::getEnums
   */
  public function testGetEnumsCachesEmptyResult(): void {
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies/enums')
      ->willReturn([]);

    // Check that an empty enums response is stored and the API is not called again.
    $first = $this->manager->getEnums();
    $second = $this->manager->getEnums();
    $this->assertSame([], $first);
    $this->assertSame($first, $second);
  }
```

- [ ] **Step 2: Run the tests to verify they pass**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
```

Expected: PASS — the three new tests plus all 12 existing tests pass.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
git commit -m "test(clinical_trials_gov): add caching regression tests for ClinicalTrialsGovManager

AI-assisted by Claude Code."
```

---

### Task 3: Unit tests for `ClinicalTrialsGovConfigForm` visibility methods

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovConfigForm.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovConfigFormTest.php`

`shouldHideFieldRow()`, `shouldHideEmptyGroupRow()`, and `hasSelectedDescendant()` contain complex traversal logic that is not directly exercised by the existing three unit tests.

- [ ] **Step 1: Extend the testable subclass**

Add these methods to `TestClinicalTrialsGovConfigForm.php` before the closing `}`:

```php
  /**
   * Exposes shouldHideFieldRow() for testing.
   */
  public function exposedShouldHideFieldRow(string $path, array $definitions): bool {
    return $this->shouldHideFieldRow($path, $definitions);
  }

  /**
   * Exposes shouldHideEmptyGroupRow() for testing.
   */
  public function exposedShouldHideEmptyGroupRow(string $path, array $definitions): bool {
    return $this->shouldHideEmptyGroupRow($path, $definitions);
  }

  /**
   * Exposes hasSelectedDescendant() for testing.
   */
  public function exposedHasSelectedDescendant(string $path, array $selected_rows): bool {
    return $this->hasSelectedDescendant($path, $selected_rows);
  }
```

- [ ] **Step 2: Write the failing tests**

Add these test methods to `ClinicalTrialsGovConfigFormTest.php`, before the closing `}`:

```php
  /**
   * Tests shouldHideFieldRow() hides children of non-group custom fields.
   *
   * @covers ::shouldHideFieldRow
   */
  public function testShouldHideFieldRowHidesChildrenOfCustomFields(): void {
    $definitions = [
      'protocolSection.designModule.enrollmentInfo' => [
        'available' => TRUE,
        'field_type' => 'custom',
        'group_only' => FALSE,
      ],
      'protocolSection.designModule.enrollmentInfo.count' => [],
      'protocolSection.statusModule.overallStatus' => [
        'available' => TRUE,
        'field_type' => 'string',
        'group_only' => FALSE,
      ],
    ];

    // Check that a child of a non-group custom field is hidden.
    $this->assertTrue(
      $this->form->exposedShouldHideFieldRow('protocolSection.designModule.enrollmentInfo.count', $definitions)
    );

    // Check that a standalone field is not hidden.
    $this->assertFalse(
      $this->form->exposedShouldHideFieldRow('protocolSection.statusModule.overallStatus', $definitions)
    );

    // Check that the custom field parent itself is not hidden.
    $this->assertFalse(
      $this->form->exposedShouldHideFieldRow('protocolSection.designModule.enrollmentInfo', $definitions)
    );
  }

  /**
   * Tests shouldHideFieldRow() does not hide children of group_only parents.
   *
   * @covers ::shouldHideFieldRow
   */
  public function testShouldHideFieldRowKeepsChildrenOfGroupOnlyParents(): void {
    $definitions = [
      'protocolSection.statusModule' => [
        'available' => TRUE,
        'field_type' => 'custom',
        'group_only' => TRUE,
      ],
      'protocolSection.statusModule.overallStatus' => [],
    ];

    // Check that group_only parents do not cause their children to be hidden.
    $this->assertFalse(
      $this->form->exposedShouldHideFieldRow('protocolSection.statusModule.overallStatus', $definitions)
    );
  }

  /**
   * Tests shouldHideEmptyGroupRow() hides group rows with no visible children.
   *
   * @covers ::shouldHideEmptyGroupRow
   */
  public function testShouldHideEmptyGroupRow(): void {
    $definitions = [
      'protocolSection.statusModule' => ['group_only' => TRUE],
      'protocolSection.statusModule.overallStatus' => ['group_only' => FALSE],
      'protocolSection.emptyGroup' => ['group_only' => TRUE],
    ];

    // Check that a group row with visible children is not hidden.
    $this->assertFalse(
      $this->form->exposedShouldHideEmptyGroupRow('protocolSection.statusModule', $definitions)
    );

    // Check that a group row with no children is hidden.
    $this->assertTrue(
      $this->form->exposedShouldHideEmptyGroupRow('protocolSection.emptyGroup', $definitions)
    );

    // Check that a non-group row is never hidden by this method.
    $this->assertFalse(
      $this->form->exposedShouldHideEmptyGroupRow('protocolSection.statusModule.overallStatus', $definitions)
    );
  }

  /**
   * Tests hasSelectedDescendant() detects selected child paths.
   *
   * @covers ::hasSelectedDescendant
   */
  public function testHasSelectedDescendant(): void {
    $selected_rows = [
      'protocolSection.statusModule.overallStatus' => TRUE,
      'protocolSection.statusModule.startDateStruct' => FALSE,
      'protocolSection.designModule.phases' => TRUE,
    ];

    // Check that a selected child path is detected.
    $this->assertTrue(
      $this->form->exposedHasSelectedDescendant('protocolSection.statusModule', $selected_rows)
    );

    // Check that unselected-only children are not detected.
    $this->assertFalse(
      $this->form->exposedHasSelectedDescendant('protocolSection.referencesModule', $selected_rows)
    );

    // Check that sibling paths are not treated as descendants.
    $this->assertFalse(
      $this->form->exposedHasSelectedDescendant('protocolSection.status', $selected_rows)
    );
  }
```

- [ ] **Step 3: Run the tests to verify they pass**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovConfigFormTest.php
```

Expected: PASS — all 3 existing tests plus the 4 new test methods pass.

- [ ] **Step 4: Commit**

```bash
git add web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovConfigForm.php
git add web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovConfigFormTest.php
git commit -m "test(clinical_trials_gov): add unit tests for ClinicalTrialsGovConfigForm visibility methods

AI-assisted by Claude Code."
```

---

### Task 4: Run full test suite and code review

- [ ] **Step 1: Run all unit tests**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Unit/
```

Expected: PASS — all tests in the Unit directory pass.

- [ ] **Step 2: Run the functional test to verify no regressions**

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
```

Expected: PASS

- [ ] **Step 3: Run code-review on the new test files**

```bash
ddev code-review web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovBuilderTest.php
ddev code-review web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovBuilder.php
ddev code-review web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovConfigFormTest.php
ddev code-review web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovConfigForm.php
ddev code-review web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovManagerTest.php
```

Expected: no errors. Fix any phpcs or phpstan issues before the final commit.

---

## Coverage Summary

| Class | Method | Before | After |
|---|---|---|---|
| `ClinicalTrialsGovBuilder` | `buildValueElement()` — 7 branches | 0 unit | all 7 |
| `ClinicalTrialsGovBuilder` | `normalizeStringList()` | 0 | covered |
| `ClinicalTrialsGovManager` | caching on empty API result | 0 | covered |
| `ClinicalTrialsGovManager` | `getStudy()` cache hit | 0 | covered |
| `ClinicalTrialsGovConfigForm` | `shouldHideFieldRow()` | 0 | 2 scenarios |
| `ClinicalTrialsGovConfigForm` | `shouldHideEmptyGroupRow()` | 0 | 3 scenarios |
| `ClinicalTrialsGovConfigForm` | `hasSelectedDescendant()` | 0 | 3 scenarios |
