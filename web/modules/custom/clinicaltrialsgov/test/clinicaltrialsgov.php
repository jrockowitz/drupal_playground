<?php

// =============================================================================
// CONSTANTS
// =============================================================================

define('API_BASE', 'https://clinicaltrials.gov/api/v2');
define('SELF', basename($_SERVER['PHP_SELF']));

// =============================================================================
// DATA
// =============================================================================

$endpoints = [
  ['path' => '/studies',              'description' => 'Search / list studies',                            'section' => 'Studies'],
  ['path' => '/studies/{nctId}',      'description' => 'Single study record (replace {nctId} in the URL)', 'section' => 'Studies'],
  ['path' => '/studies/metadata',     'description' => 'Study data model / field tree',                    'section' => 'Studies'],
  ['path' => '/studies/search-areas', 'description' => 'Full-text search areas and their syntax',          'section' => 'Studies'],
  ['path' => '/studies/enums',        'description' => 'All enumeration types and their allowed values',   'section' => 'Studies'],
  ['path' => '/stats/size',           'description' => 'Total number of studies in the database',          'section' => 'Data statistics'],
  ['path' => '/stats/field/values',   'description' => 'Value stats for a specific field',                 'section' => 'Data statistics'],
  ['path' => '/stats/field/sizes',    'description' => 'Size stats for a specific field',                  'section' => 'Data statistics'],
  ['path' => '/version',              'description' => 'API version and data timestamp',                   'section' => 'Version'],
];

$studies_params = [
  ['query.cond',           'string',  'Condition or disease'],
  ['query.term',           'string',  'Other search terms (full-text)'],
  ['query.locn',           'string',  'Location terms'],
  ['query.titles',         'string',  'Title / acronym search'],
  ['query.intr',           'string',  'Intervention or treatment'],
  ['query.outc',           'string',  'Outcome measure'],
  ['query.spons',          'string',  'Sponsor or collaborator'],
  ['query.lead',           'string',  'Lead sponsor only'],
  ['query.id',             'string',  'NCT number or study ID'],
  ['filter.overallStatus', 'string',  'Pipe-separated statuses e.g. RECRUITING|COMPLETED'],
  ['filter.geo',           'string',  'Geo filter e.g. distance(lat,lng,50mi)'],
  ['filter.ids',           'string',  'Pipe-separated NCT IDs e.g. NCT001|NCT002'],
  ['filter.advanced',      'string',  'Essie expression syntax filter'],
  ['aggFilters',           'string',  'Aggregation filters e.g. phase:phase2,studyType:int'],
  ['pageSize',             'integer', 'Results per page (1–1000, default 10)'],
  ['pageToken',            'string',  'Pagination cursor token from previous response'],
  ['countTotal',           'boolean', 'Include total matching count in response'],
  ['sort',                 'string',  'Sort field and direction e.g. LastUpdatePostDate:desc'],
];

$status_colours = [
  'RECRUITING'            => 'success',
  'NOT_YET_RECRUITING'    => 'warning text-dark',
  'ACTIVE_NOT_RECRUITING' => 'info text-dark',
  'COMPLETED'             => 'secondary',
  'TERMINATED'            => 'danger',
  'WITHDRAWN'             => 'danger',
  'SUSPENDED'             => 'warning text-dark',
];


// =============================================================================
// FUNCTIONS
// =============================================================================

/**
 * Fetches a ClinicalTrials.gov API endpoint and returns the decoded response.
 *
 * Returns ['data' => mixed, 'url' => string] on success,
 * or ['error' => string, 'url' => string] on failure.
 */
function fetch_api(string $path, array $params = []): array {
  $query_string = build_query_string($params);
  $url = API_BASE . $path . ($query_string ? '?' . $query_string : '');

  if (function_exists('curl_init')) {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT        => 10,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error) {
      return ['error' => 'cURL error: ' . $curl_error, 'url' => $url];
    }
    if ($http_code >= 400) {
      return ['error' => 'HTTP ' . $http_code . ': ' . $response, 'url' => $url];
    }
  }
  else {
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, FALSE, $context);
    if ($response === FALSE) {
      return ['error' => 'Failed to fetch: ' . $url, 'url' => $url];
    }
    if (isset($http_response_header)) {
      foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d+\.?\d*\s+(\d+)/', $header, $matches) && (int) $matches[1] >= 400) {
          return ['error' => 'HTTP ' . $matches[1] . ': ' . $response, 'url' => $url];
        }
      }
    }
  }

  $decoded = json_decode($response, TRUE);
  if ($decoded === NULL && $response !== 'null') {
    return ['error' => 'Invalid JSON response: ' . substr($response, 0, 500), 'url' => $url];
  }

  return ['data' => $decoded, 'url' => $url];
}

/**
 * Builds a URL query string from an array, omitting empty values.
 */
function build_query_string(array $params): string {
  $parts = [];
  foreach ($params as $key => $value) {
    if ($value !== '' && $value !== NULL) {
      $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
  }
  return implode('&', $parts);
}

/**
 * Parses a query string while preserving dot-notation keys (e.g. query.cond).
 *
 * $_GET and parse_str() convert dots to underscores, so we parse
 * QUERY_STRING manually to keep keys like query.cond intact.
 */
function parse_query_string(string $query_string): array {
  $result = [];
  if ($query_string === '') {
    return $result;
  }
  foreach (explode('&', $query_string) as $pair) {
    $parts = explode('=', $pair, 2);
    $key = urldecode($parts[0]);
    $value = isset($parts[1]) ? urldecode($parts[1]) : '';
    if ($key !== '') {
      $result[$key] = $value;
    }
  }
  return $result;
}

/**
 * Recursively flattens nested study data into a key_path => raw_value map.
 *
 * Assoc arrays (objects) are recursed into using dot-notation keys.
 * Lists and scalars are stored as-is for rendering by render_study_data_value().
 */
function flatten_study(mixed $data, string $prefix = ''): array {
  if (is_array($data) && !array_is_list($data)) {
    $result = [];
    foreach ($data as $key => $value) {
      $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
      $result += flatten_study($value, $child_key);
    }
    return $result;
  }

  return [$prefix => $data];
}

require __DIR__ . '/clinicaltrialsgov.inc';

// =============================================================================
// ROUTER
// =============================================================================

$all_params = parse_query_string($_SERVER['QUERY_STRING'] ?? '');
$path = $all_params['path'] ?? '';
unset($all_params['path']);
$params = $all_params;

// Handle single-study paths early so the page title can come from the study data.
if (preg_match('#^/studies/NCT\d+$#i', $path)) {
  $view = (($params['view'] ?? 'summary') === 'data') ? 'data' : 'summary';
  $api_params = $params;
  unset($api_params['view']);
  $result = fetch_api($path, $api_params);
  $page_title = ($view === 'summary' && isset($result['data']))
    ? ($result['data']['protocolSection']['identificationModule']['briefTitle'] ?? $path)
    : $path;
  render_page_start($page_title);
  if (isset($result['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>' . "\n";
  }
  else {
    render_study_toggle($path, $view);
    if ($view === 'summary') {
      render_study_summary($result['data']);
    }
    else {
      render_study_data($result['data']);
    }
  }
  render_raw_json($result['data'] ?? NULL, $result['url'] ?? '');
  render_page_end();
  exit;
}

render_page_start($path ?: 'Endpoints');

if ($path === '') {
  render_endpoint_index($endpoints);
}
elseif ($path === '/studies') {
  render_studies_form($studies_params, $params, open: empty($params));
  $api_params = $params + ['countTotal' => 'true'];
  $result = fetch_api('/studies', $api_params);
  if (isset($result['error'])) {
    echo '<div class="alert alert-danger mt-3">' . htmlspecialchars($result['error']) . '</div>' . "\n";
  }
  else {
    render_studies_results($result['data'], $params);
  }
}
else {
  $result = fetch_api($path, $params);
  if (isset($result['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>' . "\n";
  }
  else if (in_array($path, ['/stats/field/values', '/stats/field/sizes'])) {
    render_field_stats($result['data'], $path);
  }
  else {
    render_generic($result['data']);
  }
}

if (isset($result) && isset($result['data']) && isset($result['url'])) {
  render_raw_json($result['data'], $result['url']);
}

render_page_end();
