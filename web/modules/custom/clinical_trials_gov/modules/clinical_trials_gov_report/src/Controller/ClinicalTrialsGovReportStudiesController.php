<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders the ClinicalTrials.gov studies list report.
 */
class ClinicalTrialsGovReportStudiesController extends ControllerBase {

  /**
   * Constructs a new ClinicalTrialsGovReportStudiesController.
   */
  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.manager'),
      $container->get('clinical_trials_gov.builder'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renders the studies list page with the search form and results table.
   */
  public function index(Request $request): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov-report-studies'],
      ],
      '#attached' => [
        'library' => ['clinical_trials_gov_report/report'],
      ],
    ];
    $query_string = $request->getQueryString() ?? '';
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query_string);
    $version = $this->manager->getVersion();

    $build['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('This page displays ClinicalTrials.gov studies returned by the API for the current query-string parameters.'),
    ];
    $build['search_form'] = $this->formBuilder()->getForm('Drupal\clinical_trials_gov_report\Form\ClinicalTrialsGovReportStudiesSearchForm');

    // pageOffset is a client-side display parameter (tracks cumulative row
    // count across pages) and is never sent to the API. It is added to the
    // next-page URL by this controller so the "Showing X to Y" label stays
    // accurate across paginated requests.
    $page_offset = (int) ($parameters['pageOffset'] ?? 0);
    unset($parameters['pageOffset']);
    $api_parameters = $this->buildApiParameters($parameters);

    $response = $this->manager->getStudies($api_parameters);
    $studies = $response['studies'] ?? [];
    $api_url = $this->buildApiUrl($api_parameters);

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

    $build['results'] = $this->builder->buildStudiesList($studies, 'clinical_trials_gov_report.study');

    if ($api_url) {
      $build['api_url'] = [
        '#type' => 'item',
        '#markup' => $this->t('ClinicalTrials.gov API: <a href=":url" class="font-monospace">@url</a>', [
          ':url' => $api_url,
          '@url' => $api_url,
        ]),
      ];
    }

    if (isset($response['nextPageToken'])) {
      $next_parameters = $api_parameters;
      $next_parameters['pageToken'] = $response['nextPageToken'];
      $next_parameters['pageOffset'] = $page_offset + count($studies);

      $build['pager'] = [
        '#type' => 'item',
        '#markup' => $this->t('<a href=":url" class="button">Next page &#8250;</a>', [
          ':url' => Url::fromRoute('clinical_trials_gov_report.studies', [], ['query' => $next_parameters])->toString(),
        ]),
      ];
    }

    $build['version_separator'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];
    $build['version'] = [
      '#type' => 'item',
      '#markup' => $this->buildVersionMarkup($version),
    ];

    return $build;
  }

  /**
   * Builds the upstream ClinicalTrials.gov API URL for the current query.
   */
  protected function buildApiUrl(array $parameters): ?string {
    if (!$parameters) {
      return NULL;
    }

    return ClinicalTrialsGovApi::BASE_URL . '/studies?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
  }

  /**
   * Applies controller defaults to the upstream API query parameters.
   */
  protected function buildApiParameters(array $parameters): array {
    $parameters['countTotal'] = $this->normalizeCountTotal($parameters['countTotal'] ?? NULL);
    return $parameters;
  }

  /**
   * Normalizes the countTotal parameter to the API-supported strings 'true'/'false'.
   *
   * NULL or '' defaults to 'true' so the controller always requests a total
   * count. Recognized falsy strings ('0', 'false', 'no') map to 'false'. Any
   * other unrecognized value also maps to 'false'.
   */
  protected function normalizeCountTotal(mixed $value): string {
    if ($value === NULL || $value === '') {
      return 'true';
    }

    $normalized = strtolower((string) $value);

    return match ($normalized) {
      '1', 'true', 'yes' => 'true',
      default => 'false',
    };
  }

  /**
   * Builds the version line markup.
   */
  protected function buildVersionMarkup(array $version): string {
    $api_version = (string) ($version['apiVersion'] ?? '');
    $timestamp = (string) ($version['dataTimestamp'] ?? '');
    $formatted_timestamp = $timestamp;

    if ($timestamp) {
      $date_time = strtotime($timestamp . ' UTC');
      if ($date_time) {
        $formatted_timestamp = $this->dateFormatter->format($date_time, 'custom', 'F j Y \a\t g:i a');
      }
    }

    return '<small>' . $this->t('Version: @version and Last Updated: @updated', [
      '@version' => $api_version,
      '@updated' => $formatted_timestamp,
    ]) . '</small>';
  }

}
