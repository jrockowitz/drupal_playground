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

  /**
   * The system under test.
   */
  protected ClinicalTrialsGovManager $manager;

  /**
   * The mocked API.
   */
  protected ClinicalTrialsGovApiInterface $api;

  /**
   * {@inheritdoc}
   */
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
   * Tests that getVersion() returns the raw version response unchanged.
   *
   * @covers ::getVersion
   */
  public function testGetVersionReturnsRawResponse(): void {
    $response = [
      'apiVersion' => '2.0.5',
      'dataTimestamp' => '2026-04-24T09:00:05',
    ];
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/version')
      ->willReturn($response);

    $result = $this->manager->getVersion();

    // Check that the raw version response is returned with no transformation.
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
   * Tests that getMetadataByPath() flattens the metadata tree.
   *
   * @covers ::getMetadataByPath
   */
  public function testGetMetadataByPathFlattensTree(): void {
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

    $result = $this->manager->getMetadataByPath();

    // Check that the top-level section is keyed by its name.
    $this->assertArrayHasKey('protocolSection', $result);
    $this->assertSame('STRUCT', $result['protocolSection']['sourceType']);
    $this->assertSame('protocolSection', $result['protocolSection']['path']);
    $this->assertSame('', $result['protocolSection']['parent']);

    // Check that a nested module is keyed by its dotted path.
    $this->assertArrayHasKey('protocolSection.identificationModule', $result);
    $this->assertSame('Identification Module', $result['protocolSection.identificationModule']['title']);
    $this->assertSame('protocolSection', $result['protocolSection.identificationModule']['parent']);

    // Check that children are recorded as dotted paths.
    $this->assertContains('protocolSection.identificationModule', $result['protocolSection']['children']);
  }

  /**
   * Tests that getMetadataByPath() can return one metadata row.
   *
   * @covers ::getMetadataByPath
   */
  public function testGetMetadataByPathReturnsOneMetadataRow(): void {
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
          'children' => [
            [
              'name' => 'identificationModule',
              'piece' => 'Identification',
              'title' => 'Identification Module',
              'type' => 'IdModule',
              'sourceType' => 'STRUCT',
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getMetadataByPath('protocolSection.identificationModule');

    // Check that a single metadata row can be loaded by path.
    $this->assertSame('Identification', $result['piece']);
    $this->assertSame('protocolSection', $result['parent']);
  }

  /**
   * Tests that getMetadataByPath() preserves extended metadata fields.
   *
   * @covers ::getMetadataByPath
   */
  public function testGetMetadataByPathPreservesExtendedMetadataFields(): void {
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
          'dedLink' => [
            'label' => 'Study Protocol',
            'url' => 'https://clinicaltrials.gov/policy/protocol-definitions',
          ],
          'children' => [
            [
              'name' => 'briefTitle',
              'piece' => 'BriefTitle',
              'title' => 'Brief Title',
              'type' => 'text',
              'sourceType' => 'TEXT',
              'rules' => 'Required for INT/OBS/EA.',
              'altPieceNames' => ['BRIEF-TITLE'],
              'synonyms' => TRUE,
              'dedLink' => [
                'label' => 'Brief Title',
                'url' => 'https://clinicaltrials.gov/policy/protocol-definitions#BriefTitle',
              ],
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getMetadataByPath();

    // Check that optional root-level DED link data is preserved.
    $this->assertSame('Study Protocol', $result['protocolSection']['dedLinkLabel']);
    $this->assertSame('https://clinicaltrials.gov/policy/protocol-definitions', $result['protocolSection']['dedLinkUrl']);

    // Check that leaf-level raw metadata fields are preserved.
    $this->assertSame('Required for INT/OBS/EA.', $result['protocolSection.briefTitle']['rules']);
    $this->assertSame(['BRIEF-TITLE'], $result['protocolSection.briefTitle']['altPieceNames']);
    $this->assertTrue($result['protocolSection.briefTitle']['synonyms']);
    $this->assertSame('Brief Title', $result['protocolSection.briefTitle']['dedLinkLabel']);
    $this->assertSame('https://clinicaltrials.gov/policy/protocol-definitions#BriefTitle', $result['protocolSection.briefTitle']['dedLinkUrl']);
  }

  /**
   * Tests that missing extended metadata fields normalize consistently.
   *
   * @covers ::getMetadataByPath
   */
  public function testGetMetadataByPathNormalizesMissingExtendedMetadataFields(): void {
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
          'children' => [
            [
              'name' => 'identificationModule',
              'piece' => 'IdentificationModule',
              'title' => 'Identification Module',
              'type' => 'IdentificationModule',
              'sourceType' => 'STRUCT',
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getMetadataByPath('protocolSection.identificationModule');

    // Check that missing optional raw fields normalize to empty values.
    $this->assertSame('', $result['rules']);
    $this->assertSame([], $result['altPieceNames']);
    $this->assertFalse($result['synonyms']);
    $this->assertSame('', $result['dedLinkLabel']);
    $this->assertSame('', $result['dedLinkUrl']);
  }

  /**
   * Tests that getMetadataByPiece() returns metadata rows keyed by piece.
   *
   * @covers ::getMetadataByPiece
   */
  public function testGetMetadataByPieceReturnsMetadataRowsByPiece(): void {
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
          'children' => [
            [
              'name' => 'identificationModule',
              'piece' => 'Identification',
              'title' => 'Identification Module',
              'type' => 'IdModule',
              'sourceType' => 'STRUCT',
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getMetadataByPiece();

    // Check that the metadata can be looked up by piece.
    $this->assertArrayHasKey('ProtocolSection', $result);
    $this->assertSame('protocolSection', $result['ProtocolSection']['path']);
    $this->assertArrayHasKey('Identification', $result);
    $this->assertSame('protocolSection.identificationModule', $result['Identification']['path']);
  }

  /**
   * Tests that getMetadataByPiece() can return one metadata row.
   *
   * @covers ::getMetadataByPiece
   */
  public function testGetMetadataByPieceReturnsOneMetadataRow(): void {
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
          'children' => [
            [
              'name' => 'identificationModule',
              'piece' => 'Identification',
              'title' => 'Identification Module',
              'type' => 'IdModule',
              'sourceType' => 'STRUCT',
              'children' => [],
            ],
          ],
        ],
      ]);

    $result = $this->manager->getMetadataByPiece('Identification');

    // Check that a single metadata row can be loaded by piece.
    $this->assertSame('protocolSection.identificationModule', $result['path']);
    $this->assertSame('Identification Module', $result['title']);
  }

  /**
   * Tests that getEnum() extracts the value strings from enum objects.
   *
   * @covers ::getEnum
   */
  public function testGetEnumReturnsAllowedValues(): void {
    $this->api
      ->method('get')
      ->with('/studies/enums')
      ->willReturn([
        [
          'type' => 'OverallStatus',
          'values' => [
            ['value' => 'RECRUITING', 'legacyValue' => 'Recruiting'],
            ['value' => 'COMPLETED', 'legacyValue' => 'Completed'],
            ['value' => 'TERMINATED', 'legacyValue' => 'Terminated'],
          ],
        ],
        [
          'type' => 'Phase',
          'values' => [
            ['value' => 'PHASE1', 'legacyValue' => 'Phase 1'],
            ['value' => 'PHASE2', 'legacyValue' => 'Phase 2'],
          ],
        ],
      ]);

    $result = $this->manager->getEnum('OverallStatus');

    // Check that the value strings are extracted from the enum objects.
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

  /**
   * Tests that getEnumAsAllowedValues() returns core-style allowed values.
   *
   * @covers ::getEnumAsAllowedValues
   */
  public function testGetEnumAsAllowedValuesReturnsCoreStyleMap(): void {
    $this->api
      ->method('get')
      ->with('/studies/enums')
      ->willReturn([
        [
          'type' => 'OverallStatus',
          'values' => [
            ['value' => 'RECRUITING', 'legacyValue' => 'Recruiting'],
            ['value' => 'COMPLETED', 'legacyValue' => 'Completed'],
            ['value' => 'UNKNOWN'],
          ],
        ],
      ]);

    $result = $this->manager->getEnumAsAllowedValues('OverallStatus');

    // Check that allowed values use the API value as the key and legacy label.
    $this->assertSame([
      'RECRUITING' => 'Recruiting',
      'COMPLETED' => 'Completed',
      'UNKNOWN' => 'UNKNOWN',
    ], $result);
  }

  /**
   * Tests that getEnumAsAllowedValues() returns custom-field row format.
   *
   * @covers ::getEnumAsAllowedValues
   */
  public function testGetEnumAsAllowedValuesReturnsKeyLabelRows(): void {
    $this->api
      ->method('get')
      ->with('/studies/enums')
      ->willReturn([
        [
          'type' => 'Phase',
          'values' => [
            ['value' => 'PHASE1', 'legacyValue' => 'Phase 1'],
            ['value' => 'PHASE2', 'legacyValue' => 'Phase 2'],
          ],
        ],
      ]);

    $result = $this->manager->getEnumAsAllowedValues('Phase', TRUE);

    // Check that custom field rows are normalized to key/label pairs.
    $this->assertSame([
      [
        'key' => 'PHASE1',
        'label' => 'Phase 1',
      ],
      [
        'key' => 'PHASE2',
        'label' => 'Phase 2',
      ],
    ], $result);
  }

  /**
   * Tests that flattenStudy() keeps the first value when two paths collide.
   *
   * A literal dot in a key (e.g. 'a.b') and a nested structure ('a' => ['b'])
   * both produce the same flattened key. The + operator means the first
   * occurrence wins and the second is silently discarded.
   *
   * @covers ::getStudy
   */
  public function testGetStudyKeyCollisionFirstWins(): void {
    $this->api
      ->expects($this->once())
      ->method('get')
      ->with('/studies/NCT001')
      ->willReturn([
        'a.b' => 'first',
        'a' => ['b' => 'second'],
      ]);

    $result = $this->manager->getStudy('NCT001');

    // Check that the first-encountered value is kept when paths collide.
    $this->assertSame('first', $result['a.b']);
    // Check that no additional keys are present.
    $this->assertCount(1, $result);
  }

}
