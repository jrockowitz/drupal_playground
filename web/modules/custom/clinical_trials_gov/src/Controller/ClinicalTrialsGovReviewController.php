<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Traits\ClinicalTrialsGovMessageTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Displays the review step for the import wizard.
 */
class ClinicalTrialsGovReviewController extends ControllerBase {

  use ClinicalTrialsGovMessageTrait;

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
  public function index(Request $request, string $nctId = ''): array {
    if ($nctId !== '') {
      if (!preg_match('/^NCT\d+$/', $nctId)) {
        return $this->buildListPage($request, $this->buildMessages($this->t('The study identifier %nct_id is not valid.', [
          '%nct_id' => $nctId,
        ]), 'warning'));
      }

      $study = $this->manager->getStudy($nctId);
      if ($study === []) {
        return $this->buildListPage($request, $this->buildMessages($this->t('Study %nct_id was not found. Review the saved query results below.', [
          '%nct_id' => $nctId,
        ]), 'warning'));
      }

      return $this->builder->buildStudy($study, $nctId);
    }

    return $this->buildListPage($request);
  }

  /**
   * Returns the page title for the review route.
   */
  public function title(string $nctId = ''): string {
    if ($nctId === '' || !preg_match('/^NCT\d+$/', $nctId)) {
      return (string) $this->t('Review');
    }

    $study = $this->manager->getStudy($nctId);
    return (string) ($study['protocolSection.identificationModule.briefTitle'] ?? $this->t('Review'));
  }

  /**
   * Builds the saved-query studies list page.
   */
  protected function buildListPage(Request $request, ?array $message = NULL): array {
    $saved_query = (string) ($this->config('clinical_trials_gov.settings')->get('query') ?? '');
    if ($saved_query === '') {
      return [
        'message' => $this->buildMessages('No saved query was found. Start with the Find step.', 'warning'),
      ];
    }

    $parameters = \Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery::parseQueryString($saved_query);
    $page_token = (string) $request->query->get('pageToken', '');
    $page_offset = (int) $request->query->get('pageOffset', 0);
    if ($page_token !== '') {
      $parameters['pageToken'] = $page_token;
    }
    $parameters['countTotal'] = 'true';

    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];
    $count = count($studies);
    $start = $page_offset + 1;
    $end = $page_offset + $count;
    $total = $response['totalCount'] ?? NULL;

    $build = [
      'intro' => [
        '#markup' => '<p>' . $this->t('Review the studies returned by the saved <a href=":find_url">query</a> before <a href=":configure_url">configuring the destination content type</a>.', [
          ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
          ':configure_url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
        ]) . '</p>',
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
      'results' => $this->builder->buildStudiesList($studies, 'clinical_trials_gov.review'),
    ];

    if ($message !== NULL) {
      $build = ['message' => $message] + $build;
    }

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

}
