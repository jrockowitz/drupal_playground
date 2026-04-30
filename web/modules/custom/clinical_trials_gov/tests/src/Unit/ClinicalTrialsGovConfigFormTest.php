<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovConfigForm helper methods.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\Form\ClinicalTrialsGovConfigForm
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovConfigFormTest extends UnitTestCase {

  /**
   * The form under test.
   */
  protected TestClinicalTrialsGovConfigForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $field_manager = $this->createMock(ClinicalTrialsGovFieldManagerInterface::class);
    $entity_manager = $this->createMock(ClinicalTrialsGovEntityManagerInterface::class);
    $migration_manager = $this->createMock(ClinicalTrialsGovMigrationManagerInterface::class);

    $this->form = new TestClinicalTrialsGovConfigForm($entity_type_manager, $field_manager, $entity_manager, $migration_manager);
  }

  /**
   * Tests that hierarchy depth uses the full dotted metadata path.
   *
   * @covers ::calculateHierarchyDepth
   */
  public function testCalculateHierarchyDepthUsesFullPathDepth(): void {
    // Check that top-level rows stay unindented.
    $this->assertSame(0, $this->form->exposedCalculateHierarchyDepth('protocolSection'));

    // Check that nested rows use the full dotted path depth.
    $this->assertSame(2, $this->form->exposedCalculateHierarchyDepth('protocolSection.statusModule.overallStatus'));
    $this->assertSame(2, $this->form->exposedCalculateHierarchyDepth('protocolSection.sponsorCollaboratorsModule.responsibleParty'));
  }

  /**
   * Tests that indent spacing matches the reports.
   *
   * @covers ::buildIndentStyle
   */
  public function testBuildIndentStyleUsesReportSpacing(): void {
    // Check that top-level rows do not get inline padding.
    $this->assertSame('', $this->form->exposedBuildIndentStyle(0));

    // Check that nested rows use 1.5rem increments.
    $this->assertSame(' style="padding-left: 3rem;"', $this->form->exposedBuildIndentStyle(2));
    $this->assertSame(' style="padding-left: 4.5rem;"', $this->form->exposedBuildIndentStyle(3));
  }

  /**
   * Tests that the configure cells preserve indent attributes in render arrays.
   *
   * @covers ::buildLabelCell
   * @covers ::buildFieldNameCell
   */
  public function testIndentedCellsUseRenderArrayAttributes(): void {
    $label_cell = $this->form->exposedBuildLabelCell('Responsible Party', 'Description', 2);
    $field_name_cell = $this->form->exposedBuildFieldNameCell('field_responsible_party', ['type'], 2);

    // Check that the wrapper style survives as a real render-array attribute.
    $this->assertSame('padding-left: 3rem;', $label_cell['#attributes']['style']);
    $this->assertSame('padding-left: 3rem;', $field_name_cell['#attributes']['style']);
  }

}
