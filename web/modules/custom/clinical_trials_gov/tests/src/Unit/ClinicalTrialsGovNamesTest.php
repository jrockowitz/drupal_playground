<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovNames;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovNames.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovNames
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovNamesTest extends UnitTestCase {

  /**
   * The mocked ClinicalTrials.gov manager.
   */
  protected ClinicalTrialsGovManagerInterface $manager;

  /**
   * The names service under test.
   */
  protected ClinicalTrialsGovNames $names;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->createMock(ClinicalTrialsGovManagerInterface::class);
    $this->names = new ClinicalTrialsGovNames($this->manager);
  }

  /**
   * Tests field names are normalized from ClinicalTrials.gov pieces.
   *
   * @covers ::getFieldName
   */
  public function testGetFieldName(): void {
    // Check that hard-coded overrides are respected.
    $this->assertSame('field_nct_id_alias', $this->names->getFieldName('NCTIdAlias'));

    // Check that non-overridden names are normalized to snake case.
    $this->assertSame('field_responsible_party', $this->names->getFieldName('ResponsibleParty'));
  }

  /**
   * Tests group names are normalized from ClinicalTrials.gov pieces.
   *
   * @covers ::getGroupName
   */
  public function testGetGroupName(): void {
    // Check that group names are prefixed and normalized.
    $this->assertSame('group_location', $this->names->getGroupName('Location'));
  }

  /**
   * Tests display labels prefer metadata titles when they are available.
   *
   * @covers ::getDisplayLabel
   */
  public function testGetDisplayLabelPrefersMetadataTitle(): void {
    $this->manager
      ->expects($this->once())
      ->method('getMetadataByPiece')
      ->willReturn([
        'ResponsibleParty' => [
          'piece' => 'ResponsibleParty',
          'title' => 'Responsible Party',
        ],
      ]);

    // Check that metadata titles take precedence over generated labels.
    $this->assertSame('Responsible Party', $this->names->getDisplayLabel('ResponsibleParty'));
  }

  /**
   * Tests display labels fall back to piece normalization.
   *
   * @covers ::getDisplayLabel
   */
  public function testGetDisplayLabelFallsBackToPiece(): void {
    $this->manager
      ->expects($this->once())
      ->method('getMetadataByPiece')
      ->willReturn([]);

    // Check that camel case is split into human-readable words.
    $this->assertSame('Protocol Section', $this->names->getDisplayLabel('ProtocolSection'));
  }

  /**
   * Tests detail labels trim the parent piece before normalization.
   *
   * @covers ::getDetailLabel
   */
  public function testGetDetailLabel(): void {
    // Check that the parent piece prefix is trimmed from the child piece.
    $this->assertSame('investigator_full_name', $this->names->getDetailLabel('ResponsiblePartyInvestigatorFullName', 'ResponsibleParty'));

    // Check that labels still normalize when there is no parent piece.
    $this->assertSame('type', $this->names->getDetailLabel('Type'));
  }

}
