<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

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
   * @param \Drupal\ai_automators\AiAutomatorStatusField $aiAutomatorStatusField
   *   The AI automator status field manager.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly UuidInterface $uuid,
    protected readonly AiAutomatorStatusField $aiAutomatorStatusField,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addFieldToEntity(string $entity_type_id, string $bundle): void {
    $this->createFieldStorage($entity_type_id);
    $created = $this->createField($entity_type_id, $bundle);
    if (!$created) {
      return;
    }
    $this->createAutomator($entity_type_id, $bundle);
    $this->addFormDisplayComponent($entity_type_id, $bundle);
    $this->addViewDisplayComponent($entity_type_id, $bundle);
  }

  /**
   * Creates or updates the field storage config.
   */
  protected function createFieldStorage(string $entity_type_id): void {
    $storage_id = $entity_type_id . '.' . self::FIELD_NAME;
    $field_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load($storage_id);

    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => self::FIELD_NAME,
        'entity_type' => $entity_type_id,
        'type' => 'json_native',
        'cardinality' => 1,
        'translatable' => TRUE,
      ]);
    }

    $field_storage->save();
  }

  /**
   * Creates the field instance if it does not already exist.
   *
   * @return bool
   *   TRUE if the field was created, FALSE if it already existed.
   */
  protected function createField(string $entity_type_id, string $bundle): bool {
    $field_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME;
    $existing = $this->entityTypeManager
      ->getStorage('field_config')
      ->load($field_id);

    if ($existing) {
      return FALSE;
    }

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'label' => 'Schema.org JSON-LD',
      'required' => FALSE,
      'translatable' => TRUE,
    ])->save();

    return TRUE;
  }

  /**
   * Creates the AI automator config entity.
   */
  protected function createAutomator(string $entity_type_id, string $bundle): void {
    $automator_id = $entity_type_id . '.' . $bundle . '.' . self::FIELD_NAME . '.default';
    $existing = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load($automator_id);

    if (!$existing) {
      $prompt = $this->configFactory
        ->get('ai_schemadotorg_jsonld.settings')
        ->get('entity_types.' . $entity_type_id . '.prompt') ?? '';

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
    }

    $this->aiAutomatorStatusField->modifyStatusField($entity_type_id, $bundle);
  }

  /**
   * Adds the field to the default form display.
   */
  protected function addFormDisplayComponent(string $entity_type_id, string $bundle): void {
    $display_storage = $this->entityTypeManager->getStorage('entity_form_display');
    $display = $display_storage->load($entity_type_id . '.' . $bundle . '.default');

    if (!$display) {
      $display = $display_storage->create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $widget_type = $this->moduleHandler->moduleExists('json_field_widget')
      ? 'json_editor'
      : 'json_textarea';

    $action_uuid = $this->uuid->generate();

    $display->setComponent(self::FIELD_NAME, [
      'type' => $widget_type,
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
   */
  protected function addViewDisplayComponent(string $entity_type_id, string $bundle): void {
    $display_storage = $this->entityTypeManager->getStorage('entity_view_display');
    $display = $display_storage->load($entity_type_id . '.' . $bundle . '.default');

    if (!$display) {
      $display = $display_storage->create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    if ($display->getComponent(self::FIELD_NAME)) {
      return;
    }

    $display->setComponent(self::FIELD_NAME, [
      'type' => 'pretty',
      'weight' => 99,
    ])->save();
  }

}
