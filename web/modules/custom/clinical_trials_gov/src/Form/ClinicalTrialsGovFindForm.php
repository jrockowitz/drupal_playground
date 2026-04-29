<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\Batch\ClinicalTrialsGovPathDiscoveryBatch;
use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 1 of the import wizard.
 */
class ClinicalTrialsGovFindForm extends ConfigFormBase {

  /**
   * Preview page size.
   */
  protected const PREVIEW_PAGE_SIZE = 10;

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

    $config = $this->config('clinical_trials_gov.settings');
    $saved_query = (string) ($config->get('query') ?? '');
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
    $form_state->set('preview_page_token', '');
    $form_state->set('preview_page_offset', 0);
    $form_state->set('preview_next_page_token', '');
    $form_state->set('preview_page_advanced', FALSE);
    $form_state->set('preview_total_count', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax submit handler for preview paging.
   */
  public function nextPreviewPageSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('preview_query', (string) ($form_state->getValue('query') ?? ''));
    $form_state->set('preview_page_token', (string) $form_state->get('preview_next_page_token'));
    $form_state->set('preview_page_offset', (int) $form_state->get('preview_next_page_offset'));
    $form_state->set('preview_page_advanced', TRUE);
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
    $query = (string) $form_state->getValue('query');
    $this->configFactory()->getEditable('clinical_trials_gov.settings')
      ->set('query', $query)
      ->set('paths', [])
      ->save();
    $this->migrationManager->updateMigration();

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Discovering study fields on ClinicalTrials.gov'))
      ->setProgressMessage($this->t('Discovering available study fields...'))
      ->addOperation([ClinicalTrialsGovPathDiscoveryBatch::class, 'discover'], [$query])
      ->setFinishCallback([ClinicalTrialsGovPathDiscoveryBatch::class, 'finish']);
    batch_set($batch->toArray());

    $form_state->setRedirect('clinical_trials_gov.review');
    parent::submitForm($form, $form_state);
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

    $page_token = $this->getPreviewPageToken($form_state);
    $page_offset = $this->getPreviewPageOffset($form_state);
    if ($page_token !== '') {
      $parameters['pageToken'] = $page_token;
    }
    $parameters['countTotal'] = 'true';
    $parameters['pageSize'] = self::PREVIEW_PAGE_SIZE;
    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];
    $total = $response['totalCount'] ?? $form_state->get('preview_total_count');
    if (isset($response['totalCount'])) {
      $form_state->set('preview_total_count', $response['totalCount']);
    }
    $count = count($studies);
    $start = ($count > 0) ? ($page_offset + 1) : 0;
    $end = $page_offset + $count;

    $build = [];
    if ($total !== NULL) {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('Showing @start - @end of @total trials.', [
          '@start' => $start,
          '@end' => $end,
          '@total' => $total,
        ]) . '</p>',
      ];
    }
    elseif ($count > 0) {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('Showing @start - @end trials.', [
          '@start' => $start,
          '@end' => $end,
        ]) . '</p>',
      ];
    }
    else {
      $build['summary'] = [
        '#markup' => '<p>' . $this->t('No trials matched the current query.') . '</p>',
      ];
    }

    if ($studies !== []) {
      $build['results'] = $this->builder->buildStudiesList($studies, 'clinical_trials_gov.review.study', ['modal' => TRUE]);
    }

    if (!empty($response['nextPageToken']) && is_string($response['nextPageToken'])) {
      $form_state->set('preview_next_page_token', $response['nextPageToken']);
      $form_state->set('preview_next_page_offset', $page_offset + $count);
      $build['next_page'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next page'),
        '#submit' => ['::nextPreviewPageSubmit'],
        '#ajax' => [
          'callback' => '::updatePreviewAjax',
          'wrapper' => 'clinical-trials-gov-find-preview',
        ],
      ];
    }
    else {
      $form_state->set('preview_next_page_token', '');
      $form_state->set('preview_next_page_offset', 0);
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

  /**
   * Gets the stored preview page token.
   */
  protected function getPreviewPageToken(FormStateInterface $form_state): string {
    if ($form_state->get('preview_page_advanced')) {
      return (string) $form_state->get('preview_page_token');
    }

    return '';
  }

  /**
   * Gets the stored preview page offset.
   */
  protected function getPreviewPageOffset(FormStateInterface $form_state): int {
    if ($form_state->get('preview_page_advanced')) {
      return (int) $form_state->get('preview_page_offset');
    }

    return 0;
  }
}
