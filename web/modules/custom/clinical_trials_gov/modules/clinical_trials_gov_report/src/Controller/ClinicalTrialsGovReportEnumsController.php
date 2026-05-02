<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov enums report.
 */
class ClinicalTrialsGovReportEnumsController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new self(
      $container->get('clinical_trials_gov.study_manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renders the enums report page.
   */
  public function index(): array {
    $enums = $this->studyManager->getEnums();
    $version = $this->studyManager->getVersion();
    $api_url = ClinicalTrialsGovApi::BASE_URL . '/studies/enums';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov-report-enums'],
      ],
      '#attached' => [
        'library' => ['clinical_trials_gov_report/report'],
      ],
      'intro' => [
        '#type' => 'item',
        '#markup' => $this->t('This page displays ClinicalTrials.gov enumeration types and their allowed values returned by the API.'),
      ],
      'summary' => [
        '#type' => 'item',
        '#markup' => $this->t('Showing @count enum types', ['@count' => count($enums)]),
      ],
      'results' => $this->buildEnumsTable($enums),
      'api_url' => [
        '#type' => 'item',
        '#markup' => $this->t('ClinicalTrials.gov API: <a href=":url" class="font-monospace">@url</a>', [
          ':url' => $api_url,
          '@url' => $api_url,
        ]),
      ],
      'version_separator' => [
        '#type' => 'html_tag',
        '#tag' => 'hr',
      ],
      'version' => [
        '#type' => 'item',
        '#markup' => $this->buildVersionMarkup($version),
      ],
    ];
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

  /**
   * Builds the enums table.
   */
  protected function buildEnumsTable(array $enums): array {
    $rows = [];

    foreach ($enums as $enum) {
      if (!is_array($enum)) {
        continue;
      }

      $rows[] = [
        $this->buildTextCell((string) ($enum['type'] ?? '')),
        $this->buildValuesCell($enum['values'] ?? []),
        $this->buildListCell($enum['pieces'] ?? []),
      ];
    }

    return [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['clinical-trials-gov-table'],
      ],
      '#header' => [
        $this->t('Enum Type'),
        $this->t('Values'),
        $this->t('Pieces'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No enums returned.'),
    ];
  }

  /**
   * Builds the values cell.
   */
  protected function buildValuesCell(mixed $values): array|string {
    if (!is_array($values) || $values === []) {
      return '';
    }

    $items = [];
    foreach ($values as $value) {
      if (!is_array($value)) {
        continue;
      }

      $raw_value = (string) ($value['value'] ?? '');
      $legacy_value = (string) ($value['legacyValue'] ?? '');

      if (!$raw_value && !$legacy_value) {
        continue;
      }

      $items[] = ($legacy_value)
        ? $legacy_value . ' (' . $raw_value . ')'
        : $raw_value;
    }

    return $this->buildListCell($items);
  }

  /**
   * Builds a multi-line list cell.
   */
  protected function buildListCell(mixed $values): array|string {
    if (!is_array($values) || $values === []) {
      return '';
    }

    $items = array_values(array_filter(array_map(
      fn(mixed $item): string => is_scalar($item) ? (string) $item : '',
      $values
    )));

    return $this->buildTextCell(implode("\n", $items));
  }

  /**
   * Builds a plain text cell preserving line breaks.
   */
  protected function buildTextCell(string $value): array|string {
    if (!$value) {
      return '';
    }

    return [
      'data' => [
        '#markup' => nl2br(Html::escape($value)),
      ],
    ];
  }

}
