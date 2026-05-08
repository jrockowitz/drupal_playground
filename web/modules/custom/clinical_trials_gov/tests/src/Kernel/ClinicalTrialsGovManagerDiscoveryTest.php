<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\clinical_trials_gov_test\ClinicalTrialsGovStudyManagerStub;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovPathsManager query discovery helpers.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovManagerDiscoveryTest extends ClinicalTrialsGovTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The ClinicalTrials.gov paths manager.
   */
  protected ClinicalTrialsGovPathsManagerInterface $pathsManager;

  /**
   * The stubbed ClinicalTrials.gov study manager.
   */
  protected ClinicalTrialsGovStudyManagerStub $studyManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');

    $this->pathsManager = $this->container->get('clinical_trials_gov.paths_manager');
    $this->studyManager = $this->container->get('clinical_trials_gov.study_manager');
  }

  /**
   * Tests that query discovery returns normalized metadata-ordered paths.
   */
  public function testDiscoverQueryPaths(): void {
    $paths = $this->pathsManager->discoverQueryPaths('query.cond=lung');

    // Check that ancestors, discovered leaves, and required paths are all kept.
    $this->assertContains('protocolSection', $paths);
    $this->assertContains('protocolSection.identificationModule', $paths);
    $this->assertContains('protocolSection.identificationModule.nctId', $paths);
    $this->assertContains('protocolSection.identificationModule.briefTitle', $paths);
    $this->assertContains('protocolSection.descriptionModule.briefSummary', $paths);
    $this->assertContains('protocolSection.contactsLocationsModule.locations', $paths);
    $this->assertContains('protocolSection.contactsLocationsModule.locations.facility', $paths);
    $this->assertNotContains('not.real.path', $paths);

    // Check that the discovery helper scans one recent studies page directly.
    $this->assertSame([
      'query.cond' => 'lung',
      'pageSize' => 1000,
      'sort' => 'LastUpdatePostDate:desc',
    ], $this->studyManager->getStudiesRequests()[count($this->studyManager->getStudiesRequests()) - 1]);
    $this->assertSame([], $this->studyManager->getStudyRequests());
  }

}
