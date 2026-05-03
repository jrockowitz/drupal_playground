<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovSetupManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovSetupManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSetupManagerTest extends KernelTestBase {

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
   * The setup manager under test.
   */
  protected ClinicalTrialsGovSetupManagerInterface $setupManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['clinical_trials_gov', 'field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->setupManager = $this->container->get('clinical_trials_gov.setup_manager');
  }

  /**
   * Tests that setup applies overrides and provisions the import workflow.
   */
  public function testSetUp(): void {
    $summary = $this->setupManager->setUp([
      'query' => 'query.cond=lung',
      'type' => 'study',
      'field_prefix' => 'study',
      'readonly' => TRUE,
    ]);

    $settings = $this->container->get('config.factory')->get('clinical_trials_gov.settings');
    $migration = $this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov');

    // Check that the provided overrides and derived config are saved.
    $this->assertSame('query.cond=lung', $settings->get('query'));
    $this->assertSame('study', $settings->get('type'));
    $this->assertSame('study', $settings->get('field_prefix'));
    $this->assertTrue($settings->get('readonly'));
    $this->assertNotEmpty($settings->get('query_paths'));
    $this->assertNotEmpty($settings->get('fields'));

    // Check that the setup manager creates the configured bundle and fields.
    $this->assertNotNull(NodeType::load('study'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'study', 'study_nct_id'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'study', 'study_brief_title'));

    // Check that the generated migration targets the configured bundle.
    $this->assertSame('study', $migration->get('destination.default_bundle'));
    $this->assertSame('query.cond=lung', $migration->get('source.query'));

    // Check that callers receive a summary of the completed setup.
    $this->assertSame('query.cond=lung', $summary['query']);
    $this->assertSame('study', $summary['type']);
    $this->assertGreaterThan(0, $summary['query_paths_count']);
    $this->assertGreaterThan(0, $summary['fields_count']);
  }

}
