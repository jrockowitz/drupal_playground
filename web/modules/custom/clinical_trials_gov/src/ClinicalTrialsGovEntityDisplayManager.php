<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages wizard-created entity displays.
 */
class ClinicalTrialsGovEntityDisplayManager implements ClinicalTrialsGovEntityDisplayManagerInterface {

  /**
   * The generated field-group machine name for ClinicalTrials.gov display items.
   */
  protected const ROOT_FIELD_GROUP_NAME = 'group_clinical_trials_gov';

  /**
   * The generated field-group label for ClinicalTrials.gov display items.
   */
  protected const ROOT_FIELD_GROUP_LABEL = 'ClinicalTrials.gov';

  /**
   * Constructs a new ClinicalTrialsGovEntityDisplayManager.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createFieldDisplayComponents(string $type, array $field_definitions): void {
    $form_display = $this->loadOrCreateFormDisplay($type);
    $default_view_display = $this->loadOrCreateViewDisplay($type);
    $form_display_component = $this->getConfiguredFormDisplayComponent();
    $view_display_component = $this->getConfiguredViewDisplayComponent();

    $weight = 0;
    foreach ($field_definitions as $definition) {
      if (empty($definition['selectable']) || !empty($definition['group_only']) || empty($definition['field_name'])) {
        continue;
      }

      $field_name = $definition['field_name'];
      if (($form_display_component !== 'hidden') && !$form_display->getComponent($field_name)) {
        $form_display->setComponent($field_name, [
          'type' => $this->getConfiguredFormDisplayWidget($definition),
          'settings' => $this->getConfiguredFormDisplayWidgetSettings($definition),
          'weight' => $weight,
          'region' => 'content',
        ]);
      }

      if (($view_display_component !== 'hidden') && !$default_view_display->getComponent($field_name)) {
        $default_view_display->setComponent($field_name, [
          'type' => $this->getViewDisplayFormatter($definition),
          'label' => 'above',
          'weight' => $weight,
          'region' => 'content',
        ]);
      }

      $weight++;
    }

    $default_display_id = 'node.' . $type . '.default';
    $form_display->save();
    $default_view_display->save();
    $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$default_display_id]);
    $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$default_display_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldGroups(string $type, array $fields, array $field_definitions): void {
    if (!$this->supportsFieldGroups()) {
      return;
    }

    $form_root_format = $this->getConfiguredFormDisplayRoot();
    $form_field_group_format = $this->getConfiguredFormDisplayFieldGroup();
    $view_root_format = $this->getConfiguredViewDisplayRoot();
    $view_field_group_format = $this->getConfiguredViewDisplayFieldGroup();
    if (($form_root_format === 'none')
      && ($form_field_group_format === 'none')
      && ($view_root_format === 'none')
      && ($view_field_group_format === 'none')) {
      return;
    }

    $selected_fields = [];
    foreach ($fields as $field) {
      if (is_string($field) && $field) {
        $selected_fields[$field] = $this->fieldManager->resolveFieldDefinition($field);
      }
    }

    $group_definitions = array_filter($selected_fields, static fn(array $definition): bool => !empty($definition['group_only']));

    $form_display = (($form_root_format !== 'none') || ($form_field_group_format !== 'none')) ? $this->loadOrCreateFormDisplay($type) : NULL;
    $view_display = (($view_root_format !== 'none') || ($view_field_group_format !== 'none')) ? $this->loadOrCreateViewDisplay($type) : NULL;

    if ($form_display) {
      $this->applyDisplayFieldGroups($form_display, $group_definitions, $selected_fields, $field_definitions, $form_field_group_format, $form_root_format);
    }

    if ($view_display) {
      $this->applyDisplayFieldGroups($view_display, $group_definitions, $selected_fields, $field_definitions, $view_field_group_format, $view_root_format);
    }

    $display_id = 'node.' . $type . '.default';
    if ($form_display) {
      $form_display->save();
      $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$display_id]);
    }
    if ($view_display) {
      $view_display->save();
      $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$display_id]);
    }
  }

  /**
   * Returns whether the field_group module can be used for nested structs.
   */
  protected function supportsFieldGroups(): bool {
    return $this->moduleHandler->moduleExists('field_group');
  }

  /**
   * Loads or creates the default entity form display for a node bundle.
   */
  protected function loadOrCreateFormDisplay(string $type): EntityFormDisplayInterface {
    $display_id = 'node.' . $type . '.default';
    $storage = $this->entityTypeManager->getStorage('entity_form_display');
    return $storage->load($display_id) ?? $storage->create([
      'targetEntityType' => 'node',
      'bundle' => $type,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  /**
   * Loads or creates the default entity view display for a node bundle.
   */
  protected function loadOrCreateViewDisplay(string $type, string $mode = 'default'): EntityViewDisplayInterface {
    $display_id = 'node.' . $type . '.' . $mode;
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    return $storage->load($display_id) ?? $storage->create([
      'targetEntityType' => 'node',
      'bundle' => $type,
      'mode' => $mode,
      'status' => TRUE,
    ]);
  }

  /**
   * Resolves the default form widget for a generated field.
   */
  protected function getFormDisplayWidget(array $definition): string {
    return match ($definition['field_type']) {
      'boolean' => 'boolean_checkbox',
      'link' => 'link_default',
      'datetime' => 'datetime_default',
      'integer' => 'number',
      'list_string' => 'options_select',
      'text_long' => 'text_textarea',
      'custom' => 'custom_stacked',
      default => 'string_textfield',
    };
  }

  /**
   * Resolves the configured form widget for a generated field.
   */
  protected function getConfiguredFormDisplayWidget(array $definition): string {
    if ($this->getConfiguredFormDisplayComponent() === 'readonly') {
      return 'readonly_field_widget';
    }

    return $this->getFormDisplayWidget($definition);
  }

  /**
   * Resolves widget settings for the configured form display component.
   */
  protected function getConfiguredFormDisplayWidgetSettings(array $definition): array {
    if ($this->getConfiguredFormDisplayComponent() !== 'readonly') {
      return [];
    }

    $formatter_type = $this->getViewDisplayFormatter($definition);

    return [
      'label' => 'above',
      'formatter_type' => $formatter_type,
      'formatter_settings' => [
        $formatter_type => [],
      ],
      'show_description' => FALSE,
      'error_validation' => TRUE,
    ];
  }

  /**
   * Resolves the default view formatter for a generated field.
   */
  protected function getViewDisplayFormatter(array $definition): string {
    return match ($definition['field_type']) {
      'boolean' => 'boolean',
      'link' => 'link',
      'datetime' => 'datetime_default',
      'integer' => 'number_integer',
      'list_string' => 'list_default',
      'text_long' => 'text_default',
      'custom' => 'custom_formatter',
      default => 'string',
    };
  }

  /**
   * Returns the configured view display component behavior.
   */
  protected function getConfiguredViewDisplayComponent(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('view_display_component');

    return in_array($value, ['visible', 'visible_update', 'hidden']) ? $value : 'visible';
  }

  /**
   * Returns the configured view root field-group format.
   */
  protected function getConfiguredViewDisplayRoot(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('view_display_root');

    return in_array($value, ['details', 'details_opened', 'fieldset', 'container', 'none']) ? $value : 'details_opened';
  }

  /**
   * Returns the configured view field-group format.
   */
  protected function getConfiguredViewDisplayFieldGroup(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('view_display_field_group');

    return in_array($value, ['details', 'details_opened', 'fieldset', 'container', 'none']) ? $value : 'details_opened';
  }

  /**
   * Returns the configured form display component behavior.
   */
  protected function getConfiguredFormDisplayComponent(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('form_display_component');
    if (($value === 'readonly') && !$this->moduleHandler->moduleExists('readonly_field_widget')) {
      return 'visible';
    }

    return in_array($value, ['visible', 'hidden', 'readonly']) ? $value : 'visible';
  }

  /**
   * Returns the configured form root field-group format.
   */
  protected function getConfiguredFormDisplayRoot(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('form_display_root');

    return in_array($value, ['details', 'details_opened', 'fieldset', 'container', 'none']) ? $value : 'details_opened';
  }

  /**
   * Returns the configured form field-group format.
   */
  protected function getConfiguredFormDisplayFieldGroup(): string {
    $value = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('form_display_field_group');

    return in_array($value, ['details', 'details_opened', 'fieldset', 'container', 'none']) ? $value : 'details_opened';
  }

  /**
   * Resolves the direct children for a field group.
   */
  protected function resolveFieldGroupChildren(string $path, array $selected_fields): array {
    $metadata = $this->studyManager->getMetadataByPath($path);
    $children = [];

    foreach (($metadata['children'] ?? []) as $child_path) {
      if (!is_string($child_path) || !isset($selected_fields[$child_path])) {
        continue;
      }

      $child_definition = $selected_fields[$child_path];
      if (!empty($child_definition['group_only'])) {
        $children[] = $child_definition['field_name'];
        continue;
      }
      if (($child_definition['destination_property'] ?? NULL) === 'title') {
        if (!empty($child_definition['field_name'])) {
          $children[] = $child_definition['field_name'];
        }
        continue;
      }
      if (!empty($child_definition['field_name'])) {
        $children[] = $child_definition['field_name'];
      }
    }

    return array_values(array_unique($children));
  }

  /**
   * Resolves the parent field-group name for a nested group.
   */
  protected function resolveParentGroupName(string $path, array $selected_fields): string {
    $metadata = $this->studyManager->getMetadataByPath($path);
    $parent = (string) ($metadata['parent'] ?? '');
    if (!$parent || !isset($selected_fields[$parent]) || empty($selected_fields[$parent]['group_only'])) {
      return '';
    }

    return (string) $selected_fields[$parent]['field_name'];
  }

  /**
   * Applies generated field-group settings to a display.
   */
  protected function applyDisplayFieldGroups(
    EntityFormDisplayInterface|EntityViewDisplayInterface $display,
    array $group_definitions,
    array $selected_fields,
    array $field_definitions,
    string $field_group_format,
    string $root_format,
  ): void {
    $root_children = $this->getRootFieldGroupChildren($field_definitions, $group_definitions, $selected_fields, $display, $field_group_format);
    foreach ($group_definitions as $path => $definition) {
      if ($field_group_format === 'none') {
        $display->unsetThirdPartySetting('field_group', $definition['field_name']);
        continue;
      }

      $children = $this->resolveFieldGroupChildren($path, $selected_fields);
      if (!$children) {
        continue;
      }

      $parent_name = $this->resolveParentGroupName($path, $selected_fields);
      if (($parent_name === '') && ($root_format !== 'none')) {
        $parent_name = self::ROOT_FIELD_GROUP_NAME;
        $root_children[] = $definition['field_name'];
      }

      $display->setThirdPartySetting('field_group', $definition['field_name'], $this->buildFieldGroupSettings($definition, $children, $parent_name, $field_group_format));
    }

    if ($root_format === 'none') {
      $display->unsetThirdPartySetting('field_group', self::ROOT_FIELD_GROUP_NAME);
      return;
    }

    $display->setThirdPartySetting('field_group', self::ROOT_FIELD_GROUP_NAME, $this->buildRootFieldGroupSettings(array_values(array_unique($root_children)), $root_format));
  }

  /**
   * Returns the top-level generated children for the ClinicalTrials.gov root group.
   */
  protected function getRootFieldGroupChildren(
    array $field_definitions,
    array $group_definitions,
    array $selected_fields,
    EntityFormDisplayInterface|EntityViewDisplayInterface $display,
    string $field_group_format,
  ): array {
    $group_children = [];
    if ($field_group_format !== 'none') {
      foreach (array_keys($group_definitions) as $path) {
        foreach ($this->resolveFieldGroupChildren($path, $selected_fields) as $child_name) {
          $group_children[] = $child_name;
        }
      }
    }

    $root_children = [];
    foreach ($field_definitions as $definition) {
      if (empty($definition['selectable']) || !empty($definition['group_only']) || empty($definition['field_name'])) {
        continue;
      }
      if (($definition['destination_property'] ?? NULL) === 'title') {
        continue;
      }
      if (!$display->getComponent($definition['field_name'])) {
        continue;
      }
      if (in_array($definition['field_name'], $group_children)) {
        continue;
      }

      $root_children[] = $definition['field_name'];
    }

    return $root_children;
  }

  /**
   * Builds third-party settings for the ClinicalTrials.gov root field group.
   */
  protected function buildRootFieldGroupSettings(array $children, string $format): array {
    return $this->buildFieldGroupSettings([
      'label' => self::ROOT_FIELD_GROUP_LABEL,
      'description' => '',
    ], $children, '', $format);
  }

  /**
   * Builds third-party field-group settings for a selected format.
   */
  protected function buildFieldGroupSettings(array $definition, array $children, string $parent_name, string $format): array {
    $settings = [
      'children' => $children,
      'label' => $definition['label'],
      'parent_name' => $parent_name,
      'weight' => 0,
      'format_type' => $this->getFieldGroupFormatType($format),
      'format_settings' => [
        'label' => $definition['label'],
        'classes' => '',
        'id' => '',
        'show_empty_fields' => FALSE,
        'label_as_html' => FALSE,
      ],
      'region' => 'content',
    ];

    if (in_array($format, ['details', 'details_opened'])) {
      $settings['format_settings']['open'] = ($format === 'details_opened');
      $settings['format_settings']['description'] = $definition['description'];
      $settings['format_settings']['required_fields'] = FALSE;
      return $settings;
    }

    if ($format === 'fieldset') {
      $settings['format_settings']['description'] = $definition['description'];
      $settings['format_settings']['required_fields'] = FALSE;
      return $settings;
    }

    if ($format === 'container') {
      $settings['format_settings']['element'] = 'div';
      $settings['format_settings']['show_label'] = FALSE;
      $settings['format_settings']['label_element'] = 'h3';
      $settings['format_settings']['label_element_classes'] = '';
      $settings['format_settings']['attributes'] = '';
      $settings['format_settings']['effect'] = 'none';
      $settings['format_settings']['speed'] = 'fast';
      $settings['format_settings']['required_fields'] = FALSE;
    }

    return $settings;
  }

  /**
   * Maps saved field-group options to field_group formatter plugin ids.
   */
  protected function getFieldGroupFormatType(string $format): string {
    return match ($format) {
      'fieldset' => 'fieldset',
      'container' => 'html_element',
      default => 'details',
    };
  }

}
