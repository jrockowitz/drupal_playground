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
   * Tests that query discovery scans the first 250 recent studies directly.
   *
   * @covers ::discoverQueryPaths
   */
  public function testDiscoverQueryPathsUsesRecentStudiesResponse(): void {
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
      ->with([
        'query.cond' => 'lung',
        'pageSize' => 1000,
        'sort' => 'LastUpdatePostDate:desc',
      ])
      ->willReturn([
        'studies' => [
          [
            'protocolSection' => [
              'identificationModule' => [
                'nctId' => 'NCT001',
              ],
            ],
          ],
          [
            'protocolSection' => [
              'identificationModule' => [
                'briefTitle' => 'Trial',
              ],
            ],
          ],
        ],
      ]);
    $study_manager
      ->expects($this->never())
      ->method('getStudy');
    $study_manager
      ->method('getMetadataByPath')
      ->willReturn([
        'protocolSection' => [],
        'protocolSection.identificationModule' => [],
        'protocolSection.identificationModule.nctId' => [],
        'protocolSection.identificationModule.briefTitle' => [],
      ]);

    $paths_manager = new ClinicalTrialsGovPathsManager($config_factory, $study_manager);

    $paths = $paths_manager->discoverQueryPaths('query.cond=lung');

    // Check that response paths are discovered and normalized directly.
    $this->assertContains('protocolSection.identificationModule.nctId', $paths);
    $this->assertContains('protocolSection.identificationModule.briefTitle', $paths);
  }

}
