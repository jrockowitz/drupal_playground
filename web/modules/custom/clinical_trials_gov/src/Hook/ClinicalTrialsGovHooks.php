<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuerySummary;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return [
      'clinical_trials_gov_find_form',
      'clinical_trials_gov_config_form',
      'clinical_trials_gov_import_form',
    ];
  }

  /**
   * Implements hook_element_info().
   */
  #[Hook('element_info')]
  public function elementInfo(): array {
    return [
      'clinical_trials_gov_studies_query_summary' => (new ClinicalTrialsGovStudiesQuerySummary())->getInfo(),
    ];
  }

  /**
   * Implements hook_entity_form_display_alter().
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    if (($context['entity_type'] ?? '') !== 'node') {
      return;
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    if (!$settings->get('readonly') || !$this->moduleHandler->moduleExists('readonly_field_widget')) {
      return;
    }

    $bundle = (string) ($context['bundle'] ?? '');
    $type = (string) ($settings->get('type') ?? '');
    if ($bundle !== $type) {
      return;
    }

    $field_mappings = array_filter($settings->get('fields') ?? [], 'is_string');
    if ($field_mappings === []) {
      return;
    }

    $view_display = EntityViewDisplay::load('node.' . $bundle . '.default');
    foreach (array_keys($field_mappings) as $field_name) {
      $component = $form_display->getComponent($field_name);
      if ($component === NULL) {
        continue;
      }

      $formatter_type = (string) (($view_display?->getComponent($field_name)['type'] ?? '') ?: 'string');
      $formatter_settings = (array) ($view_display?->getComponent($field_name)['settings'] ?? []);
      $component['type'] = 'readonly_field_widget';
      $component['settings'] = [
        'label' => 'above',
        'formatter_type' => $formatter_type,
        'formatter_settings' => [
          $formatter_type => $formatter_settings,
        ],
        'show_description' => FALSE,
        'error_validation' => TRUE,
      ];
      $form_display->setComponent($field_name, $component);
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    if (!$settings->get('readonly') || !$this->moduleHandler->moduleExists('readonly_field_widget')) {
      return;
    }

    if ($entity->bundle() !== (string) ($settings->get('type') ?? '')) {
      return;
    }

    $mapped_paths = array_values(array_filter($settings->get('fields') ?? [], 'is_string'));
    if (!in_array('protocolSection.identificationModule.briefTitle', $mapped_paths, TRUE)) {
      return;
    }

    if (isset($form['title'])) {
      $form['title']['#access'] = FALSE;
    }
  }

}
