<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ClinicalTrialsGovConfigForm helper methods.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\Form\ClinicalTrialsGovConfigForm
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovConfigFormTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface|MockObject $entityTypeManager;

  /**
   * The mocked ClinicalTrials.gov paths manager.
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ClinicalTrialsGovPathsManagerInterface|MockObject $pathsManager;

  /**
   * The mocked ClinicalTrials.gov field manager.
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ClinicalTrialsGovFieldManagerInterface|MockObject $fieldManager;

  /**
   * The mocked ClinicalTrials.gov entity manager.
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ClinicalTrialsGovEntityManagerInterface|MockObject $entityManager;

  /**
   * The mocked ClinicalTrials.gov migration manager.
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ClinicalTrialsGovMigrationManagerInterface|MockObject $migrationManager;

  /**
   * The form under test.
   */
  protected TestClinicalTrialsGovConfigForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->pathsManager = $this->createMock(ClinicalTrialsGovPathsManagerInterface::class);
    $this->fieldManager = $this->createMock(ClinicalTrialsGovFieldManagerInterface::class);
    $this->entityManager = $this->createMock(ClinicalTrialsGovEntityManagerInterface::class);
    $this->migrationManager = $this->createMock(ClinicalTrialsGovMigrationManagerInterface::class);

    $this->form = new TestClinicalTrialsGovConfigForm(
      $this->entityTypeManager,
      $this->pathsManager,
      $this->fieldManager,
      $this->entityManager,
      $this->migrationManager,
    );
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
   * Tests that the configure cells preserve indent attributes in render arrays.
   *
   * @covers ::buildLabelCell
   * @covers ::buildFieldNameCell
   * @covers ::buildPieceMarkup
   */
  public function testIndentedCellsUseRenderArrayAttributes(): void {
    $label_cell = $this->form->exposedBuildLabelCell('Responsible Party', 'Description', 2);
    $field_name_cell = $this->form->exposedBuildFieldNameCell('field_responsible_party', ['type', 'inv_full_name'], 2);
    $piece_markup = $this->form->exposedBuildPieceMarkup([
      'piece' => 'ResponsibleParty',
      'details' => ['Type', 'InvestigatorFullName'],
    ], 'protocolSection.sponsorCollaboratorsModule.responsibleParty');

    // Check that the wrapper style survives as a real render-array attribute.
    $this->assertSame('padding-left: 3rem;', $label_cell['#attributes']['style']);
    $this->assertSame('padding-left: 3rem;', $field_name_cell['#attributes']['style']);

    // Check that the field-name details remain snake case.
    $this->assertSame('type', $field_name_cell['details']['#items'][0]['#value']);
    $this->assertSame('inv_full_name', $field_name_cell['details']['#items'][1]['#value']);

    // Check that the piece markup preserves ClinicalTrials.gov piece casing.
    $this->assertSame('container', $piece_markup['#type']);
    $this->assertSame('ResponsibleParty', $piece_markup['piece']['#value']);
    $this->assertSame('protocolSection.sponsorCollaboratorsModule.responsibleParty', $piece_markup['path']['#value']);
    $this->assertSame('item_list', $piece_markup['details']['#theme']);
    $this->assertSame('Type', $piece_markup['details']['#items'][0]['#value']);
    $this->assertSame('InvestigatorFullName', $piece_markup['details']['#items'][1]['#value']);
  }

  /**
   * Tests hasRequiredDescendant() detects required child paths.
   *
   * @covers ::hasRequiredDescendant
   */
  public function testHasRequiredDescendant(): void {
    $definitions = [
      'protocolSection.eligibilityModule' => [
        'group_only' => TRUE,
        'required' => FALSE,
      ],
      'protocolSection.eligibilityModule.eligibilityCriteria' => [
        'required' => TRUE,
      ],
      'protocolSection.eligibilityModule.studyPopulation' => [
        'required' => FALSE,
      ],
      'protocolSection.designModule' => [
        'group_only' => TRUE,
        'required' => FALSE,
      ],
      'protocolSection.designModule.studyType' => [
        'required' => FALSE,
      ],
    ];

    // Check that a required child path marks the parent as required.
    $this->assertTrue(
      $this->form->exposedHasRequiredDescendant('protocolSection.eligibilityModule', $definitions)
    );

    // Check that a branch with no required children stays optional.
    $this->assertFalse(
      $this->form->exposedHasRequiredDescendant('protocolSection.designModule', $definitions)
    );

    // Check that sibling paths are not treated as descendants.
    $this->assertFalse(
      $this->form->exposedHasRequiredDescendant('protocolSection.eligibility', $definitions)
    );
  }

}
