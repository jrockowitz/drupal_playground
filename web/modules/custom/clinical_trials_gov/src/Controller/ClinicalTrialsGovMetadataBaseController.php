<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Shared metadata page controller logic.
 */
abstract class ClinicalTrialsGovMetadataBaseController extends ControllerBase {

  /**
   * Whether to filter metadata to the configured paths.
   */
  protected bool $filter = TRUE;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ConfigFactoryInterface $configurationFactory,
    protected MessengerInterface $messageHandler,
  ) {}

  /**
   * Builds the metadata page.
   */
  public function index(): array {
    if ($this->filter && $this->getSavedQuery() === '') {
      $this->messageHandler->addWarning(Markup::create((string) $this->t('No saved query was found. Start with the <a href=":find_url">Find</a> step.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ])));
      return [];
    }

    $metadata = $this->getDisplayedMetadata();
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $this->getContainerClasses(),
      ],
      '#attached' => [
        'library' => $this->getAttachedLibraries(),
      ],
      'intro' => $this->buildIntro(),
      'summary' => [
        '#type' => 'item',
        '#markup' => $this->t('Showing @count fields', ['@count' => count($metadata)]),
      ],
      'results' => $this->buildMetadataTable($metadata),
    ];

    $footer = $this->buildFooter();
    if ($footer !== []) {
      $build['footer'] = $footer;
    }

    return $build;
  }

  /**
   * Builds the page intro section.
   */
  abstract protected function buildIntro(): array;

  /**
   * Builds the page footer section.
   */
  abstract protected function buildFooter(): array;

  /**
   * Returns the libraries attached to the page.
   */
  protected function getAttachedLibraries(): array {
    return ['clinical_trials_gov/report'];
  }

  /**
   * Returns the page container classes.
   */
  protected function getContainerClasses(): array {
    return ['clinical-trials-gov-report-metadata'];
  }

  /**
   * Returns configured metadata paths.
   */
  protected function getConfiguredPaths(): array {
    return array_values(array_filter(
      $this->configurationFactory->get('clinical_trials_gov.settings')->get('paths') ?? [],
      'is_string'
    ));
  }

  /**
   * Returns the saved query string.
   */
  protected function getSavedQuery(): string {
    return (string) ($this->configurationFactory->get('clinical_trials_gov.settings')->get('query') ?? '');
  }

  /**
   * Returns the metadata rows that should be displayed.
   */
  protected function getDisplayedMetadata(): array {
    $metadata = $this->manager->getMetadataByPath();
    if (!$this->filter) {
      return $metadata;
    }

    $path_lookup = array_fill_keys($this->getConfiguredPaths(), TRUE);
    if ($path_lookup === []) {
      return [];
    }

    $displayed_metadata = [];
    foreach ($metadata as $path => $row) {
      if (!is_array($row) || !isset($path_lookup[$path])) {
        continue;
      }
      $displayed_metadata[$path] = $row;
    }

    return $displayed_metadata;
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

      $rows[] = [
        'data' => $this->buildRow($row),
        'class' => [],
      ];
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
