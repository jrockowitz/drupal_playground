<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\Entity\ClinicalTrialsGovNodeAccessControlHandler;
use Drupal\clinical_trials_gov\Entity\ClinicalTrialsGovTrashNodeAccessControlHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Entity-related hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovEntityHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if (!isset($entity_types['node']) || !$entity_types['node'] instanceof EntityTypeInterface) {
      return;
    }

    $handler_class = $this->moduleHandler->moduleExists('trash')
      ? ClinicalTrialsGovTrashNodeAccessControlHandler::class
      : ClinicalTrialsGovNodeAccessControlHandler::class;
    $entity_types['node']->setHandlerClass('access', $handler_class);
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
    $field_names = array_keys($field_mappings);
    $field_names[] = $this->entityManager->getStudyUrlFieldName();
    $field_names[] = $this->entityManager->getStudyApiFieldName();

    $view_display = EntityViewDisplay::load('node.' . $bundle . '.default');
    foreach (array_unique($field_names) as $field_name) {
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
