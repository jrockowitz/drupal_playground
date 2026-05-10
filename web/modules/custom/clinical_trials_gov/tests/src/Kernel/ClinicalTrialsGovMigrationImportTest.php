<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovSetupManagerInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrials.gov migration imports.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovMigrationImportTest extends ClinicalTrialsGovContentTestBase {

  /**
   * The setup manager under test.
   */
  protected ClinicalTrialsGovSetupManagerInterface $setupManager;

  /**
   * The entity manager under test.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * The migration plugin manager under test.
   */
  protected MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['clinical_trials_gov', 'field', 'filter', 'node', 'system']);
    $this->installSchema('migrate_tools', ['migrate_tools_sync_source_ids']);
    $this->setupManager = $this->container->get('clinical_trials_gov.setup_manager');
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
    $this->migrationPluginManager = $this->container->get('plugin.manager.migration');
  }

  /**
   * Tests that imported custom fields populate remapped property names.
   */
  public function testImportPopulatesCustomFields(): void {
    $this->setupManager->setUp([
      'query' => 'query.cond=cancer',
    ]);

    $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');
    $result = (new MigrateExecutable($migration))->import();

    // Check that the migration completes successfully.
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $result);

    $nct_id_field_name = $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId');
    $conditions_field_name = $this->entityManager->generateFieldName('protocolSection.conditionsModule');
    $description_field_name = $this->entityManager->generateFieldName('protocolSection.descriptionModule');
    $eligibility_field_name = $this->entityManager->generateFieldName('protocolSection.eligibilityModule');

    $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties([
      'type' => 'trial',
    ]);
    $nodes_by_nct_id = [];

    foreach ($nodes as $node) {
      $nodes_by_nct_id[$node->get($nct_id_field_name)->value] = $node;
    }

    // Check that the configured source rows were imported as trial nodes.
    $this->assertCount(3, $nodes_by_nct_id);
    $this->assertArrayHasKey('NCT05088187', $nodes_by_nct_id);
    $this->assertArrayHasKey('NCT01205711', $nodes_by_nct_id);

    $thyroid_study = $nodes_by_nct_id['NCT05088187'];
    $thyroid_conditions = $thyroid_study->get($conditions_field_name)->getValue()[0];
    $thyroid_description = $thyroid_study->get($description_field_name)->getValue()[0];
    $thyroid_eligibility = $thyroid_study->get($eligibility_field_name)->getValue()[0];

    // Check that conditions remap into the abbreviated custom field key.
    $this->assertSame([
      'Thyroid Nodule',
      'Thyroid Cancer',
      'Cognitive Decline',
      'Survivorship',
      'Symptoms, Cognitive',
    ], $thyroid_conditions['cond']);

    // Check that remapped description and eligibility values are populated.
    $this->assertStringContainsString('objective cognitive dysfunction', $thyroid_description['brief_summary']);
    $this->assertStringContainsString('Inclusion Criteria', $thyroid_eligibility['elig_criteria']);
    $this->assertSame([
      'ADULT',
      'OLDER_ADULT',
    ], $thyroid_eligibility['std_age']);

    $colorectal_study = $nodes_by_nct_id['NCT01205711'];
    $colorectal_conditions = $colorectal_study->get($conditions_field_name)->getValue()[0];

    // Check that keywords remap into the abbreviated custom field key.
    $this->assertSame([
      'recurrent colon cancer',
      'stage IV colon cancer',
      'recurrent rectal cancer',
      'stage IV rectal cancer',
    ], $colorectal_conditions['keyword']);
  }

}
