<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Batch\ClinicalTrialsGovPathDiscoveryBatch;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovPathDiscoveryBatch.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovPathDiscoveryBatchTest extends KernelTestBase {

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
   * Tests that path discovery scans studies and saves discovered paths.
   */
  public function testDiscoverAndFinish(): void {
    /** @var \Drupal\clinical_trials_gov_test\ClinicalTrialsGovManagerStub $manager */
    $manager = $this->container->get('clinical_trials_gov.manager');

    $context = [];
    do {
      ClinicalTrialsGovPathDiscoveryBatch::discover('query.cond=lung', $context);
    } while (($context['finished'] ?? 0) < 1);
    ClinicalTrialsGovPathDiscoveryBatch::finish(TRUE, $context['results'], []);

    $saved_config = $this->container->get('config.factory')->get('clinical_trials_gov.settings');

    // Check that the discovery batch scans studies and saves discovered paths.
    $this->assertContains('protocolSection', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.identificationModule', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.identificationModule.nctId', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.identificationModule.briefTitle', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.descriptionModule.briefSummary', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.statusModule.overallStatus', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.contactsLocationsModule.locations', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.contactsLocationsModule.locations.facility', $saved_config->get('query_paths'));
    $this->assertSame([
      'query.cond' => 'lung',
      'fields' => 'NCTId',
      'pageSize' => '100',
      'pageToken' => 'page-2',
    ], $manager->getStudiesRequests()[count($manager->getStudiesRequests()) - 1]);
    $this->assertSame([
      'NCT05088187',
      'NCT05189171',
      'NCT01205711',
    ], $manager->getStudyRequests());
  }

}
