<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov structs report.
 */
class ClinicalTrialsGovReportStructsController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
    protected ClinicalTrialsGovManagerInterface $manager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new self(
      $container->get('clinical_trials_gov.field_manager'),
      $container->get('clinical_trials_gov.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renders the structs report page.
   */
  public function index(): array {
    $metadata = $this->manager->getMetadataByPath();
    $used_paths = $this->fieldManager->getAvailableFieldKeys();
    $struct_rows = $this->buildStructRows($metadata, $used_paths);
    $version = $this->manager->getVersion();
    $api_url = ClinicalTrialsGovApi::BASE_URL . '/studies/metadata';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov-report-structs'],
      ],
      '#attached' => [
        'library' => ['clinical_trials_gov_report/report'],
      ],
      'intro' => [
        '#type' => 'item',
        '#markup' => $this->t('This page displays ClinicalTrials.gov struct metadata and hierarchy returned by the API.'),
      ],
      'summary' => [
        '#type' => 'item',
        '#markup' => $this->t('Showing @count structs', ['@count' => count($struct_rows)]),
      ],
      'results' => $this->buildStructsTable($struct_rows),
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
   * Builds normalized struct rows from metadata.
   */
  protected function buildStructRows(array $metadata, array $used_paths = []): array {
    $rows = [];
    $used_path_lookup = array_fill_keys(array_values(array_filter($used_paths, 'is_string')), TRUE);

    foreach ($metadata as $path => $row) {
      if (!is_array($row) || (($row['sourceType'] ?? '') !== 'STRUCT')) {
        continue;
      }

      $sub_properties = [];
      foreach (($row['children'] ?? []) as $child_path) {
        if (!is_string($child_path) || !isset($metadata[$child_path]) || !is_array($metadata[$child_path])) {
          continue;
        }
        $sub_properties[] = [
          'name' => (string) ($metadata[$child_path]['name'] ?? $child_path),
          'is_struct' => (($metadata[$child_path]['sourceType'] ?? '') === 'STRUCT'),
          'is_multiple' => str_ends_with((string) ($metadata[$child_path]['type'] ?? ''), '[]'),
        ];
      }

      $rows[$path] = [
        'path' => $path,
        'name' => (string) ($row['name'] ?? ''),
        'piece' => (string) ($row['piece'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'data_type' => (string) ($row['type'] ?? ''),
        'parent_struct' => $this->findParentStructPath($path, $metadata),
        'is_nested_multiple' => $this->isNestedMultipleStruct($path, $metadata),
        'is_unused' => ($used_path_lookup !== [] && !isset($used_path_lookup[$path])),
        'sub_properties' => $sub_properties,
      ];
    }

    return $rows;
  }

  /**
   * Finds the nearest ancestor path that is also a struct.
   */
  protected function findParentStructPath(string $path, array $metadata): string {
    $parts = explode('.', $path);
    array_pop($parts);

    while ($parts !== []) {
      $candidate = implode('.', $parts);
      if (isset($metadata[$candidate]) && is_array($metadata[$candidate]) && (($metadata[$candidate]['sourceType'] ?? '') === 'STRUCT')) {
        return $candidate;
      }
      array_pop($parts);
    }

    return '';
  }

  /**
   * Determines if a repeatable struct is nested within another repeatable struct.
   */
  protected function isNestedMultipleStruct(string $path, array $metadata): bool {
    if (
      !isset($metadata[$path])
      || !is_array($metadata[$path])
      || !str_ends_with((string) ($metadata[$path]['type'] ?? ''), '[]')
    ) {
      return FALSE;
    }

    $parent_struct = $this->findParentStructPath($path, $metadata);

    while ($parent_struct !== '') {
      if (
        isset($metadata[$parent_struct])
        && is_array($metadata[$parent_struct])
        && str_ends_with((string) ($metadata[$parent_struct]['type'] ?? ''), '[]')
      ) {
        return TRUE;
      }

      $parent_struct = $this->findParentStructPath($parent_struct, $metadata);
    }

    return FALSE;
  }

  /**
   * Builds the structs table.
   */
  protected function buildStructsTable(array $struct_rows): array {
    $rows = [];

    foreach ($struct_rows as $row) {
      $classes = [];
      if ($row['is_nested_multiple']) {
        $classes[] = 'color-warning';
      }
      if ($row['is_unused']) {
        $classes[] = 'clinical-trials-gov-report-structs__row--unused';
      }

      $rows[] = [
        'data' => [
          $this->buildPrimarySecondaryCell(
            primary: $row['name'],
            secondary: ($row['title'] !== '') ? $row['title'] : $row['piece'],
            depth: substr_count($row['path'], '.'),
          ),
          $this->buildTextCell($row['piece']),
          $this->buildTextCell($row['data_type']),
          $this->buildSubPropertiesCell($row['sub_properties']),
        ],
        'class' => $classes,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Struct'),
        $this->t('Piece'),
        $this->t('Data type'),
        $this->t('Sub-properties'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No structs returned.'),
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
   * Builds a multi-line list cell.
   */
  protected function buildListCell(array $values): array|string {
    if ($values === []) {
      return '';
    }

    $items = array_values(array_filter(array_map(
      fn(mixed $item): string => is_scalar($item) ? (string) $item : '',
      $values
    )));

    return $this->buildTextCell(implode("\n", $items));
  }

  /**
   * Builds the sub-properties cell as a small bullet list.
   */
  protected function buildSubPropertiesCell(array $values): array|string {
    if ($values === []) {
      return '';
    }

    $items = [];
    foreach ($values as $value) {
      if (!is_array($value)) {
        continue;
      }

      $name = (string) ($value['name'] ?? '');
      if ($name === '') {
        continue;
      }

      if (!empty($value['is_multiple'])) {
        $name .= '[]';
      }

      if (!empty($value['is_struct'])) {
        $name = '<strong>' . Html::escape($name) . '</strong>';
      }
      else {
        $name = Html::escape($name);
      }

      $items[] = Markup::create($name);
    }

    if ($items === []) {
      return '';
    }

    return [
      'data' => [
        '#prefix' => '<small class="clinical-trials-gov-report-structs__sub-properties">',
        '#theme' => 'item_list',
        '#items' => $items,
        '#suffix' => '</small>',
      ],
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

}
