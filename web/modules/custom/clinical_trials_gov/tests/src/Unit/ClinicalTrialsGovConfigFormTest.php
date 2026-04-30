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

}
