<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Displays the studies review step for the import wizard.
 */
class ClinicalTrialsGovReviewStudiesController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
  ) {}

  /**
   * Creates the controller from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.manager'),
      $container->get('clinical_trials_gov.builder'),
    );
  }

  /**
   * Returns the review page.
   */
  public function index(Request $request): array {
    return $this->buildListPage($request);
  }

  /**
   * Returns the review study detail page.
   */
  public function study(Request $request, string $nctId): array {
    if (!preg_match('/^NCT\d+$/', $nctId)) {
      $this->messenger()->addWarning($this->t('The study identifier %nct_id is not valid.', [
        '%nct_id' => $nctId,
      ]));
      return $this->buildListPage($request);
    }

    $study = $this->manager->getStudy($nctId);
    if ($study === []) {
      $this->messenger()->addWarning($this->t('Study %nct_id was not found. Review the saved query results below.', [
        '%nct_id' => $nctId,
      ]));
      return $this->buildListPage($request);
    }

    return $this->builder->buildStudy($study, $nctId);
  }

  /**
   * Returns the page title for the study detail route.
   */
  public function title(string $nctId): string {
    if (!preg_match('/^NCT\d+$/', $nctId)) {
      return (string) $this->t('ClinicalTrials.gov');
    }

    $study = $this->manager->getStudy($nctId);
    return (string) ($study['protocolSection.identificationModule.briefTitle'] ?? $this->t('ClinicalTrials.gov'));
  }

  /**
   * Builds the saved-query studies list page.
   */
  protected function buildListPage(Request $request): array {
    $saved_query = (string) ($this->config('clinical_trials_gov.settings')->get('query') ?? '');
    if ($saved_query === '') {
      $this->messenger()->addWarning($this->t('No saved query was found. Start with the <a href=":find_url">Find</a> step.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
      return [];
    }

    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($saved_query);
    $page_token = (string) $request->query->get('pageToken', '');
    $page_offset = (int) $request->query->get('pageOffset', 0);
    if ($page_token !== '') {
      $parameters['pageToken'] = $page_token;
    }
    $parameters['countTotal'] = 'true';
    $parameters['pageSize'] = 50;

    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];
    $count = count($studies);
    $start = $page_offset + 1;
    $end = $page_offset + $count;
    $total = $response['totalCount'] ?? NULL;

    $build = [
      '#attributes' => [
        'class' => ['clinical-trials-gov'],
      ],
      '#attached' => [
        'library' => ['clinical_trials_gov/clinical_trials_gov'],
      ],
      'intro' => [
        '#markup' => '<p>' . $this->t('Review the trials returned by the saved <a href=":find_url">studies query</a> before <a href=":configure_url">configuring the destination content type</a>.', [
          ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
          ':configure_url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
        ]) . '</p>',
      ],
      'studies_query' => [
        '#type' => 'details',
        '#title' => $this->t('Studies query'),
        '#open' => FALSE,
        'summary' => [
          '#type' => 'clinical_trials_gov_studies_query_summary',
          '#query' => $saved_query,
        ],
        'links' => $this->buildActionLinks([
          'find' => [
            'title' => $this->t('Edit studies query'),
            'url' => Url::fromRoute('clinical_trials_gov.find'),
          ],
        ]),
      ],
      'summary' => [
        '#markup' => '<p>' . (($total !== NULL)
          ? $this->t('Showing @start - @end of @total trials.', [
            '@start' => $start,
            '@end' => $end,
            '@total' => $total,
          ])
          : $this->t('Showing @start - @end trials.', [
            '@start' => $start,
            '@end' => $end,
          ])) . '</p>',
      ],
      'results' => $this->builder->buildStudiesList($studies, 'clinical_trials_gov.review.study', ['modal' => TRUE]),
    ];

    if (isset($response['nextPageToken'])) {
      $build['pager'] = [
        '#type' => 'link',
        '#title' => $this->t('Next page'),
        '#url' => Url::fromRoute('clinical_trials_gov.review', ['nctId' => ''], [
          'query' => [
            'pageToken' => $response['nextPageToken'],
            'pageOffset' => $page_offset + $count,
          ],
        ]),
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds a row of button-style action links.
   */
  protected function buildActionLinks(array $links): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov__section-links'],
      ],
    ];

    foreach ($links as $key => $link) {
      $build[$key] = [
        '#type' => 'link',
        '#title' => $link['title'],
        '#url' => $link['url'],
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    return $build;
  }

}
