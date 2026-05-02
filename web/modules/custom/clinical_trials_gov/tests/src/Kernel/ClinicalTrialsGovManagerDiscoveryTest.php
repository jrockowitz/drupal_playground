<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovPathsManager query discovery helpers.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovManagerDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
  }

  /**
   * Tests that query discovery returns normalized metadata-ordered paths.
   */
  public function testDiscoverQueryPaths(): void {
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface $paths_manager */
    $paths_manager = $this->container->get('clinical_trials_gov.paths_manager');
    /** @var \Drupal\clinical_trials_gov_test\ClinicalTrialsGovStudyManagerStub $study_manager */
    $study_manager = $this->container->get('clinical_trials_gov.study_manager');

    $paths = $paths_manager->discoverQueryPaths('query.cond=lung');

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
    ], $study_manager->getStudiesRequests()[count($study_manager->getStudiesRequests()) - 1]);
    $this->assertSame([], $study_manager->getStudyRequests());
  }

}
