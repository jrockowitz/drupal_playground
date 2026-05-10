<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\search_api\processor;

use Drupal\clinical_trials_gov\ClinicalTrialsGovNamesInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds flattened multi-value Search API properties for custom-field arrays.
 */
#[SearchApiProcessor(
  id: 'clinical_trials_gov_flattened_custom_field_values',
  label: new TranslatableMarkup('ClinicalTrials.gov flattened custom field values'),
  description: new TranslatableMarkup('Exposes derived multi-value string properties for serialized ClinicalTrials.gov custom-field arrays.'),
  stages: [
    'add_properties' => 0,
  ],
  hidden: TRUE,
)]
class ClinicalTrialsGovFlattenedCustomFieldValues extends ProcessorPluginBase {

  /**
   * The derived Search API property for conditions.
   */
  public const CONDITIONS_PROPERTY_PATH = 'trial_cond';

  /**
   * The derived Search API property for keywords.
   */
  public const KEYWORDS_PROPERTY_PATH = 'trial_keyword';

  /**
   * The derived Search API property for age groups.
   */
  public const STANDARD_AGE_PROPERTY_PATH = 'trial_std_age';

  /**
   * The derived Search API property for sex.
   */
  public const SEX_PROPERTY_PATH = 'trial_sex';

  /**
   * The ConditionsModule custom-field property key.
   */
  protected const CONDITIONS_PROPERTY_NAME = 'cond';

  /**
   * The Keywords custom-field property key.
   */
  protected const KEYWORD_PROPERTY_NAME = 'keyword';

  /**
   * The standard age custom-field property key.
   */
  protected const STANDARD_AGE_PROPERTY_NAME = 'std_age';

  /**
   * The sex custom-field property key.
   */
  protected const SEX_PROPERTY_NAME = 'sex';

  /**
   * The naming helper.
   */
  protected ?ClinicalTrialsGovNamesInterface $names = NULL;

  /**
   * Creates the processor plugin from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   The plugin configuration.
   * @param mixed $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The instantiated processor plugin.
   */
  // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setNames($container->get(ClinicalTrialsGovNamesInterface::class));
    return $processor;
  }

  /**
   * Sets the naming helper.
   *
   * @param \Drupal\clinical_trials_gov\ClinicalTrialsGovNamesInterface $names
   *   The naming helper.
   *
   * @return $this
   *   The current processor.
   */
  public function setNames(ClinicalTrialsGovNamesInterface $names): static {
    $this->names = $names;
    return $this;
  }

  /**
   * Gets the naming helper.
   *
   * @return \Drupal\clinical_trials_gov\ClinicalTrialsGovNamesInterface
   *   The naming helper.
   */
  protected function getNames(): ClinicalTrialsGovNamesInterface {
    if ($this->names instanceof ClinicalTrialsGovNamesInterface) {
      return $this->names;
    }

    throw new \LogicException('The ClinicalTrials.gov naming helper has not been set.');
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if (
      !$datasource
      || ($datasource->getEntityTypeId() !== 'node')
    ) {
      return [];
    }

    return [
      self::CONDITIONS_PROPERTY_PATH => new ProcessorProperty([
        'label' => $this->t('ClinicalTrials.gov conditions'),
        'description' => $this->t('Flattened condition values from the promoted ConditionsModule custom field.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
      self::KEYWORDS_PROPERTY_PATH => new ProcessorProperty([
        'label' => $this->t('ClinicalTrials.gov keywords'),
        'description' => $this->t('Flattened keyword values from the promoted ConditionsModule custom field.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
      self::STANDARD_AGE_PROPERTY_PATH => new ProcessorProperty([
        'label' => $this->t('ClinicalTrials.gov age groups'),
        'description' => $this->t('Flattened standard age values from the promoted EligibilityModule custom field.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
      self::SEX_PROPERTY_PATH => new ProcessorProperty([
        'label' => $this->t('ClinicalTrials.gov sex values'),
        'description' => $this->t('Flattened sex values from the promoted EligibilityModule custom field.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $conditions_field_name = $this->getNames()->getFieldName('ConditionsModule');
    $eligibility_field_name = $this->getNames()->getFieldName('EligibilityModule');

    $this->addValuesToProperty(
      $item,
      self::CONDITIONS_PROPERTY_PATH,
      $this->extractFieldValues($entity, $conditions_field_name, self::CONDITIONS_PROPERTY_NAME),
    );
    $this->addValuesToProperty(
      $item,
      self::KEYWORDS_PROPERTY_PATH,
      $this->extractFieldValues($entity, $conditions_field_name, self::KEYWORD_PROPERTY_NAME),
    );
    $this->addValuesToProperty(
      $item,
      self::STANDARD_AGE_PROPERTY_PATH,
      $this->extractFieldValues($entity, $eligibility_field_name, self::STANDARD_AGE_PROPERTY_NAME),
    );
    $this->addValuesToProperty(
      $item,
      self::SEX_PROPERTY_PATH,
      $this->extractFieldValues($entity, $eligibility_field_name, self::SEX_PROPERTY_NAME),
    );
  }

  /**
   * Extracts normalized string values from one custom-field property.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being indexed.
   * @param string $field_name
   *   The custom field machine name.
   * @param string $property_name
   *   The custom-field property name.
   *
   * @return array
   *   The flattened string values.
   */
  protected function extractFieldValues(ContentEntityInterface $entity, string $field_name, string $property_name): array {
    if (
      !$entity->hasField($field_name)
      || $entity->get($field_name)->isEmpty()
    ) {
      return [];
    }

    $values = [];
    foreach ($entity->get($field_name)->getValue() as $item) {
      if (
        !is_array($item)
        || !array_key_exists($property_name, $item)
      ) {
        continue;
      }

      $values = array_merge($values, $this->normalizeStoredValue($item[$property_name]));
    }

    return array_values(array_unique($values));
  }

  /**
   * Normalizes one stored custom-field value into flat strings.
   *
   * @param mixed $value
   *   The stored value.
   *
   * @return array
   *   The flattened string values.
   */
  protected function normalizeStoredValue(mixed $value): array {
    if (is_resource($value)) {
      $value = stream_get_contents($value) ?: '';
    }

    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return [];
      }

      $decoded_value = @unserialize($value, ['allowed_classes' => FALSE]);
      if (is_array($decoded_value)) {
        return $this->flattenStringValues($decoded_value);
      }

      return [$value];
    }

    if (is_array($value)) {
      return $this->flattenStringValues($value);
    }

    return [];
  }

  /**
   * Flattens nested arrays into a de-duplicated list of strings.
   *
   * @param array $values
   *   The nested values to flatten.
   *
   * @return array
   *   The flattened string values.
   */
  protected function flattenStringValues(array $values): array {
    $flattened_values = [];

    array_walk_recursive($values, static function (mixed $value) use (&$flattened_values): void {
      if (!is_string($value)) {
        return;
      }

      $value = trim($value);
      if ($value === '') {
        return;
      }

      $flattened_values[] = $value;
    });

    return array_values(array_unique($flattened_values));
  }

  /**
   * Adds normalized values to a derived Search API property.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The Search API item.
   * @param string $property_path
   *   The derived property path.
   * @param array $values
   *   The values to add.
   */
  protected function addValuesToProperty(ItemInterface $item, string $property_path, array $values): void {
    if ($values === []) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), $item->getDatasourceId(), $property_path);

    foreach ($fields as $field) {
      foreach ($values as $value) {
        $field->addValue($value);
      }
    }
  }

}
