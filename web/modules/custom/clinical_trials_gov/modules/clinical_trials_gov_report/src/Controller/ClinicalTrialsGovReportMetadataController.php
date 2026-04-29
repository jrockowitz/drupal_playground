<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov metadata report.
 */
class ClinicalTrialsGovReportMetadataController extends ControllerBase {

  /**
   * Metadata report table columns.
   */
  protected const COLUMNS = [
    'field_name',
    'field_title',
    'alt_piece_names',
    'classic_type',
    'data_type',
    'definition',
    'description',
    'notes',
    'index_field',
  ];

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new self(
      $container->get('clinical_trials_gov.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renders the metadata report page.
   */
  public function index(): array {
    $metadata = $this->manager->getMetadataByPath();
    $version = $this->manager->getVersion();
    $api_url = ClinicalTrialsGovApi::BASE_URL . '/studies/metadata';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov-report-metadata'],
      ],
      '#attached' => [
        'library' => ['clinical_trials_gov_report/report'],
      ],
      'intro' => [
        '#type' => 'item',
        '#markup' => $this->t('This page displays flattened ClinicalTrials.gov fields metadata returned by the API.'),
      ],
      'summary' => [
        '#type' => 'item',
        '#markup' => $this->t('Showing @count fields', ['@count' => count($metadata)]),
      ],
      'results' => $this->buildMetadataTable($metadata),
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

    if ($timestamp !== '') {
      $date_time = strtotime($timestamp . ' UTC');
      if ($date_time !== FALSE) {
        $formatted_timestamp = $this->dateFormatter->format($date_time, 'custom', 'F j Y \a\t g:i a');
      }
    }

    return '<small>' . $this->t('Version: @version and Last Updated: @updated', [
      '@version' => $api_version,
      '@updated' => $formatted_timestamp,
    ]) . '</small>';
  }

  /**
   * Builds the metadata table.
   */
  protected function buildMetadataTable(array $metadata): array {
    $rows = [];

    foreach ($metadata as $row) {
      if (!is_array($row)) {
        continue;
      }

      $rows[] = $this->buildRow($row);
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->buildHeader('Field Name', 'Piece Name'),
        $this->t('Field Title'),
        $this->t('Alt Piece Names'),
        $this->t('Classic Type'),
        $this->t('Data Type'),
        $this->t('Definition'),
        $this->t('Description'),
        $this->t('Notes'),
        $this->buildHeader('Index Field', ''),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No metadata returned.'),
    ];
  }

  /**
   * Builds one metadata row in documentation-table format.
   */
  protected function buildRow(array $row): array {
    $depth = substr_count((string) ($row['path'] ?? ''), '.');
    $classic_type = (string) ($row['sourceType'] ?? '');
    $max_chars = $row['maxChars'] ?? NULL;
    if (is_int($max_chars) && $classic_type !== '') {
      $classic_type .= ' (max ' . $max_chars . ' chars)';
    }

    return [
      $this->buildPrimarySecondaryCell(
        primary: (string) ($row['name'] ?? ''),
        secondary: (string) ($row['piece'] ?? ''),
        depth: $depth,
      ),
      $this->buildTextCell((string) ($row['title'] ?? '')),
      $this->buildListCell($row['altPieceNames'] ?? []),
      $this->buildTextCell($classic_type),
      $this->buildDataTypeCell($row),
      $this->buildDefinitionCell($row),
      $this->buildTextCell((string) ($row['description'] ?? '')),
      $this->buildTextCell((string) ($row['rules'] ?? '')),
      $this->buildPrimarySecondaryCell(
        primary: (string) ($row['path'] ?? ''),
        secondary: '',
        depth: 0,
      ),
    ];
  }

  /**
   * Builds a header cell with optional secondary text.
   */
  protected function buildHeader(string $primary, string $secondary): array {
    $markup = '<strong>' . Html::escape($primary) . '</strong>';
    if ($secondary !== '') {
      $markup .= '<br><span class="clinical-trials-gov-report-metadata__secondary">'
        . Html::escape($secondary)
        . '</span>';
    }

    return [
      'data' => Markup::create($markup),
    ];
  }

  /**
   * Builds a cell with primary and secondary lines.
   */
  protected function buildPrimarySecondaryCell(string $primary, string $secondary, int $depth = 0): array {
    $style = ($depth > 0) ? ' style="padding-left:' . ($depth * 1.5) . 'rem"' : '';
    $markup = '<div class="clinical-trials-gov-report-metadata__primary"' . $style . '><strong>'
      . Html::escape($primary)
      . '</strong></div>';

    if ($secondary !== '') {
      $markup .= '<div class="clinical-trials-gov-report-metadata__secondary"' . $style . '>'
        . Html::escape($secondary)
        . '</div>';
    }

    return [
      'data' => Markup::create($markup),
    ];
  }

  /**
   * Builds a plain text cell preserving line breaks.
   */
  protected function buildTextCell(string $value): array|string {
    if ($value === '') {
      return '';
    }

    return [
      'data' => [
        '#markup' => nl2br(Html::escape($value)),
      ],
    ];
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
   * Builds the data type cell.
   */
  protected function buildDataTypeCell(array $row): array|string {
    $value = (string) ($row['type'] ?? '');
    $suffixes = [];

    if (!empty($row['synonyms'])) {
      $suffixes[] = 'synonyms';
    }

    if ($suffixes !== []) {
      $value .= ' (' . implode(', ', $suffixes) . ')';
    }

    return $this->buildTextCell($value);
  }

  /**
   * Builds the definition cell.
   */
  protected function buildDefinitionCell(array $row): array|string {
    $label = (string) ($row['dedLinkLabel'] ?? '');
    $url = (string) ($row['dedLinkUrl'] ?? '');

    if ($label === '') {
      return '';
    }

    if ($url === '') {
      return $this->buildTextCell($label);
    }

    return [
      'data' => [
        '#type' => 'link',
        '#title' => $label,
        '#url' => Url::fromUri($url),
      ],
    ];
  }

}
