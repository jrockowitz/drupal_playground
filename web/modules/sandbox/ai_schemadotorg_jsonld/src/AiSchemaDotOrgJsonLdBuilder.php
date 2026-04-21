<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates and manages the Schema.org JSON-LD field on entity bundles.
 */
class AiSchemaDotOrgJsonLdBuilder implements AiSchemaDotOrgJsonLdBuilderInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdBuilder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager
   *   The Schema.org JSON-LD manager.
   * @param \Drupal\ai_automators\AiAutomatorStatusField $aiAutomatorStatusField
   *   The AI automator status field manager.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly UuidInterface $uuid,
    protected readonly AiSchemaDotOrgJsonLdManagerInterface $manager,
    #[Autowire(service: 'ai_automator.status_field')]
    protected readonly AiAutomatorStatusField $aiAutomatorStatusField,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addFieldToBundles(string $entity_type_id, array $bundles): void {
    $bundles = $this->resolveBundles($entity_type_id, $bundles);
    foreach ($bundles as $bundle) {
      $this->addFieldToBundle($entity_type_id, $bundle);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldToBundle(string $entity_type_id, string $bundle): void {
    if (!$this->manager->isSupportedEntityType($entity_type_id)) {
      throw new \InvalidArgumentException('The entity type ' . $entity_type_id . ' is not supported.');
    }

    $this->ensureEntityTypeSettings($entity_type_id);

    $this->createFieldStorage($entity_type_id);
    $this->createField($entity_type_id, $bundle);
    $this->createAutomator($entity_type_id, $bundle);
    $this->addFormDisplayComponent($entity_type_id, $bundle);
    $this->addViewDisplayComponent($entity_type_id, $bundle);
  }

  /**
   * Creates or updates the field storage configuration for the JSON-LD field.
   *
   * The storage is created as a 'json_native' field type if it doesn't already
   * exist for the given entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   */
  protected function createFieldStorage(string $entity_type_id): void {
    $storage_id = $entity_type_id . '.' . self::FIELD_NAME;

    $field_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load($storage_id)
      ?? FieldStorageConfig::create([
        'field_name' => self::FIELD_NAME,
        'entity_type' => $entity_type_id,
        'type' => 'json_native',
        'cardinality' => 1,
        'translatable' => TRUE,
      ]);

    $field_storage->save();
  }

  /**
   * Creates the JSON-LD field instance on the specified bundle if needed.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function createField(string $entity_type_id, string $bundle): void {
    $field_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME;
    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load($field_id);

    if ($field_config) {
      return;
    }

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'label' => 'Schema.org JSON-LD',
      'required' => FALSE,
      'translatable' => TRUE,
    ])->save();
  }

  /**
   * Creates the AI automator configuration entity for the bundle.
   *
   * Configures a default automator using the 'llm_json_native_field' rule
   * and populates it with the default prompt for the entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function createAutomator(string $entity_type_id, string $bundle): void {
    $automator_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME . '.default';

    $automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load($automator_id);
    if ($automator) {
      return;
    }
    $prompt = $this->configFactory
      ->get('ai_schemadotorg_jsonld.settings')
      ->get('entity_types.' . $entity_type_id . '.default_prompt') ?? '';

    $this->entityTypeManager->getStorage('ai_automator')->create([
      'id' => $automator_id,
      'label' => 'Schema.org JSON-LD Default',
      'rule' => 'llm_json_native_field',
      'input_mode' => 'token',
      'weight' => 100,
      'worker_type' => 'field_widget_actions',
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => self::FIELD_NAME,
      'edit_mode' => FALSE,
      'base_field' => 'revision_log',
      'prompt' => '',
      'token' => $prompt,
      // plugin_config is used by AiAutomatorEntityModifier to build the
      // runtime automatorConfig array (stripping the 'automator_' prefix).
      // All fields that are read at runtime must be present here.
      'plugin_config' => [
        'automator_enabled' => 1,
        'automator_rule' => 'llm_json_native_field',
        'automator_mode' => 'token',
        'automator_base_field' => 'revision_log',
        'automator_prompt' => '',
        'automator_token' => $prompt,
        'automator_edit_mode' => 0,
        'automator_label' => 'Schema.org JSON-LD Default',
        'automator_weight' => '100',
        'automator_worker_type' => 'field_widget_actions',
        'automator_ai_provider' => 'default_json',
      ],
    ])->save();

    $this->aiAutomatorStatusField->modifyStatusField($entity_type_id, $bundle);
  }

  /**
   * Adds the field to the default form display.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function addFormDisplayComponent(string $entity_type_id, string $bundle): void {
    $display_storage = $this->entityTypeManager->getStorage('entity_form_display');
    $display = $display_storage->load($entity_type_id . '.' . $bundle . '.default')
      ?? $display_storage->create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $widget_type = $this->moduleHandler->moduleExists('json_field_widget')
      ? 'json_editor'
      : 'json_textarea';

    $action_uuid = $this->uuid->generate();

    $display->setComponent(self::FIELD_NAME, [
      'type' => $widget_type,
      // Setting widget weight to 99 to ensure it appears last in the form
      // but before the submit actions.
      'weight' => 99,
      'third_party_settings' => [
        'field_widget_actions' => [
          $action_uuid => [
            'plugin_id' => 'automator_json',
            'enabled' => TRUE,
            'weight' => 0,
            'button_label' => 'Generate Schema.org JSON-LD',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Adds the field to the default view display.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function addViewDisplayComponent(string $entity_type_id, string $bundle): void {
    $display_storage = $this->entityTypeManager->getStorage('entity_view_display');
    $display = $display_storage->load($entity_type_id . '.' . $bundle . '.default')
      ?? $display_storage->create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $display->setComponent(self::FIELD_NAME, [
      'type' => 'json',
      // Setting widget weight to 99 to ensure it appears before links.
      'weight' => 99,
    ])->save();
  }

  /**
   * Returns a supported content entity type definition.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface
   *   The supported content entity type definition.
   */
  protected function getSupportedEntityTypeDefinition(string $entity_type_id): ContentEntityTypeInterface {
    if (!$this->manager->isSupportedEntityType($entity_type_id)) {
      throw new \InvalidArgumentException('The entity type ' . $entity_type_id . ' is not supported.');
    }

    $supported_entity_types = $this->manager->getSupportedEntityTypes();
    $entity_type_definition = $supported_entity_types[$entity_type_id];

    return $entity_type_definition;
  }

  /**
   * Resolves the bundle list for a supported content entity type.
   *
   * @param string $entity_type_id
   *   The supported content entity type ID.
   * @param array $bundles
   *   The requested bundle list.
   *
   * @return array
   *   The resolved bundle list.
   */
  protected function resolveBundles(string $entity_type_id, array $bundles): array {
    if ($bundles === []) {
      throw new \InvalidArgumentException('The bundles list for ' . $entity_type_id . ' cannot be empty.');
    }

    $bundles = array_values(array_unique($bundles));
    if (in_array('*', $bundles) && count($bundles) > 1) {
      throw new \InvalidArgumentException('The bundles list for ' . $entity_type_id . ' cannot mix "*" with explicit bundle names.');
    }

    $entity_type_definition = $this->getSupportedEntityTypeDefinition($entity_type_id);
    $bundle_entity_type_id = $entity_type_definition->getBundleEntityType();
    if (!$bundle_entity_type_id) {
      if (($bundles === ['*']) || ($bundles === [$entity_type_id])) {
        return [$entity_type_id];
      }

      throw new \InvalidArgumentException('The non-bundle entity type ' . $entity_type_id . ' requires the synthetic bundle ' . $entity_type_id . '.');
    }

    $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type_id);
    if ($bundles === ['*']) {
      $bundle_ids = array_keys($bundle_storage->loadMultiple());
      sort($bundle_ids);
      return $bundle_ids;
    }

    foreach ($bundles as $bundle) {
      if (!$bundle_storage->load($bundle)) {
        throw new \InvalidArgumentException('The bundle ' . $bundle . ' does not exist for the entity type ' . $entity_type_id . '.');
      }
    }

    return $bundles;
  }

  /**
   * Ensures entity type settings exist for the given content entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   */
  protected function ensureEntityTypeSettings(string $entity_type_id): void {
    $entity_type_settings = $this->configFactory
      ->get('ai_schemadotorg_jsonld.settings')
      ->get('entity_types.' . $entity_type_id);
    if (!$entity_type_settings) {
      $this->manager->addEntityType($entity_type_id);
    }
  }

}
