<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovNames;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

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
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ClinicalTrialsGovManagerInterface|MockObject $manager;

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
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->getMockBuilder(ImmutableConfig::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $config
      ->method('get')
      ->with('field_prefix')
      ->willReturn('trial_version_holder');
    $config_factory
      ->method('get')
      ->with('clinical_trials_gov.settings')
      ->willReturn($config);

    $this->names = new ClinicalTrialsGovNames($this->manager, $config_factory);
  }

  /**
   * Tests field names are normalized from ClinicalTrials.gov pieces.
   *
   * @covers ::getFieldName
   */
  public function testGetFieldName(): void {
    // Check that hard-coded overrides are respected.
    $this->assertSame('field_trial_version_hol_0591be62', $this->names->getFieldName('NCTIdAlias'));

    // Check that non-overridden names are normalized to snake case.
    $this->assertSame('field_trial_version_hol_fe6cf9e5', $this->names->getFieldName('ResponsibleParty'));
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
    $this->assertSame('inv_full_name', $this->names->getDetailLabel('ResponsiblePartyInvestigatorFullName', 'ResponsibleParty'));

    // Check that labels still normalize when there is no parent piece.
    $this->assertSame('type', $this->names->getDetailLabel('Type'));
  }

  /**
   * Tests normalized pieces apply seeded abbreviations by token.
   *
   * @covers ::normalizePiece
   */
  public function testNormalizePieceAppliesAbbreviations(): void {
    // Check that seeded abbreviations shorten long normalized tokens.
    $this->assertSame('res_first_submit_qc_dt', $this->names->normalizePiece('ResultsFirstSubmitQCDate'));
  }

  /**
   * Tests abbreviations only apply to whole underscore-delimited tokens.
   *
   * @covers ::normalizePiece
   */
  public function testNormalizePieceOnlyAbbreviatesWholeTokens(): void {
    // Check that partial substrings are not abbreviated.
    $this->assertSame('measured_value', $this->names->normalizePiece('MeasuredValue'));
  }

  /**
   * Tests abbreviation tokens are not abbreviated twice.
   *
   * @covers ::normalizePiece
   */
  public function testNormalizePieceDoesNotDoubleAbbreviate(): void {
    // Check that an existing abbreviation token is left intact.
    $this->assertSame('base_desc', $this->names->normalizePiece('BaseDesc'));
  }

}
