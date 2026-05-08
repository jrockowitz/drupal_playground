<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrials.gov setUp config action.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSettingsSetUpConfigActionTest extends ClinicalTrialsGovContentTestBase {

  use RecipeTestTrait;

  /**
   * The config action manager under test.
   */
  protected ConfigActionManager $configActionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['clinical_trials_gov', 'field', 'filter', 'node', 'system']);
    $this->configActionManager = $this->container->get('plugin.manager.config_action');
  }

  /**
   * Tests that the config action validates input and provisions setup.
   */
  public function testSetUp(): void {
    try {
      $this->configActionManager->applyAction('setUp', 'clinical_trials_gov.settings', []);
      $this->fail('Expected the config action to require a query.');
    }
    catch (ConfigActionException $exception) {
      // Check that the config action rejects missing queries.
      $this->assertSame('The setUp config action requires a query.', $exception->getMessage());
    }

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
