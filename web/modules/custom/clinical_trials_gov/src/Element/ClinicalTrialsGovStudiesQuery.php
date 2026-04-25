<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Composite form element for the ClinicalTrials.gov /studies query interface.
 *
 * Accepts a raw query string as #default_value and returns a raw query string
 * from its #element_validate callback.
 *
 * Usage:
 * @code
 * $form['query'] = [
 *   '#type' => 'clinical_trials_gov_studies_query',
 *   '#default_value' => 'query.cond=cancer&filter.overallStatus=RECRUITING',
 * ];
 * @endcode
 *
 * @FormElement("clinical_trials_gov_studies_query")
 */
class ClinicalTrialsGovStudiesQuery extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#input' => TRUE,
      '#default_value' => '',
      '#process' => [[static::class, 'processStudiesQuery']],
      '#element_validate' => [[static::class, 'validateStudiesQuery']],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Parses a query string while preserving dot-notation keys.
   *
   * PHP's parse_str() and $_GET convert dots to underscores. This method
   * splits on '&' and '=' manually to preserve keys like 'query.cond'.
   */
  public static function parseQueryString(string $query_string): array {
    $result = [];
    if ($query_string === '') {
      return $result;
    }
    foreach (explode('&', $query_string) as $pair) {
      [$raw_key, $raw_value] = array_pad(explode('=', $pair, 2), 2, '');
      $key = urldecode($raw_key);
      if ($key !== '') {
        $result[$key] = urldecode($raw_value);
      }
    }
    return $result;
  }

  /**
   * Converts an API key like 'query.cond' to a safe element name 'query__cond'.
   */
  public static function apiKeyToElementName(string $key): string {
    return str_replace('.', '__', $key);
  }

  /**
   * Converts an element name 'query__cond' back to an API key 'query.cond'.
   */
  public static function elementNameToApiKey(string $name): string {
    return str_replace('__', '.', $name);
  }

  /**
   * Builds sub-elements from the query string #default_value.
   */
  public static function processStudiesQuery(
    array $element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): array {
    $defaults = static::parseQueryString($element['#default_value'] ?? '');
    $manager = \Drupal::service('clinical_trials_gov.manager');

    $query_keys = [
      'query.cond', 'query.term', 'query.locn', 'query.titles',
      'query.intr', 'query.outc', 'query.spons', 'query.lead', 'query.id',
    ];
    $filter_keys = ['filter.overallStatus', 'filter.geo', 'filter.ids', 'filter.advanced', 'aggFilters'];
    $pagination_keys = ['pageSize', 'pageToken', 'countTotal', 'sort'];

    $has_defaults = !empty($defaults);

    $element['search'] = [
      '#type' => 'details',
      '#title' => t('Search'),
      '#open' => !$has_defaults,
    ];

    $element['search']['query_parameters'] = [
      '#type' => 'details',
      '#title' => t('Query parameters'),
      '#open' => !$has_defaults || !empty(array_intersect_key($defaults, array_flip($query_keys))),
    ];
    foreach (static::queryParameterDefinitions() as $definition) {
      $name = static::apiKeyToElementName($definition['key']);
      $element['search']['query_parameters'][$name] = static::buildTextField(
        $definition['label'],
        $definition['description'],
        $defaults[$definition['key']] ?? '',
      );
    }

    $element['search']['filters'] = [
      '#type' => 'details',
      '#title' => t('Filters'),
      '#open' => !$has_defaults || !empty(array_intersect_key($defaults, array_flip($filter_keys))),
    ];
    $overall_status_options = ['' => t('- Any -')];
    foreach ($manager->getEnum('OverallStatus') as $status) {
      $overall_status_options[$status] = $status;
    }
    $element['search']['filters']['filter__overallStatus'] = [
      '#type' => 'select',
      '#title' => t('Overall status (filter.overallStatus)'),
      '#description' => t('Filter by comma- or pipe-separated list of study statuses. Example: NOT_YET_RECRUITING|RECRUITING'),
      '#options' => $overall_status_options,
      '#default_value' => $defaults['filter.overallStatus'] ?? '',
    ];
    $element['search']['filters']['filter__geo'] = static::buildTextField(
      'Geographic filter (filter.geo)',
      'Filter by distance function from a geographic location. Example: distance(39.0035707,-77.1013313,50mi)',
      $defaults['filter.geo'] ?? '',
    );
    $element['search']['filters']['filter__ids'] = static::buildTextField(
      'NCT ID filter (filter.ids)',
      'Filter by NCT IDs, searchable in NCTId and NCTIdAlias fields. Example: NCT04852770|NCT01728545|NCT02109302',
      $defaults['filter.ids'] ?? '',
    );
    $element['search']['filters']['filter__advanced'] = static::buildTextField(
      'Advanced filter (filter.advanced)',
      'Filter using Essie expression syntax. Example: AREA[StartDate]2022',
      $defaults['filter.advanced'] ?? '',
    );
    $element['search']['filters']['aggFilters'] = static::buildTextField(
      'Aggregation filters (aggFilters)',
      'Apply aggregation filters as comma/pipe-separated "filter_id:option_keys" pairs. Example: results:with,status:com',
      $defaults['aggFilters'] ?? '',
    );

    $element['search']['pagination'] = [
      '#type' => 'details',
      '#title' => t('Pagination and sort'),
      '#open' => !$has_defaults || !empty(array_intersect_key($defaults, array_flip($pagination_keys))),
    ];
    $element['search']['pagination']['pageSize'] = [
      '#type' => 'number',
      '#title' => t('Page size (pageSize)'),
      '#description' => t('Maximum studies per response page; capped at 1,000. Default: 10.'),
      '#min' => 1,
      '#max' => 1000,
      '#default_value' => $defaults['pageSize'] ?? '',
    ];
    $element['search']['pagination']['pageToken'] = static::buildTextField(
      'Page token (pageToken)',
      'Token for retrieving subsequent pages from previous response',
      $defaults['pageToken'] ?? '',
    );
    $element['search']['pagination']['countTotal'] = [
      '#type' => 'select',
      '#title' => t('Count total (countTotal)'),
      '#description' => t('When true, returns total study count in first page response.'),
      '#options' => ['' => t('- Default -'), 'true' => t('Yes'), 'false' => t('No')],
      '#default_value' => $defaults['countTotal'] ?? '',
    ];
    $element['search']['pagination']['sort'] = static::buildTextField(
      'Sort (sort)',
      'Comma- or pipe-separated list of sorting options with optional directions (asc/desc). Example: @relevance',
      $defaults['sort'] ?? '',
    );

    return $element;
  }

  /**
   * Assembles sub-element values into a query string and sets it on $form_state.
   */
  public static function validateStudiesQuery(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): void {
    $parts = [];
    foreach (['query_parameters', 'filters', 'pagination'] as $group) {
      if (!isset($element['search'][$group])) {
        continue;
      }
      foreach ($element['search'][$group] as $name => $child) {
        if (!is_array($child) || !array_key_exists('#value', $child)) {
          continue;
        }
        $value = trim((string) $child['#value']);
        if ($value === '') {
          continue;
        }
        $parts[] = rawurlencode(static::elementNameToApiKey($name)) . '=' . rawurlencode($value);
      }
    }
    $form_state->setValueForElement($element, implode('&', $parts));
  }

  /**
   * Returns the ordered definitions for the query parameter group.
   */
  protected static function queryParameterDefinitions(): array {
    return [
      [
        'key' => 'query.cond',
        'label' => 'Condition or disease (query.cond)',
        'description' => '"Conditions or disease" query in Essie expression syntax for the Condition Search Area. Example: lung cancer',
      ],
      [
        'key' => 'query.term',
        'label' => 'Other search terms (query.term)',
        'description' => '"Other terms" query in Essie expression syntax for the Basic Search Area. Example: AREA[LastUpdatePostDate]RANGE[2023-01-15,MAX]',
      ],
      [
        'key' => 'query.locn',
        'label' => 'Location terms (query.locn)',
        'description' => '"Location terms" query in Essie expression syntax for the Location Search Area.',
      ],
      [
        'key' => 'query.titles',
        'label' => 'Title or acronym (query.titles)',
        'description' => '"Title / acronym" query in Essie expression syntax for the Title Search Area.',
      ],
      [
        'key' => 'query.intr',
        'label' => 'Intervention or treatment (query.intr)',
        'description' => '"Intervention / treatment" query in Essie expression syntax for the Intervention Search Area.',
      ],
      [
        'key' => 'query.outc',
        'label' => 'Outcome measure (query.outc)',
        'description' => '"Outcome measure" query in Essie expression syntax for the Outcome Search Area.',
      ],
      [
        'key' => 'query.spons',
        'label' => 'Sponsor or collaborator (query.spons)',
        'description' => '"Sponsor / collaborator" query in Essie expression syntax for the Sponsor Search Area.',
      ],
      [
        'key' => 'query.lead',
        'label' => 'Lead sponsor (query.lead)',
        'description' => 'Searches the "LeadSponsorName" field using Essie expression syntax.',
      ],
      [
        'key' => 'query.id',
        'label' => 'NCT number or study ID (query.id)',
        'description' => '"Study IDs" query in Essie expression syntax for the ID Search Area.',
      ],
    ];
  }

  /**
   * Builds a textfield sub-element.
   */
  protected static function buildTextField(string $label, string $description, string $default_value): array {
    $element = [
      '#type' => 'textfield',
      '#title' => t('@label', ['@label' => $label]),
      '#default_value' => $default_value,
    ];
    if ($description !== '') {
      $element['#description'] = t('@description', ['@description' => $description]);
    }
    return $element;
  }

}
