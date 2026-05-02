<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovBuilder helper methods.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
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
    $study_manager = $this->createMock(ClinicalTrialsGovStudyManagerInterface::class);
    $this->builder = new TestClinicalTrialsGovBuilder($study_manager);
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
