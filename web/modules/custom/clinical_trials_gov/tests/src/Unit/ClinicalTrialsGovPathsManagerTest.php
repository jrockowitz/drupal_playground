<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManager;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovPathsManager.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManager
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovPathsManagerTest extends UnitTestCase {

  /**
   * Tests effective and raw required/query path getters.
   *
   * @covers ::getRequiredPaths
   * @covers ::getRequiredPathsRaw
   * @covers ::getQueryPaths
   * @covers ::getQueryPathsRaw
   */
  public function testPathGettersExpandAndNormalizeConfiguredPaths(): void {
    $study_manager = $this->createMock(ClinicalTrialsGovStudyManagerInterface::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->getMockBuilder(ImmutableConfig::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $config
      ->method('get')
      ->willReturnMap([
        ['required_paths', ['protocolSection.identificationModule.nctId']],
        ['title_path', 'protocolSection.identificationModule.briefTitle'],
        ['query_paths', ['protocolSection.descriptionModule.briefSummary']],
      ]);
    $config_factory
      ->method('get')
      ->with('clinical_trials_gov.settings')
      ->willReturn($config);
    $study_manager
      ->method('getMetadataByPath')
      ->willReturn([
        'protocolSection' => [],
        'protocolSection.identificationModule' => [],
        'protocolSection.identificationModule.nctId' => [],
        'protocolSection.identificationModule.briefTitle' => [],
        'protocolSection.descriptionModule' => [],
        'protocolSection.descriptionModule.briefSummary' => [],
      ]);

    $paths_manager = new ClinicalTrialsGovPathsManager($config_factory, $study_manager);

    // Check that raw getters return stored config values unchanged.
    $this->assertSame(['protocolSection.identificationModule.nctId'], $paths_manager->getRequiredPathsRaw());
    $this->assertSame(['protocolSection.descriptionModule.briefSummary'], $paths_manager->getQueryPathsRaw());

    // Check that effective getters expand ancestors and include title/required paths.
    $this->assertSame([
      'protocolSection',
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.nctId',
    ], $paths_manager->getRequiredPaths());
    $this->assertSame([
      'protocolSection',
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.descriptionModule',
      'protocolSection.descriptionModule.briefSummary',
    ], $paths_manager->getQueryPaths());
  }

  /**
   * Tests that query discovery pauses between study-detail requests.
   *
   * @covers ::discoverQueryPaths
   */
  public function testDiscoverQueryPathsPausesBetweenStudyRequests(): void {
    $study_manager = $this->createMock(ClinicalTrialsGovStudyManagerInterface::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->getMockBuilder(ImmutableConfig::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $config
      ->method('get')
      ->willReturnMap([
        ['required_paths', []],
        ['title_path', 'protocolSection.identificationModule.briefTitle'],
        ['query_paths', []],
      ]);
    $config_factory
      ->method('get')
      ->with('clinical_trials_gov.settings')
      ->willReturn($config);

    $study_manager
      ->expects($this->once())
      ->method('getStudies')
      ->willReturn([
        'studies' => [
          ['protocolSection' => ['identificationModule' => ['nctId' => 'NCT001']]],
          ['protocolSection' => ['identificationModule' => ['nctId' => 'NCT002']]],
        ],
      ]);
    $study_manager
      ->expects($this->exactly(2))
      ->method('getStudy')
      ->willReturnMap([
        ['NCT001', ['protocolSection.identificationModule.nctId' => 'NCT001']],
        ['NCT002', ['protocolSection.identificationModule.briefTitle' => 'Trial']],
      ]);
    $study_manager
      ->method('getMetadataByPath')
      ->willReturn([
        'protocolSection' => [],
        'protocolSection.identificationModule' => [],
        'protocolSection.identificationModule.nctId' => [],
        'protocolSection.identificationModule.briefTitle' => [],
      ]);

    $delays = [];
    $paths_manager = new class($config_factory, $study_manager, $delays) extends ClinicalTrialsGovPathsManager {

      /**
       * Constructs a delay-observing paths manager test double.
       */
      public function __construct(
        ConfigFactoryInterface $configFactory,
        ClinicalTrialsGovStudyManagerInterface $studyManager,
        protected array &$delays,
      ) {
        parent::__construct($configFactory, $studyManager);
      }

      /**
       * {@inheritdoc}
       */
      protected function delayBetweenStudyRequests(): void {
        $this->delays[] = TRUE;
      }

    };

    $paths = $paths_manager->discoverQueryPaths('query.cond=lung');

    // Check that both study-detail paths are discovered and normalized.
    $this->assertContains('protocolSection.identificationModule.nctId', $paths);
    $this->assertContains('protocolSection.identificationModule.briefTitle', $paths);

    // Check that one pause happens between the two detail requests.
    $this->assertCount(1, $delays);
  }

}
