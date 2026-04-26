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
   * Base URL for ClinicalTrials.gov documentation links.
   */
  protected const DOCS_BASE_URL = 'https://clinicaltrials.gov';

  /**
   * Keys that accept multiple values and serialize to pipe-delimited strings.
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
    $field_definitions = static::fieldDefinitions($manager->getEnum('Status'));

    $element['#attached']['library'][] = 'clinical_trials_gov/studies_query';

    foreach ($field_definitions as $definition) {
      $name = static::apiKeyToElementName($definition['key']);
      $element[$name] = static::buildFieldElement(
        $definition,
        $defaults[$definition['key']] ?? '',
      );
    }

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
    foreach ($element as $name => $child) {
      if (!is_array($child) || !array_key_exists('#value', $child)) {
        continue;
      }
      $api_key = static::elementNameToApiKey($name);
      $value = static::normalizeSubmittedValue($api_key, $child['#value']);
      if ($value === '') {
        continue;
      }
      $parts[] = rawurlencode($api_key) . '=' . rawurlencode($value);
    }
    $form_state->setValueForElement($element, implode('&', $parts));
  }

  /**
   * Returns the ordered field definitions for the query builder.
   */
  protected static function fieldDefinitions(array $overall_status_values): array {
    $overall_status_values = array_values(array_filter(array_map(
      static function (mixed $value): ?string {
        if (is_string($value)) {
          return $value;
        }
        if (is_array($value) && isset($value['value']) && is_string($value['value'])) {
          return $value['value'];
        }
        return NULL;
      },
      $overall_status_values
    )));
    $overall_status_examples = [
      ['values' => ['NOT_YET_RECRUITING', 'RECRUITING'], 'label' => 'NOT_YET_RECRUITING, RECRUITING'],
      ['values' => ['COMPLETED'], 'label' => 'COMPLETED'],
    ];
    $overall_status_allowed = array_map(
      static fn(string $value): array => [
        'value' => [$value],
        'label' => $value,
      ],
      $overall_status_values
    );

    return [
      [
        'key' => 'query.cond',
        'label' => 'Condition or disease (query.cond)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Conditions or disease" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "ConditionSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#ConditionSearch') . '">Search Areas</a> for more details.',
        'examples' => [
          ['value' => 'lung cancer', 'label' => 'lung cancer'],
          ['value' => '(head OR neck) AND pain', 'label' => '(head OR neck) AND pain'],
        ],
      ],
      [
        'key' => 'query.term',
        'label' => 'Other search terms (query.term)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Other terms" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "BasicSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#BasicSearch') . '">Search Areas</a> for more details.',
        'examples' => [
          ['value' => 'AREA[LastUpdatePostDate]RANGE[2023-01-15,MAX]', 'label' => 'AREA[LastUpdatePostDate]RANGE[2023-01-15,MAX]'],
        ],
      ],
      [
        'key' => 'query.locn',
        'label' => 'Location terms (query.locn)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Location terms" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "LocationSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#LocationSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.titles',
        'label' => 'Title or acronym (query.titles)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Title / acronym" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "TitleSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#TitleSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.intr',
        'label' => 'Intervention or treatment (query.intr)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Intervention / treatment" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "InterventionSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#InterventionSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.outc',
        'label' => 'Outcome measure (query.outc)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Outcome measure" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "OutcomeSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#OutcomeSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.spons',
        'label' => 'Sponsor or collaborator (query.spons)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Sponsor / collaborator" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "SponsorSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#SponsorSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.lead',
        'label' => 'Lead sponsor (query.lead)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Searches in "LeadSponsorName" field. See <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#LeadSponsorName') . '">Study Data Structure</a> for more details. The query is in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>.',
      ],
      [
        'key' => 'query.id',
        'label' => 'NCT number or study ID (query.id)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => '"Study IDs" query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>. See "IdSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#IdSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'query.patient',
        'label' => 'Patient query (query.patient)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'See "PatientSearch Area" on <a href="' . static::docsUrl('/data-api/about-api/search-areas#PatientSearch') . '">Search Areas</a> for more details.',
      ],
      [
        'key' => 'filter.overallStatus',
        'label' => 'Overall status (filter.overallStatus)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of statuses.',
        'allowed_values' => $overall_status_allowed,
        'examples' => $overall_status_examples,
      ],
      [
        'key' => 'filter.geo',
        'label' => 'Geographic filter (filter.geo)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Filter by geo-function. Currently only distance function is supported. Format: <code>distance(latitude,longitude,distance)</code>.',
        'pattern' => '^distance\(-?\d+(\.\d+)?,-?\d+(\.\d+)?,\d+(\.\d+)?(km|mi)?\)$',
        'examples' => [
          ['value' => 'distance(39.0035707,-77.1013313,50mi)', 'label' => 'distance(39.0035707,-77.1013313,50mi)'],
        ],
      ],
      [
        'key' => 'filter.ids',
        'label' => 'NCT ID filter (filter.ids)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of NCT IDs (a.k.a. ClinicalTrials.gov identifiers). The provided IDs will be searched in <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#NCTId') . '">NCTId</a> and <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#NCTIdAlias') . '">NCTIdAlias</a> fields.',
        'examples' => [
          ['values' => ['NCT04852770', 'NCT01728545', 'NCT02109302'], 'label' => 'NCT04852770, NCT01728545, NCT02109302'],
        ],
      ],
      [
        'key' => 'filter.advanced',
        'label' => 'Advanced filter (filter.advanced)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Filter by query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>.',
        'examples' => [
          ['value' => 'AREA[StartDate]2022', 'label' => 'AREA[StartDate]2022'],
          ['value' => 'AREA[MinimumAge]RANGE[MIN, 16 years] AND AREA[MaximumAge]RANGE[16 years, MAX]', 'label' => 'AREA[MinimumAge]RANGE[MIN, 16 years] AND AREA[MaximumAge]RANGE[16 years, MAX]'],
        ],
      ],
      [
        'key' => 'filter.synonyms',
        'label' => 'Synonym filter (filter.synonyms)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of <code>area</code>:<code>synonym_id</code> pairs.',
        'examples' => [
          ['values' => ['ConditionSearch:1651367', 'BasicSearch:2013558'], 'label' => 'ConditionSearch:1651367, BasicSearch:2013558'],
        ],
      ],
      [
        'key' => 'postFilter.overallStatus',
        'label' => 'Post-filter overall status (postFilter.overallStatus)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of statuses.',
        'allowed_values' => $overall_status_allowed,
        'examples' => $overall_status_examples,
      ],
      [
        'key' => 'postFilter.geo',
        'label' => 'Post-filter geographic filter (postFilter.geo)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Filter by geo-function. Currently only distance function is supported. Format: <code>distance(latitude,longitude,distance)</code>.',
        'pattern' => '^distance\(-?\d+(\.\d+)?,-?\d+(\.\d+)?,\d+(\.\d+)?(km|mi)?\)$',
        'examples' => [
          ['value' => 'distance(39.0035707,-77.1013313,50mi)', 'label' => 'distance(39.0035707,-77.1013313,50mi)'],
        ],
      ],
      [
        'key' => 'postFilter.ids',
        'label' => 'Post-filter NCT IDs (postFilter.ids)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of NCT IDs (a.k.a. ClinicalTrials.gov identifiers). The provided IDs will be searched in <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#NCTId') . '">NCTId</a> and <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#NCTIdAlias') . '">NCTIdAlias</a> fields.',
        'examples' => [
          ['values' => ['NCT04852770', 'NCT01728545', 'NCT02109302'], 'label' => 'NCT04852770, NCT01728545, NCT02109302'],
        ],
      ],
      [
        'key' => 'postFilter.advanced',
        'label' => 'Post-filter advanced query (postFilter.advanced)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Filter by query in <a href="' . static::docsUrl('/find-studies/constructing-complex-search-queries') . '">Essie expression syntax</a>.',
        'examples' => [
          ['value' => 'AREA[StartDate]2022', 'label' => 'AREA[StartDate]2022'],
          ['value' => 'AREA[MinimumAge]RANGE[MIN, 16 years] AND AREA[MaximumAge]RANGE[16 years, MAX]', 'label' => 'AREA[MinimumAge]RANGE[MIN, 16 years] AND AREA[MaximumAge]RANGE[16 years, MAX]'],
        ],
      ],
      [
        'key' => 'postFilter.synonyms',
        'label' => 'Post-filter synonyms (postFilter.synonyms)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Filter by comma- or pipe-separated list of <code>area</code>:<code>synonym_id</code> pairs.',
        'examples' => [
          ['values' => ['ConditionSearch:1651367', 'BasicSearch:2013558'], 'label' => 'ConditionSearch:1651367, BasicSearch:2013558'],
        ],
      ],
      [
        'key' => 'aggFilters',
        'label' => 'Aggregation filters (aggFilters)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Apply aggregation filters, aggregation counts will not be provided. The value is comma- or pipe-separated list of pairs <code>filter_id</code>:<code>space-separated list of option keys</code> for the checked options.',
        'examples' => [
          ['value' => 'results:with,status:com', 'label' => 'results:with,status:com'],
          ['value' => 'status:not rec,sex:f,healthy:y', 'label' => 'status:not rec,sex:f,healthy:y'],
        ],
      ],
      [
        'key' => 'geoDecay',
        'label' => 'Geo decay (geoDecay)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Set proximity factor by distance from <code>filter.geo</code> location to the closest <a href="' . static::docsUrl('/data-api/about-api/study-data-structure#LocationGeoPoint') . '">LocationGeoPoint</a> of a study. Ignored, if <code>filter.geo</code> parameter is not set or response contains more than 10,000 studies.',
        'default' => 'func:exp,scale:300mi,offset:0mi,decay:0.5',
        'pattern' => '^func:(gauss|exp|linear),scale:(\d+(\.\d+)?(km|mi)),offset:(\d+(\.\d+)?(km|mi)),decay:(\d+(\.\d+)?)$',
        'examples' => [
          ['value' => 'func:linear,scale:100km,offset:10km,decay:0.1', 'label' => 'func:linear,scale:100km,offset:10km,decay:0.1'],
          ['value' => 'func:gauss,scale:500mi,offset:0mi,decay:0.3', 'label' => 'func:gauss,scale:500mi,offset:0mi,decay:0.3'],
        ],
      ],
      [
        'key' => 'fields',
        'label' => 'Fields (fields)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'If specified, must be non-empty comma- or pipe-separated list of fields to return. If unspecified, all fields will be returned. Order of the fields does not matter.<br><br>For <code>json</code> format, every list item is either area name, piece name, field name, or special name. If a piece or a field is a branch node, all descendant fields will be included. All area names are available on <a href="' . static::docsUrl('/data-api/about-api/search-areas') . '">Search Areas</a>, the piece and field names on <a href="' . static::docsUrl('/data-api/about-api/study-data-structure') . '">Data Structure</a> and also can be retrieved at <code>/studies/metadata</code> endpoint. There is a special name, <code>@query</code>, which expands to all fields queried by search.',
        'min_items' => 1,
        'examples' => [
          ['values' => ['NCTId', 'BriefTitle', 'OverallStatus', 'HasResults'], 'label' => 'NCTId, BriefTitle, OverallStatus, HasResults'],
          ['values' => ['ProtocolSection'], 'label' => 'ProtocolSection'],
        ],
      ],
      [
        'key' => 'sort',
        'label' => 'Sort (sort)',
        'data_type' => 'array of string',
        'type' => 'textarea',
        'multivalue' => TRUE,
        'description' => 'Comma- or pipe-separated list of sorting options of the studies. The returning studies are not sorted by default for a performance reason. Every list item contains a field/piece name and an optional sort direction (<code>asc</code> for ascending or <code>desc</code> for descending) after colon character.<br><br>All piece and field names can be found on <a href="' . static::docsUrl('/data-api/about-api/study-data-structure') . '">Data Structure</a> and also can be retrieved at <code>/studies/metadata</code> endpoint. Currently, only date and numeric fields are allowed for sorting. There is a special "field" <code>@relevance</code> to sort by relevance to a search query.<br><br>Studies missing sort field are always last. Default sort direction: Date field <code>desc</code>, numeric field <code>asc</code>, and <code>@relevance</code> <code>desc</code>.',
        'max_items' => 2,
        'examples' => [
          ['values' => ['@relevance'], 'label' => '@relevance'],
          ['values' => ['LastUpdatePostDate'], 'label' => 'LastUpdatePostDate'],
          ['values' => ['EnrollmentCount:desc', 'NumArmGroups'], 'label' => 'EnrollmentCount:desc, NumArmGroups'],
        ],
      ],
      [
        'key' => 'countTotal',
        'label' => 'Count total (countTotal)',
        'data_type' => 'boolean',
        'type' => 'select',
        'description' => 'Count total number of studies in all pages and return <code>totalCount</code> field with first page, if <code>true</code>. The parameter is ignored for the subsequent pages.',
        'default' => 'false',
        'options' => [
          '' => '- Default -',
          'true' => 'Yes',
          'false' => 'No',
        ],
      ],
      [
        'key' => 'pageSize',
        'label' => 'Page size (pageSize)',
        'data_type' => 'int32',
        'type' => 'number',
        'description' => 'Page size is maximum number of studies to return in response. It does not have to be the same for every page. If not specified or set to 0, the default value will be used. It will be coerced down to 1,000, if greater than that.',
        'default' => '10',
        'min' => 0,
        'max' => 1000,
        'examples' => [
          ['value' => '2', 'label' => '2'],
          ['value' => '100', 'label' => '100'],
        ],
      ],
      [
        'key' => 'pageToken',
        'label' => 'Page token (pageToken)',
        'data_type' => 'string',
        'type' => 'textfield',
        'description' => 'Token to get next page. Set it to a <code>nextPageToken</code> value returned with the previous page in JSON format.',
      ],
    ];
  }

  /**
   * Builds a field element from a definition.
   */
  protected static function buildFieldElement(array $definition, string $default_value): array {
    if (($definition['key'] === 'countTotal') && ($default_value === '')) {
      $default_value = 'true';
    }

    $element = [
      '#title' => t('@label', [
        '@label' => static::displayLabel($definition),
      ]),
      '#default_value' => $default_value,
      '#description' => static::buildDescription($definition),
      '#field_prefix' => '<div class="clinical-trials-gov-studies-query__field-meta"><span class="clinical-trials-gov-studies-query__field-name">' . htmlspecialchars($definition['key']) . '</span> <span class="clinical-trials-gov-studies-query__field-type">(' . htmlspecialchars($definition['data_type']) . ')</span></div>',
    ];

    switch ($definition['type']) {
      case 'number':
        $element['#type'] = 'number';
        if (isset($definition['min'])) {
          $element['#min'] = $definition['min'];
        }
        if (isset($definition['max'])) {
          $element['#max'] = $definition['max'];
        }
        break;

      case 'select':
        $element['#type'] = 'select';
        $element['#options'] = array_map(
          static fn(string $label): string => (string) t('@label', ['@label' => $label]),
          $definition['options']
        );
        break;

      case 'textarea':
        $element['#type'] = 'textarea';
        $element['#rows'] = 2;
        if (!empty($definition['multivalue'])) {
          $element['#attributes']['data-clinical-trials-gov-multi-value'] = 'true';
          $element['#attributes']['data-clinical-trials-gov-separator'] = 'pipe';
        }
        break;

      case 'textfield':
      default:
        $element['#type'] = 'textfield';
        break;
    }

    return $element;
  }

  /**
   * Builds the description markup for a field.
   */
  protected static function buildDescription(array $definition): array {
    $parts = [];
    $parts[] = '<div class="clinical-trials-gov-studies-query__description">' . $definition['description'] . '</div>';

    $metadata_lines = [];
    if (isset($definition['default'])) {
      $metadata_lines[] = '<strong>Default:</strong> <code>' . htmlspecialchars($definition['default']) . '</code>';
    }
    if (isset($definition['pattern'])) {
      $metadata_lines[] = '<strong>Pattern:</strong> <code>' . htmlspecialchars($definition['pattern']) . '</code>';
    }
    if (isset($definition['min_items'])) {
      $metadata_lines[] = '<strong>Minimum:</strong> ' . (string) $definition['min_items'] . ' item';
    }
    if (isset($definition['max_items'])) {
      $metadata_lines[] = '<strong>Maximum:</strong> ' . (string) $definition['max_items'] . ' items';
    }
    if (!empty($metadata_lines)) {
      $parts[] = '<div class="clinical-trials-gov-studies-query__meta">' . implode('<br>', $metadata_lines) . '</div>';
    }

    if (!empty($definition['allowed_values'])) {
      $allowed = [];
      foreach ($definition['allowed_values'] as $allowed_value) {
        $value = static::exampleValue($allowed_value);
        $allowed[] = '<a href="#" class="clinical-trials-gov-studies-query__fill-value" data-clinical-trials-gov-value="' . htmlspecialchars($value, ENT_QUOTES) . '">' . htmlspecialchars($allowed_value['label']) . '</a>';
      }
      $parts[] = '<div class="clinical-trials-gov-studies-query__allowed"><strong>Allowed:</strong> ' . implode(' | ', $allowed) . '</div>';
    }

    if (!empty($definition['examples'])) {
      $examples = [];
      foreach ($definition['examples'] as $example) {
        $value = static::exampleValue($example);
        $examples[] = '<a href="#" class="clinical-trials-gov-studies-query__fill-value" data-clinical-trials-gov-value="' . htmlspecialchars($value, ENT_QUOTES) . '">' . htmlspecialchars($example['label']) . '</a>';
      }
      $parts[] = '<div class="clinical-trials-gov-studies-query__examples"><strong>Examples:</strong> ' . implode(' | ', $examples) . '</div>';
    }

    return [
      '#type' => 'inline_template',
      '#template' => '{{ description|raw }}',
      '#context' => [
        'description' => implode('', $parts),
      ],
    ];
  }

  /**
   * Normalizes a submitted field value for serialization.
   */
  protected static function normalizeSubmittedValue(string $api_key, mixed $value): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
      return '';
    }

    if (in_array($api_key, static::MULTI_VALUE_KEYS, TRUE)) {
      $normalized = preg_replace('/[\r\n]+/', '|', $normalized) ?? $normalized;
      $normalized = str_replace(',', '|', $normalized);
      $parts = array_values(array_filter(array_map('trim', explode('|', $normalized))));
      return implode('|', $parts);
    }

    return $normalized;
  }

  /**
   * Returns a full ClinicalTrials.gov documentation URL.
   */
  protected static function docsUrl(string $path): string {
    return static::DOCS_BASE_URL . $path;
  }

  /**
   * Returns the serialized example value.
   */
  protected static function exampleValue(array $example): string {
    if (isset($example['value'])) {
      if (is_array($example['value'])) {
        return implode('|', $example['value']);
      }
      return $example['value'];
    }
    return implode('|', $example['values']);
  }

  /**
   * Returns the human-facing label without the raw parameter name.
   */
  protected static function displayLabel(array $definition): string {
    $suffix = ' (' . $definition['key'] . ')';
    if (str_ends_with($definition['label'], $suffix)) {
      return substr($definition['label'], 0, -strlen($suffix));
    }
    return $definition['label'];
  }

}
