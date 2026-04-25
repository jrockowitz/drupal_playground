<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

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
class ClinicalTrialsGovStudiesQuery extends FormElement {

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

    $element['query_parameters'] = [
      '#type' => 'details',
      '#title' => t('Query parameters'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($query_keys))),
    ];
    foreach (static::queryParameterDefinitions() as $definition) {
      $name = static::apiKeyToElementName($definition['key']);
      $element['query_parameters'][$name] = static::buildTextField(
        $definition['label'],
        $definition['description'],
        $defaults[$definition['key']] ?? '',
      );
    }

    $element['filters'] = [
      '#type' => 'details',
      '#title' => t('Filters'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($filter_keys))),
    ];
    $overall_status_options = ['' => t('- Any -')];
    foreach ($manager->getEnum('OverallStatus') as $status) {
      $overall_status_options[$status] = $status;
    }
    $element['filters']['filter__overallStatus'] = [
      '#type' => 'select',
      '#title' => t('Overall status'),
      '#description' => t('See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#options' => $overall_status_options,
      '#default_value' => $defaults['filter.overallStatus'] ?? '',
    ];
    $element['filters']['filter__geo'] = static::buildTextField('Geographic filter', 'e.g. distance(39.0035,-77.1088,50mi)', $defaults['filter.geo'] ?? '');
    $element['filters']['filter__ids'] = static::buildTextField('NCT ID filter', 'Pipe-separated NCT IDs', $defaults['filter.ids'] ?? '');
    $element['filters']['filter__advanced'] = static::buildTextField('Advanced filter', 'Essie expression syntax', $defaults['filter.advanced'] ?? '');
    $element['filters']['aggFilters'] = static::buildTextField('Aggregation filters', 'e.g. phase:phase2,studyType:int', $defaults['aggFilters'] ?? '');

    $element['pagination'] = [
      '#type' => 'details',
      '#title' => t('Pagination and sort'),
      '#open' => !empty(array_intersect_key($defaults, array_flip($pagination_keys))),
    ];
    $element['pagination']['pageSize'] = [
      '#type' => 'number',
      '#title' => t('Page size'),
      '#description' => t('Results per page (1–1000). Default: 10. See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#min' => 1,
      '#max' => 1000,
      '#default_value' => $defaults['pageSize'] ?? '',
    ];
    $element['pagination']['pageToken'] = static::buildTextField('Page token', 'Pagination cursor from previous response', $defaults['pageToken'] ?? '');
    $element['pagination']['countTotal'] = [
      '#type' => 'select',
      '#title' => t('Count total'),
      '#description' => t('Include total match count in response. See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']),
      '#options' => ['' => t('- Default -'), 'true' => t('Yes'), 'false' => t('No')],
      '#default_value' => $defaults['countTotal'] ?? '',
    ];
    $element['pagination']['sort'] = static::buildTextField('Sort', 'Field and direction, e.g. LastUpdatePostDate:desc', $defaults['sort'] ?? '');

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
      if (!isset($element[$group])) {
        continue;
      }
      foreach ($element[$group] as $name => $child) {
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
      ['key' => 'query.cond', 'label' => 'Condition or disease', 'description' => 'e.g. cancer, heart disease'],
      ['key' => 'query.term', 'label' => 'Other search terms', 'description' => 'Full-text search across all fields'],
      ['key' => 'query.locn', 'label' => 'Location terms', 'description' => 'e.g. Boston, MA'],
      ['key' => 'query.titles', 'label' => 'Title or acronym', 'description' => ''],
      ['key' => 'query.intr', 'label' => 'Intervention or treatment', 'description' => ''],
      ['key' => 'query.outc', 'label' => 'Outcome measure', 'description' => ''],
      ['key' => 'query.spons', 'label' => 'Sponsor or collaborator', 'description' => ''],
      ['key' => 'query.lead', 'label' => 'Lead sponsor', 'description' => ''],
      ['key' => 'query.id', 'label' => 'NCT number or study ID', 'description' => 'e.g. NCT04001699'],
    ];
  }

  /**
   * Builds a textfield sub-element with a link to the API documentation.
   */
  protected static function buildTextField(string $label, string $description, string $default_value): array {
    $description_text = $description
      ? t('@description. See <a href=":url">API documentation</a>.', [
        '@description' => $description,
        ':url' => 'https://clinicaltrials.gov/data-api/api',
      ])
      : t('See <a href=":url">API documentation</a>.', [':url' => 'https://clinicaltrials.gov/data-api/api']);
    return [
      '#type' => 'textfield',
      '#title' => t('@label', ['@label' => $label]),
      '#description' => $description_text,
      '#default_value' => $default_value,
    ];
  }

}
