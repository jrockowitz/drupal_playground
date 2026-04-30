<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced settings for the ClinicalTrials.gov workflow.
 *
 * @phpstan-consistent-constructor
 */
class ClinicalTrialsGovSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['clinical_trials_gov.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $structure_locked = $this->isStructureLocked();
    $config = $this->config('clinical_trials_gov.settings');

    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content type machine name'),
      '#description' => $this->t('Machine name for the destination content type. Common values include trial, study, or nct. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => 'clinical_trials_gov.settings:type',
      '#required' => !$structure_locked,
      '#disabled' => $structure_locked,
    ];
    $form['field_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field prefix'),
      '#description' => $this->t('Prefix used when generating Drupal field machine names. Common values include trial, study, or nct. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => 'clinical_trials_gov.settings:field_prefix',
      '#required' => !$structure_locked,
      '#disabled' => $structure_locked,
    ];
    $form['readonly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read-only imported fields'),
      '#description' => $this->t('Display imported ClinicalTrials.gov fields as readonly on edit forms and hide the editable node title when the generated title mapping is present.'),
      '#default_value' => (bool) $config->get('readonly'),
      '#access' => $this->moduleHandler->moduleExists('readonly_field_widget'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->configFactory()->getEditable('clinical_trials_gov.settings')
      ->set('readonly', (bool) $form_state->getValue('readonly'))
      ->save();
  }

  /**
   * Determines whether the destination structure already exists.
   */
  protected function isStructureLocked(): bool {
    $config = $this->config('clinical_trials_gov.settings');
    $type = trim((string) ($config->get('type') ?? ''));

    if ($type === '') {
      return FALSE;
    }

    return $this->entityTypeManager->getStorage('node_type')->load($type) !== NULL;
  }

}
