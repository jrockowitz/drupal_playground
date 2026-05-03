<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrials.gov setUp config action.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSettingsSetUpConfigActionTest extends KernelTestBase {

  use RecipeTestTrait;

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
   * The config action manager under test.
   */
  protected ConfigActionManager $configActionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['clinical_trials_gov', 'field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->configActionManager = $this->container->get('plugin.manager.config_action');
  }

  /**
   * Tests that the config action requires a query.
   */
  public function testSetUpRequiresQuery(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('The setUp config action requires a query.');
    $this->configActionManager->applyAction('setUp', 'clinical_trials_gov.settings', []);
  }

  /**
   * Tests that the config action provisions ClinicalTrials.gov setup.
   */
  public function testSetUpAppliesWorkflow(): void {
    $this->configActionManager->applyAction('setUp', 'clinical_trials_gov.settings', [
      'query' => 'query.cond=lung',
      'type' => 'study',
      'field_prefix' => 'study',
    ]);

    $settings = $this->container->get('config.factory')->get('clinical_trials_gov.settings');

    // Check that the config action saved the provided query and overrides.
    $this->assertSame('query.cond=lung', $settings->get('query'));
    $this->assertSame('study', $settings->get('type'));
    $this->assertSame('study', $settings->get('field_prefix'));
    $this->assertNotEmpty($settings->get('query_paths'));

    // Check that the config action ran the full setup workflow.
    $this->assertNotNull(NodeType::load('study'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'study', 'study_nct_id'));
  }

}
