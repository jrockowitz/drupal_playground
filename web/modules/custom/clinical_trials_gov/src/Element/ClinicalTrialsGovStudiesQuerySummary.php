<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders a summary table for a ClinicalTrials.gov studies query.
 */
#[RenderElement('clinical_trials_gov_studies_query_summary')]
class ClinicalTrialsGovStudiesQuerySummary extends RenderElementBase {

  /**
   * Keys that should be displayed as multiple values.
   */
  protected const MULTI_VALUE_KEYS = [
    'filter.overallStatus',
    'filter.ids',
    'filter.synonyms',
    'postFilter.overallStatus',
    'postFilter.ids',
    'postFilter.synonyms',
    'fields',
    'sort',
  ];

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#query' => '',
      '#pre_render' => [[static::class, 'preRenderSummary']],
    ];
  }

  /**
   * Builds the studies query summary table.
   */
  public static function preRenderSummary(array $element): array {
    $query = (string) ($element['#query'] ?? '');
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
    $study_manager = \Drupal::service('clinical_trials_gov.study_manager');
    $definitions = ClinicalTrialsGovStudiesQuery::fieldDefinitions($study_manager->getEnum('Status'));
    $rows = [];
    $handled_parameters = [];

    foreach ($definitions as $definition) {
      $key = (string) ($definition['key'] ?? '');
      if (!$key || !isset($parameters[$key])) {
        continue;
      }

      $rows[] = [
        static::buildParameterCell($definition),
        static::formatValue($key, (string) $parameters[$key]),
      ];
      $handled_parameters[$key] = TRUE;
    }

    foreach ($parameters as $key => $value) {
      if (isset($handled_parameters[$key])) {
        continue;
      }

      $rows[] = [
        static::buildFallbackParameterCell($key),
        static::formatValue($key, (string) $value),
      ];
    }

    $element['#type'] = 'table';
    $element['#theme'] = 'table';
    $element['#attributes']['class'][] = 'clinical-trials-gov-table';
    $element['#header'] = [
      [
        'data' => new TranslatableMarkup('Parameter'),
        'style' => 'width: 50%',
      ],
      [
        'data' => new TranslatableMarkup('Value'),
        'style' => 'width: 50%',
      ],
    ];
    $element['#rows'] = $rows;

    return $element;
  }

  /**
   * Builds the first-column cell for a known query parameter.
   */
  protected static function buildParameterCell(array $definition): array {
    return [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ title }}</strong><br><small><code>{{ key }}</code></small>',
        '#context' => [
          'title' => (string) ($definition['label'] ?? ''),
          'key' => (string) ($definition['key'] ?? ''),
        ],
      ],
    ];
  }

  /**
   * Builds the first-column cell for an unknown query parameter.
   */
  protected static function buildFallbackParameterCell(string $key): array {
    return [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<strong><code>{{ key }}</code></strong><br><small>{{ fallback }}</small>',
        '#context' => [
          'key' => $key,
          'fallback' => new TranslatableMarkup('Unknown parameter'),
        ],
      ],
    ];
  }

  /**
   * Formats a query parameter value for summary display.
   */
  protected static function formatValue(string $key, string $value): string {
    if (in_array($key, self::MULTI_VALUE_KEYS, TRUE)) {
      $parts = array_values(array_filter(array_map('trim', explode('|', $value))));
      return implode(', ', $parts);
    }

    return $value;
  }

}
