<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_search_api_autocomplete\Kernel;

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
 * Tests the database-backed Search API autocomplete suggester.
 *
 * @group clinical_trials_gov_search_api_autocomplete
 */
#[Group('clinical_trials_gov_search_api_autocomplete')]
class ClinicalTrialsGovSearchApiAutocompleteSuggesterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov_search_api_autocomplete',
    'custom_field',
    'field',
    'node',
    'search_api',
    'search_api_autocomplete',
    'search_api_db',
    'system',
    'text',
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
    $this->createTrialField();
    $this->createSearchIndex();
  }

  /**
   * Tests merged, de-duplicated, contains-based suggestions from trial fields.
   */
  public function testDatabaseTermsSuggester(): void {
    $this->createTrialNode(
      ['Breast Cancer', 'Brain Tumor', ''],
      ['breast screening', 'Cardiology']
    );
    $this->createTrialNode(
      ['breast cancer', 'Digestive Disease'],
      ['Breast Cancer', '']
    );
    $plugin = $this->container
      ->get('plugin.manager.search_api_autocomplete.suggester')
      ->createInstance('clinical_trials_gov_search_api_autocomplete');
    $query = Index::load('trials_elasticsearch')->query();
    $query->range(0, 5);

    $suggestions = $plugin->getAutocompleteSuggestions($query, 'bre', 'bre');
    $suggested_keys = array_map(
      static fn(SuggestionInterface $suggestion): string => $suggestion->getSuggestedKeys(),
      $suggestions
    );

    // Check that the suggester combines condition and keyword values.
    $this->assertContains('Breast Cancer', $suggested_keys);
    $this->assertContains('breast screening', $suggested_keys);

    // Check that duplicate values across both source tables are removed.
    $this->assertCount(2, $suggested_keys);

    // Check that prefix matching is case-insensitive.
    $this->assertSame(['Breast Cancer', 'breast screening'], $suggested_keys);

    // Check that unrelated and empty values are excluded.
    $this->assertNotContains('Brain Tumor', $suggested_keys);
    $this->assertNotContains('Cardiology', $suggested_keys);
    $this->assertNotContains('', $suggested_keys);

    $contains_suggestions = $plugin->getAutocompleteSuggestions($query, 'screen', 'screen');
    $contains_suggested_keys = array_map(
      static fn(SuggestionInterface $suggestion): string => $suggestion->getSuggestedKeys(),
      $contains_suggestions
    );

    // Check that matching now works on contained text, not only prefixes.
    $this->assertSame(['breast screening'], $contains_suggested_keys);
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
   * Creates the promoted custom field used by the autocomplete lookups.
   */
  protected function createTrialField(): void {
    FieldStorageConfig::create([
      'field_name' => 'trial_cond_mod',
      'entity_type' => 'node',
      'type' => 'custom',
      'settings' => [
        'columns' => [
          'cond' => [
            'name' => 'cond',
            'type' => 'map_string',
          ],
          'keyword' => [
            'name' => 'keyword',
            'type' => 'map_string',
          ],
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'trial_cond_mod',
      'entity_type' => 'node',
      'bundle' => 'trial',
      'label' => 'trial_cond_mod',
      'settings' => [
        'field_settings' => [
          'cond' => [
            'label' => 'Condition',
            'check_empty' => FALSE,
            'required' => FALSE,
            'translatable' => FALSE,
            'description' => '',
            'description_display' => 'after',
            'table_empty' => '',
          ],
          'keyword' => [
            'label' => 'Keyword',
            'check_empty' => FALSE,
            'required' => FALSE,
            'translatable' => FALSE,
            'description' => '',
            'description_display' => 'after',
            'table_empty' => '',
          ],
        ],
      ],
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
   * Creates one trial node with the provided condition and keyword values.
   */
  protected function createTrialNode(array $conditions, array $keywords): void {
    Node::create([
      'type' => 'trial',
      'title' => 'Trial ' . mt_rand(),
      'trial_cond_mod' => [[
        'cond' => $conditions,
        'keyword' => $keywords,
      ],
      ],
    ])->save();
  }

}
