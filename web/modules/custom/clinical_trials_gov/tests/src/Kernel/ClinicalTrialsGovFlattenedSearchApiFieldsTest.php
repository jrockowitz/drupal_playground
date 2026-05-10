<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for derived Search API fields from custom-field arrays.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFlattenedSearchApiFieldsTest extends ClinicalTrialsGovContentTestBase {

  /**
   * The Search API index under test.
   */
  protected Index $index;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'custom_field',
    'field',
    'node',
    'search_api',
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
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['clinical_trials_gov', 'field', 'node', 'search_api', 'system']);
    $this->installSchema('search_api', ['search_api_item']);

    $this->createTrialContentType();
    $this->createCustomField(
      'trial_cond_mod',
      [
        'cond' => 'Condition',
        'keyword' => 'Keyword',
      ]
    );
    $this->createCustomField(
      'trial_elig_mod',
      [
        'sex' => 'Sex',
        'std_age' => 'Age group',
      ]
    );
    $this->createIndex();
  }

  /**
   * Tests derived Search API fields for serialized custom-field arrays.
   */
  public function testFlattenedSearchApiFields(): void {
    $this->createTrialNode(
      ['Breast Cancer', 'Digestive Disease'],
      ['breast screening', 'Cardiology'],
      ['ALL'],
      ['ADULT', 'OLDER_ADULT']
    );
    $this->createTrialNode(
      ['Breast Cancer'],
      ['Precision Medicine'],
      ['FEMALE'],
      ['OLDER_ADULT']
    );

    $item = $this->createSearchItem(Node::load(1));
    $fields = $item->getFields();

    // Check that conditions are exposed as separate Search API string values.
    $this->assertSame([
      'Breast Cancer',
      'Digestive Disease',
    ], $fields['trial_cond']->getValues());

    // Check that keywords are exposed as separate Search API string values.
    $this->assertSame([
      'breast screening',
      'Cardiology',
    ], $fields['trial_keyword']->getValues());

    // Check that age groups are exposed as separate Search API string values.
    $this->assertSame([
      'ADULT',
      'OLDER_ADULT',
    ], $fields['trial_std_age']->getValues());

    // Check that sex values are exposed as separate Search API string values.
    $this->assertSame([
      'ALL',
    ], $fields['trial_sex']->getValues());

    $this->index->trackItemsUpdated('entity:node', ['1', '2']);
    $indexed_items = $this->index->indexItems();

    // Check that the items are indexed successfully with derived field values.
    $this->assertEquals(2, $indexed_items);

    $query = $this->index->query();
    $query->addCondition('trial_cond', 'Breast Cancer');
    $condition_results = $query->execute()->getResultCount();

    // Check that exact filtering works on the flattened condition values.
    $this->assertEquals(2, $condition_results);

    $query = $this->index->query();
    $query->addCondition('trial_keyword', 'breast screening');
    $keyword_results = $query->execute()->getResultCount();

    // Check that exact filtering works on the flattened keyword values.
    $this->assertEquals(1, $keyword_results);

    $query = $this->index->query();
    $query->addCondition('trial_std_age', 'OLDER_ADULT');
    $age_results = $query->execute()->getResultCount();

    // Check that exact filtering works on the flattened age-group values.
    $this->assertEquals(2, $age_results);

    $query = $this->index->query();
    $query->addCondition('trial_sex', 'ALL');
    $sex_results = $query->execute()->getResultCount();

    // Check that exact filtering works on the flattened sex values.
    $this->assertEquals(1, $sex_results);
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
   * Creates one custom field with map_string columns.
   */
  protected function createCustomField(string $field_name, array $columns): void {
    $storage_columns = [];
    $field_settings = [];

    foreach ($columns as $column_name => $label) {
      $storage_columns[$column_name] = [
        'name' => $column_name,
        'type' => 'map_string',
      ];
      $field_settings[$column_name] = [
        'label' => $label,
        'check_empty' => FALSE,
        'required' => FALSE,
        'translatable' => FALSE,
        'description' => '',
        'description_display' => 'after',
        'table_empty' => '',
      ];
    }

    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'custom',
      'settings' => [
        'columns' => $storage_columns,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'trial',
      'label' => $field_name,
      'settings' => [
        'field_settings' => $field_settings,
      ],
    ])->save();
  }

  /**
   * Creates the Search API index used by the test.
   */
  protected function createIndex(): void {
    Server::create([
      'id' => 'trials_server',
      'name' => 'Trials server',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ])->save();

    $this->index = Index::create([
      'id' => 'trials_index',
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
    ]);

    $plugin_helper = $this->container->get('search_api.plugin_helper');
    $processor = $plugin_helper->createProcessorPlugin($this->index, 'clinical_trials_gov_flattened_custom_field_values');
    $this->index->addProcessor($processor);

    $this->index->addField($this->createIndexField('trial_cond', 'trial_cond'));
    $this->index->addField($this->createIndexField('trial_keyword', 'trial_keyword'));
    $this->index->addField($this->createIndexField('trial_std_age', 'trial_std_age'));
    $this->index->addField($this->createIndexField('trial_sex', 'trial_sex'));
    $this->index->save();
  }

  /**
   * Creates one Search API field definition for a derived property.
   */
  protected function createIndexField(string $field_identifier, string $property_path): Field {
    $field = new Field($this->index, $field_identifier);
    $field->setType('string');
    $field->setPropertyPath($property_path);
    $field->setDatasourceId('entity:node');
    $field->setLabel($field_identifier);
    return $field;
  }

  /**
   * Creates one trial node with the provided custom-field values.
   */
  protected function createTrialNode(array $conditions, array $keywords, array $sexes, array $std_ages): void {
    Node::create([
      'type' => 'trial',
      'title' => 'Trial ' . mt_rand(),
      'trial_cond_mod' => [
        [
          'cond' => $conditions,
          'keyword' => $keywords,
        ],
      ],
      'trial_elig_mod' => [
        [
          'sex' => $sexes,
          'std_age' => $std_ages,
        ],
      ],
    ])->save();
  }

  /**
   * Creates one Search API item from a node entity.
   */
  protected function createSearchItem(Node $node): ItemInterface {
    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
    return $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);
  }

}
