<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovConfigForm;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the reusable ClinicalTrials.gov setup workflow.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSetupWorkflowTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'node',
    'field',
    'text',
    'link',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'custom_field',
    'field_group',
  ];

  /**
   * The paths manager under test.
   */
  protected ClinicalTrialsGovPathsManagerInterface $pathsManager;

  /**
   * The entity manager under test.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * The migration manager under test.
   */
  protected ClinicalTrialsGovMigrationManagerInterface $migrationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['clinical_trials_gov', 'field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->pathsManager = $this->container->get('clinical_trials_gov.paths_manager');
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
    $this->migrationManager = $this->container->get('clinical_trials_gov.migration_manager');
  }

  /**
   * Tests the reusable setup workflow from query discovery to migration config.
   */
  public function testSetupWorkflow(): void {
    $query = 'query.cond=lung';
    $paths = $this->pathsManager->discoverQueryPaths($query);

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', $query)
      ->set('query_paths', $paths)
      ->save();

    $form_object = ClinicalTrialsGovConfigForm::create($this->container);
    $form = $form_object->buildForm([], new FormState());
    $submitted_rows = [];

    foreach (($form['field_mapping']['rows'] ?? []) as $row) {
      if (!is_array($row) || empty($row['path']['#value'])) {
        continue;
      }
      $submitted_rows[] = [
        'path' => $row['path']['#value'],
        'selected' => !empty($row['selected']['#default_value']),
      ];
    }

    $field_mappings = $this->entityManager->buildDefaultFieldMappings();
    $form_field_mappings = $this->entityManager->buildFieldMappings(
      $this->entityManager->buildSelectedRows($submitted_rows, 'trial')
    );
    $this->entityManager->saveFieldMappings($field_mappings);
    $this->entityManager->createConfiguredContentType();
    $this->entityManager->createConfiguredFields();
    $this->migrationManager->updateMigration();

    $settings = $this->container->get('config.factory')->get('clinical_trials_gov.settings');
    $migration = $this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov');

    // Check that the workflow persists the saved query state.
    $this->assertSame($query, $settings->get('query'));
    $this->assertSame($paths, $settings->get('query_paths'));
    $this->assertSame($field_mappings, $settings->get('fields'));

    // Check that the reusable mapping includes title, required fields, and
    // structural group rows when descendants are present.
    $this->assertSame('protocolSection.identificationModule', $field_mappings['group_id_mod']);
    $this->assertSame('protocolSection.identificationModule.briefTitle', $field_mappings['trial_brief_title']);
    $this->assertSame('protocolSection.identificationModule.nctId', $field_mappings['trial_nct_id']);
    $this->assertSame($form_field_mappings, $field_mappings);

    // Check that bundle and field creation use the configured bundle machine
    // name and the existing default label/description behavior.
    $node_type = NodeType::load('trial');
    $this->assertNotNull($node_type);
    $this->assertSame('Trial', $node_type->label());
    $this->assertSame('Imported ClinicalTrials.gov studies.', $node_type->getDescription());
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', 'trial_nct_id'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', 'trial_brief_title'));

    // Check that the generated migration points at the configured bundle.
    $this->assertSame('clinical_trials_gov', $migration->get('id'));
    $this->assertSame('trial', $migration->get('destination.default_bundle'));
    $this->assertSame($query, $migration->get('source.query'));

  }

}
