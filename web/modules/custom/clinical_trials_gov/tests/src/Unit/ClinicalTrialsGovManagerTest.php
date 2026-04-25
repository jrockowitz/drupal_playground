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
