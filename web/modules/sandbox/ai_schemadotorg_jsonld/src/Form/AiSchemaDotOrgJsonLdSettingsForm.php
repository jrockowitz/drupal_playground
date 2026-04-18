<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Form;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Schema.org JSON-LD settings.
 */
class AiSchemaDotOrgJsonLdSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The builder service.
   */
  protected AiSchemaDotOrgJsonLdBuilderInterface $builder;

  /**
   * The manager service.
   */
  protected AiSchemaDotOrgJsonLdManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->builder = $container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
    $instance->manager = $container->get(AiSchemaDotOrgJsonLdManagerInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_schemadotorg_jsonld_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_schemadotorg_jsonld.settings');
    $entity_type_settings = $config->get('entity_types') ?? [];
    $enabled_entity_type_ids = array_keys($entity_type_settings);
    if (isset($entity_type_settings['node'])) {
      $enabled_entity_type_ids = ['node'];
      $enabled_entity_type_ids = array_merge($enabled_entity_type_ids, array_keys(array_diff_key($entity_type_settings, ['node' => TRUE])));
    }
    $enabled_entity_type_options = $this->getEnabledEntityTypeOptions($enabled_entity_type_ids);

    $form['entity_types'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($enabled_entity_type_ids as $entity_type_id) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
        continue;
      }

      $header = [
        'label' => $this->t('Name'),
        'description' => [
          'data' => $this->t('Description'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        'operations' => $this->t('Operations'),
      ];
      $disabled_bundles = [];
      $options = $this->getEntityTypeOptions($entity_type_id);
      foreach ($options as $bundle => $option) {
        if (!empty($option['#disabled'])) {
          $disabled_bundles[] = $bundle;
        }
      }

      $form['entity_types'][$entity_type_id] = [
        '#type' => 'details',
        '#title' => $entity_type_definition->getLabel(),
        '#description' => $this->t('Select the bundles that should get the Schema.org JSON-LD field. Then review the default prompt and optional default JSON-LD used for this entity type. Note: you can customize the prompt for an individual bundle by clicking Edit field, going to AI Automator Settings, and editing the automator prompt.'),
        '#open' => TRUE,
      ];

      $form['entity_types'][$entity_type_id]['bundles'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $options,
        '#default_value' => array_fill_keys($disabled_bundles, TRUE),
      ];

      $form['entity_types'][$entity_type_id]['prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Default prompt'),
        '#description' => $this->t('Token-based prompt sent to the LLM for @entity_type entities. Use <code>[@entity_type:ai_schemadotorg_jsonld:content]</code> to include the full rendered entity.', ['@entity_type' => $entity_type_id]),
        '#rows' => 5,
        '#default_value' => $entity_type_settings[$entity_type_id]['prompt'] ?? '',
      ];

      $default_jsonld_type = $this->moduleHandler->moduleExists('json_field_widget')
        ? 'json_editor'
        : 'textarea';

      $form['entity_types'][$entity_type_id]['default_jsonld'] = [
        '#type' => $default_jsonld_type,
        '#title' => $this->t('Default JSON-LD'),
        '#description' => $this->t('Default JSON-LD injected for canonical @entity_type pages whose bundle already has the Schema.org JSON-LD field. Leave blank to disable.', ['@entity_type' => $entity_type_definition->getLabel()]),
        '#default_value' => $entity_type_settings[$entity_type_id]['default_jsonld'] ?? '',
        '#element_validate' => [[$this, 'validateJson']],
      ];

      if ($default_jsonld_type === 'json_editor') {
        $form['entity_types'][$entity_type_id]['default_jsonld']['#attached']['library'][] = 'json_field_widget/json_editor.widget';
      }
    }

    $form['enabled_entity_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled entity types'),
      '#description' => $this->t('Enable the supported entity types you want to manage with this module. After saving, each enabled entity type will appear above with bundle, prompt, and default JSON-LD settings.'),
      '#open' => FALSE,
      '#tree' => TRUE,
      // Canonical content entities without bundles, such as user, are a future
      // follow-up once the field builder supports them.
    ];
    $form['enabled_entity_types']['entity_types'] = [
      '#type' => 'tableselect',
      '#header' => [
        'label' => $this->t('Entity type'),
        'machine_name' => $this->t('Machine name'),
      ],
      '#options' => $enabled_entity_type_options,
      '#default_value' => array_fill_keys($enabled_entity_type_ids, TRUE),
    ];

    $form['breadcrumb_jsonld'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include breadcrumb JSON-LD'),
      '#description' => $this->t('Attach a BreadcrumbList JSON-LD block to each page.'),
      '#default_value' => (bool) $config->get('breadcrumb_jsonld'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Element validate callback: ensures the default_jsonld value is valid JSON.
   */
  public function validateJson(array &$element, FormStateInterface $form_state): void {
    $value = trim((string) ($element['#value'] ?? ''));
    if ($value === '') {
      return;
    }
    try {
      json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      $form_state->setError($element, $this->t('Default JSON-LD contains invalid JSON: @message', ['@message' => $exception->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled_entity_types = array_filter($form_state->getValue(['enabled_entity_types', 'entity_types']) ?? []);
    $enabled_entity_type_ids = array_keys($enabled_entity_types);
    $this->manager->syncEntityTypes($enabled_entity_type_ids);
    $this->manager->addEntityTypes($enabled_entity_type_ids);

    $config = $this->configFactory->getEditable('ai_schemadotorg_jsonld.settings');
    $configured_entity_type_ids = array_keys($config->get('entity_types') ?? []);
    $entity_type_values = $form_state->getValue('entity_types') ?? [];

    foreach ($configured_entity_type_ids as $entity_type_id) {
      $entity_type_values_item = $entity_type_values[$entity_type_id] ?? NULL;
      if (!is_array($entity_type_values_item)) {
        continue;
      }

      foreach (array_filter($entity_type_values_item['bundles'] ?? []) as $bundle => $value) {
        $this->builder->addFieldToEntity($entity_type_id, $bundle);
      }

      $config->set('entity_types.' . $entity_type_id . '.prompt', $entity_type_values_item['prompt'] ?? '');
      $config->set('entity_types.' . $entity_type_id . '.default_jsonld', $entity_type_values_item['default_jsonld'] ?? '');
    }

    $config->set('breadcrumb_jsonld', (bool) $form_state->getValue('breadcrumb_jsonld'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns tableselect options for an entity type's bundles.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   *
   * @return array
   *   Tableselect options keyed by bundle machine name.
   */
  protected function getEntityTypeOptions(string $entity_type_id): array {
    $options = [];

    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
      return $options;
    }
    $bundle_entity_type_id = $entity_type_definition->getBundleEntityType();

    if ($bundle_entity_type_id === NULL) {
      return $options;
    }

    $bundle_entity_type_definition = $this->entityTypeManager->getDefinition($bundle_entity_type_id, FALSE);
    if ($bundle_entity_type_definition === NULL) {
      return $options;
    }

    $bundle_types = $this->entityTypeManager->getStorage($bundle_entity_type_id)->loadMultiple();
    uasort($bundle_types, static fn ($a, $b): int => strnatcasecmp((string) $a->label(), (string) $b->label()));

    foreach ($bundle_types as $type) {
      $bundle = $type->id();
      $label = $type->label();
      $description = '';
      if (method_exists($type, 'get')) {
        $description = (string) ($type->get('description')->value ?? '');
      }
      if ($description === '' && method_exists($type, 'getDescription')) {
        $description = (string) $type->getDescription();
      }
      $field_config = $this->entityTypeManager
        ->getStorage('field_config')
        ->load($entity_type_id . '.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);

      $links = [];
      if ($field_config !== NULL && $entity_type_definition->get('field_ui_base_route')) {
        $route_parameters = FieldUI::getRouteBundleParameter($entity_type_definition, $bundle) + [
          'field_config' => $entity_type_id . '.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
        ];
        $destination = '/admin/config/ai/schemadotorg-jsonld';
        $attributes = [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
        ];

        $edit_url = Url::fromRoute('entity.field_config.' . $entity_type_id . '_field_edit_form', $route_parameters);
        $edit_url->setOption('query', ['destination' => $destination]);
        $links['edit'] = [
          'title' => $this->t('Edit field'),
          'weight' => 10,
          'url' => $edit_url,
          'attributes' => $attributes + [
            'title' => $this->t('Edit field settings.'),
            'data-dialog-options' => Json::encode(['width' => 1100]),
          ],
        ];

        $delete_url = Url::fromRoute('entity.field_config.' . $entity_type_id . '_field_delete_form', $route_parameters);
        $delete_url->setOption('query', ['destination' => $destination]);
        $links['delete'] = [
          'title' => $this->t('Delete field'),
          'weight' => 100,
          'url' => $delete_url,
          'attributes' => $attributes + [
            'title' => $this->t('Delete field.'),
            'data-dialog-options' => Json::encode(['width' => 880]),
          ],
        ];
      }

      $label_cell = ['#plain_text' => $label];
      if ($bundle_entity_type_definition->hasLinkTemplate('edit-form')) {
        $label_cell = $type->toLink($label, 'edit-form')->toRenderable();
      }

      $options[$bundle] = [
        'label' => [
          'data' => $label_cell,
        ],
        'description' => [
          'data' => ['#markup' => $description],
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
            '#attached' => [
              'library' => ['core/drupal.dialog.ajax'],
            ],
          ],
        ],
      ];

      if ($field_config !== NULL) {
        $options[$bundle]['#disabled'] = TRUE;
      }
    }

    return $options;
  }

  /**
   * Returns enabled entity type selector options.
   *
   * @param array $enabled_entity_type_ids
   *   The enabled entity type IDs.
   *
   * @return array
   *   Tableselect options keyed by entity type ID.
   */
  protected function getEnabledEntityTypeOptions(array $enabled_entity_type_ids): array {
    $options = [];

    foreach ($this->manager->getSupportedEntityTypes() as $entity_type_id => $entity_type_definition) {
      $options[$entity_type_id] = [
        'label' => (string) $entity_type_definition->getLabel(),
        'machine_name' => $entity_type_id,
      ];

      if (
        in_array($entity_type_id, $enabled_entity_type_ids)
        && $this->hasFieldStorage($entity_type_id)
      ) {
        $options[$entity_type_id]['#disabled'] = TRUE;
      }
    }

    return $options;
  }

  /**
   * Returns TRUE when the entity type has Schema.org JSON-LD field storage.
   */
  protected function hasFieldStorage(string $entity_type_id): bool {
    return $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load($entity_type_id . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) !== NULL;
  }

}
