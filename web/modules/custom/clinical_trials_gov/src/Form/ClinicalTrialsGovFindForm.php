<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 1 of the import wizard.
 */
class ClinicalTrialsGovFindForm extends ConfigFormBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.manager'),
      $container->get('clinical_trials_gov.builder'),
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
    $form['#attributes']['class'][] = 'clinical-trials-gov';
    $form['#attached']['library'][] = 'clinical_trials_gov/clinical_trials_gov';

    $saved_query = (string) ($this->config('clinical_trials_gov.settings')->get('query') ?? '');
    $form['query_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Studies query'),
    ];
    $form['query_wrapper']['query'] = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => $saved_query,
      '#include_fields' => [
        'query.',
        'filter.overallStatus',
        'filter.ids',
      ],
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview'),
      '#open' => FALSE,
    ];
    $form['preview']['update_preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update preview'),
      '#submit' => ['::updatePreviewSubmit'],
      '#ajax' => [
        'callback' => '::updatePreviewAjax',
        'wrapper' => 'clinical-trials-gov-find-preview',
      ],
    ];
    $form['preview']['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'clinical-trials-gov-find-preview',
      ],
    ] + $this->buildPreview($form_state, $saved_query);

    $form = parent::buildForm($form, $form_state);

    if (isset($form['actions'])) {
      $actions = $form['actions'];
      unset($form['actions']);
      $query_wrapper = $form['query_wrapper'];
      $preview = $form['preview'];
      unset($form['query_wrapper'], $form['preview']);
      $form['query_wrapper'] = $query_wrapper;
      $form['actions'] = $actions;
      $form['preview'] = $preview;
    }

    return $form;
  }

  /**
   * Ajax submit handler for preview updates.
   */
  public function updatePreviewSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('preview_query', (string) ($form_state->getValue('query') ?? ''));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for preview updates.
   */
  public function updatePreviewAjax(array &$form, FormStateInterface $form_state): array {
    return $form['preview']['results'];
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

  /**
   * Builds the preview results render array.
   */
  protected function buildPreview(FormStateInterface $form_state, string $saved_query): array {
    $query = $this->getPreviewQuery($form_state, $saved_query);
    if ($query === '') {
      return [
        'message' => [
          '#markup' => '<p>' . $this->t('Use Update preview to preview the current query without saving it.') . '</p>',
        ],
      ];
    }

    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
    if ($parameters === []) {
      return [
        'message' => [
          '#markup' => '<p>' . $this->t('Enter at least one query value to preview studies.') . '</p>',
        ],
      ];
    }

    $parameters['countTotal'] = 'true';
    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];
    $total = $response['totalCount'] ?? NULL;
    $count = count($studies);

    $build = [];
    if ($total !== NULL) {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('Showing 1 - @end of @total trials.', [
          '@end' => $count,
          '@total' => $total,
        ]) . '</p>',
      ];
    }
    elseif ($count > 0) {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('Showing 1 - @end trials.', [
          '@end' => $count,
        ]) . '</p>',
      ];
    }
    else {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('No trials matched the current query.') . '</p>',
      ];
    }

    if ($studies !== []) {
      $build['results'] = $this->builder->buildStudiesList($studies, 'clinical_trials_gov.review', ['modal' => TRUE]);
    }

    return $build;
  }

  /**
   * Gets the query string used to build the preview.
   */
  protected function getPreviewQuery(FormStateInterface $form_state, string $saved_query): string {
    if ($form_state->has('preview_query')) {
      return (string) $form_state->get('preview_query');
    }

    return (ClinicalTrialsGovStudiesQuery::parseQueryString($saved_query) !== []) ? $saved_query : '';
  }

}
