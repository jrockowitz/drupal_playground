<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 1 of the import wizard.
 */
class ClinicalTrialsGovFindForm extends ConfigFormBase {

  public function __construct(
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.migration_manager'),
    );
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
  public function getFormId(): string {
    return 'clinical_trials_gov_find_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['query'] = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#title' => $this->t('Studies query'),
      '#default_value' => (string) ($this->config('clinical_trials_gov.settings')->get('query') ?? ''),
      '#include_fields' => [
        'query.',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()->getEditable('clinical_trials_gov.settings')
      ->set('query', (string) $form_state->getValue('query'))
      ->save();
    $this->migrationManager->updateMigration();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('clinical_trials_gov.review');
  }

}
