<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_data\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Suggestion\SuggestionInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the field-backed Search API autocomplete suggester.
 *
 * @group clinical_trials_gov_data
 */
#[Group('clinical_trials_gov_data')]
class ClinicalTrialsGovFieldsSearchApiAutocompleteSuggesterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov_data',
    'field',
    'node',
    'search_api',
    'search_api_autocomplete',
    'search_api_db',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'node', 'search_api', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);

    $this->createTrialContentType();
    $this->createTrialConditionField();
    $this->createSearchIndex();
  }

  /**
   * Tests deduplicated case-insensitive suggestions from field_trial_condition.
   */
  public function testFieldBackedSuggester(): void {
    $this->createTrialNode(['Breast Cancer', 'Brain Tumor']);
    $this->createTrialNode(['breast cancer', 'Digestive Disease']);

    $plugin = $this->container
      ->get('plugin.manager.search_api_autocomplete.suggester')
      ->createInstance('clinical_trials_gov_fields_search_api_autocomplete');
    $query = Index::load('trials_elasticsearch')->query();
    $query->range(0, 5);

    $suggestions = $plugin->getAutocompleteSuggestions($query, 'bre', 'bre');
    $suggested_keys = array_map(
      static fn(SuggestionInterface $suggestion): string => $suggestion->getSuggestedKeys(),
      $suggestions
    );

    // Check that matching is case-insensitive and de-duplicated.
    $this->assertSame(['Breast Cancer'], $suggested_keys);

    $contains_suggestions = $plugin->getAutocompleteSuggestions($query, 'tum', 'tum');
    $contains_suggested_keys = array_map(
      static fn(SuggestionInterface $suggestion): string => $suggestion->getSuggestedKeys(),
      $contains_suggestions
    );

    // Check that matching works on contained text.
    $this->assertSame(['Brain Tumor'], $contains_suggested_keys);
  }

  /**
   * Creates the trial content type used by the test records.
   */
  protected function createTrialContentType(): void {
    NodeType::create([
      'type' => 'trial',
      'name' => 'Trial',
    ])->save();
  }

  /**
   * Creates the normalized condition field used by autocomplete lookups.
   */
  protected function createTrialConditionField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_trial_condition',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => -1,
      'settings' => [
        'max_length' => 255,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_trial_condition',
      'entity_type' => 'node',
      'bundle' => 'trial',
      'label' => 'Condition',
    ])->save();
  }

  /**
   * Creates a Search API index so the suggester receives a real query object.
   */
  protected function createSearchIndex(): void {
    Server::create([
      'id' => 'trials_server',
      'name' => 'Trials server',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ])->save();

    Index::create([
      'id' => 'trials_elasticsearch',
      'name' => 'Trials index',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => ['trial'],
          ],
        ],
      ],
      'server' => 'trials_server',
      'tracker_settings' => [
        'default' => [],
      ],
    ])->save();
  }

  /**
   * Creates one trial node with the provided condition values.
   */
  protected function createTrialNode(array $conditions): void {
    Node::create([
      'type' => 'trial',
      'title' => 'Trial ' . mt_rand(),
      'field_trial_condition' => $conditions,
    ])->save();
  }

}
