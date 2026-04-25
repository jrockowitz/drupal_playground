<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\clinical_trials_gov_report\Form\ClinicalTrialsGovReportStudiesSearchForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders the ClinicalTrials.gov studies list report.
 */
class ClinicalTrialsGovReportStudiesController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * Renders the studies list page with the search form and results table.
   */
  public function index(Request $request): array {
    $build = [];
    $build['search_form'] = $this->formBuilder()->getForm(ClinicalTrialsGovReportStudiesSearchForm::class);

    $query_string = $request->getQueryString() ?? '';
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query_string);

    $page_offset = (int) ($parameters['pageOffset'] ?? 0);
    unset($parameters['pageOffset']);

    $response = $this->manager->getStudies($parameters);
    $studies = $response['studies'] ?? [];

    if (!empty($studies) || isset($response['totalCount'])) {
      $count = count($studies);
      $start = $page_offset + 1;
      $end = $page_offset + $count;
      $total = $response['totalCount'] ?? NULL;

      $build['summary'] = [
        '#type' => 'item',
        '#markup' => ($total !== NULL)
          ? $this->t('Showing @start to @end of @total trials', [
            '@start' => $start,
            '@end' => $end,
            '@total' => $total,
          ])
          : $this->t('Showing @start to @end', ['@start' => $start, '@end' => $end]),
      ];
    }

    $build['results'] = $this->buildResultsTable($studies);

    if (isset($response['nextPageToken'])) {
      $next_parameters = $parameters;
      $next_parameters['pageToken'] = $response['nextPageToken'];
      $next_parameters['pageOffset'] = $page_offset + count($studies);
      $build['pager'] = [
        '#type' => 'item',
        '#markup' => $this->t('<a href=":url" class="button">Next page &#8250;</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov_report.studies', [], ['query' => $next_parameters])->toString(),
        ]),
      ];
    }

    return $build;
  }

  /**
   * Builds the results table from a raw studies array.
   */
  protected function buildResultsTable(array $studies): array {
    $rows = [];
    foreach ($studies as $study) {
      $identification = $study['protocolSection']['identificationModule'] ?? [];
      $status_module = $study['protocolSection']['statusModule'] ?? [];
      $design_module = $study['protocolSection']['designModule'] ?? [];
      $conditions_module = $study['protocolSection']['conditionsModule'] ?? [];

      $nct_id = $identification['nctId'] ?? '';
      $title = $identification['briefTitle'] ?? '';
      $status = $status_module['overallStatus'] ?? '';
      $phases = implode(', ', $design_module['phases'] ?? []);
      $conditions = implode(', ', $conditions_module['conditions'] ?? []);

      $nct_link = $nct_id
        ? $this->t('<a href=":url">@nct</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov_report.study', ['nctId' => $nct_id])->toString(),
          '@nct' => $nct_id,
        ])
        : '';

      $rows[] = [$nct_link, $title, $status, $phases, $conditions];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('NCT ID'),
        $this->t('Title'),
        $this->t('Overall status'),
        $this->t('Phases'),
        $this->t('Conditions'),
      ],
      '#rows' => $rows,
    ];
  }

}
