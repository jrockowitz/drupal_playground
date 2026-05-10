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
 * Kernel tests for flattened Search API fields from custom-field values.
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
    $this->createCustomField('trial_cond_mod', 'Conditions Module', [
      'cond' => [
        'label' => 'Condition/Disease',
        'description' => 'Flattened disease values.',
      ],
      'keyword' => [
        'label' => 'Keyword',
        'description' => 'Flattened keyword values.',
      ],
    ]);
    $this->createCustomField('trial_elig_mod', 'Eligibility Module', [
      'sex' => [
        'label' => 'Sex',
        'description' => 'Flattened sex values.',
      ],
      'std_age' => [
        'label' => 'Age group',
        'description' => 'Flattened age-group values.',
      ],
    ]);
    $this->createCustomField('trial_topic_data', 'Topic Data', [
      'topic_terms' => [
        'label' => 'Topic terms',
        'description' => 'Flattened topic values.',
      ],
    ]);
    $this->createIndex();
  }

  /**
   * Tests configurable derived Search API fields for custom-field values.
   */
  public function testFlattenedSearchApiFields(): void {
    $this->createTrialNode(
      ['Breast Cancer', 'Digestive Disease'],
      ['breast screening', 'Cardiology'],
      ['ALL'],
      ['ADULT', 'OLDER_ADULT'],
      ['Topic A', 'Topic B']
    );
    $this->createTrialNode(
      ['Breast Cancer'],
      ['Precision Medicine'],
      ['FEMALE'],
      ['OLDER_ADULT'],
      ['Topic B']
    );

    $processor = $this->container
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'clinical_trials_gov_flattened_custom_field_values', [
        'mappings' => $this->getProcessorMappings(),
      ]);
    $property_definitions = $processor->getPropertyDefinitions($this->index->getDatasource('entity:node'));

    // Check that the configured mappings expose labels from field settings.
    $this->assertSame('Conditions Module: Condition/Disease', (string) $property_definitions['trial_cond']->getLabel());
    $this->assertSame('Conditions Module: Keyword', (string) $property_definitions['trial_keyword']->getLabel());
    $this->assertSame('Eligibility Module: Age group', (string) $property_definitions['trial_std_age']->getLabel());
    $this->assertSame('Eligibility Module: Sex', (string) $property_definitions['trial_sex']->getLabel());
    $this->assertSame('Topic Data: Topic terms', (string) $property_definitions['trial_topic']->getLabel());

    // Check that the configured mappings expose descriptions from field
    // settings.
    $this->assertSame('Flattened disease values.', (string) $property_definitions['trial_cond']->getDescription());
    $this->assertSame('Flattened keyword values.', (string) $property_definitions['trial_keyword']->getDescription());
    $this->assertSame('Flattened age-group values.', (string) $property_definitions['trial_std_age']->getDescription());
    $this->assertSame('Flattened sex values.', (string) $property_definitions['trial_sex']->getDescription());
    $this->assertSame('Flattened topic values.', (string) $property_definitions['trial_topic']->getDescription());

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

    // Check that non-ClinicalTrials.gov custom fields can use the same
    // processor mappings.
    $this->assertSame([
      'Topic A',
      'Topic B',
    ], $fields['trial_topic']->getValues());

    // Check that mappings for missing source fields do not add values.
    $this->assertSame([], $fields['trial_missing']->getValues());

    $this->index->trackItemsUpdated('entity:node', ['1', '2']);
    $indexed_items = $this->index->indexItems();

    // Check that the items are indexed successfully with derived field values.
    $this->assertEquals(2, $indexed_items);

    $query = $this->index->query();
    $query->addCondition('trial_cond', 'Breast Cancer');

    // Check that exact filtering works on the flattened condition values.
    $this->assertEquals(2, $query->execute()->getResultCount());

    $query = $this->index->query();
    $query->addCondition('trial_keyword', 'breast screening');

    // Check that exact filtering works on the flattened keyword values.
    $this->assertEquals(1, $query->execute()->getResultCount());

    $query = $this->index->query();
    $query->addCondition('trial_std_age', 'OLDER_ADULT');

    // Check that exact filtering works on the flattened age-group values.
    $this->assertEquals(2, $query->execute()->getResultCount());

    $query = $this->index->query();
    $query->addCondition('trial_sex', 'ALL');

    // Check that exact filtering works on the flattened sex values.
    $this->assertEquals(1, $query->execute()->getResultCount());

    $query = $this->index->query();
    $query->addCondition('trial_topic', 'Topic B');

    // Check that exact filtering works on generic custom-field mappings too.
    $this->assertEquals(2, $query->execute()->getResultCount());
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
   *
   * @param string $field_name
   *   The field machine name.
   * @param string $field_label
   *   The field label.
   * @param array $columns
   *   The custom-field columns keyed by column name.
   */
  protected function createCustomField(string $field_name, string $field_label, array $columns): void {
    $storage_columns = [];
    $field_settings = [];

    foreach ($columns as $column_name => $column_definition) {
      $storage_columns[$column_name] = [
        'name' => $column_name,
        'type' => 'map_string',
      ];
      $field_settings[$column_name] = [
        'label' => $column_definition['label'],
        'check_empty' => FALSE,
        'required' => FALSE,
        'translatable' => FALSE,
        'description' => $column_definition['description'],
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
      'label' => $field_label,
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
    $processor = $plugin_helper->createProcessorPlugin($this->index, 'clinical_trials_gov_flattened_custom_field_values', [
      'mappings' => $this->getProcessorMappings(),
    ]);
    $this->index->addProcessor($processor);

    $this->index->addField($this->createIndexField('trial_cond', 'trial_cond'));
    $this->index->addField($this->createIndexField('trial_keyword', 'trial_keyword'));
    $this->index->addField($this->createIndexField('trial_std_age', 'trial_std_age'));
    $this->index->addField($this->createIndexField('trial_sex', 'trial_sex'));
    $this->index->addField($this->createIndexField('trial_topic', 'trial_topic'));
    $this->index->addField($this->createIndexField('trial_missing', 'trial_missing'));
    $this->index->save();
  }

  /**
   * Gets the processor mappings used by the test index.
   *
   * @return array
   *   The mapping definitions.
   */
  protected function getProcessorMappings(): array {
    return [
      [
        'property_path' => 'trial_cond',
        'field_name' => 'trial_cond_mod',
        'column_name' => 'cond',
      ],
      [
        'property_path' => 'trial_keyword',
        'field_name' => 'trial_cond_mod',
        'column_name' => 'keyword',
      ],
      [
        'property_path' => 'trial_std_age',
        'field_name' => 'trial_elig_mod',
        'column_name' => 'std_age',
      ],
      [
        'property_path' => 'trial_sex',
        'field_name' => 'trial_elig_mod',
        'column_name' => 'sex',
      ],
      [
        'property_path' => 'trial_topic',
        'field_name' => 'trial_topic_data',
        'column_name' => 'topic_terms',
      ],
      [
        'property_path' => 'trial_cond',
        'field_name' => 'trial_elig_mod',
        'column_name' => 'sex',
      ],
      [
        'property_path' => 'trial_missing',
        'field_name' => 'trial_missing_data',
        'column_name' => 'ghost_terms',
      ],
    ];
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
  protected function createTrialNode(array $conditions, array $keywords, array $sexes, array $std_ages, array $topics): void {
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
      'trial_topic_data' => [
        [
          'topic_terms' => $topics,
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
