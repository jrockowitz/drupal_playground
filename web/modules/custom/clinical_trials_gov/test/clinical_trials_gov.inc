<?php

/**
 * @file
 * Rendering functions only.
 */

// Requires SELF, build_query_string(), flatten_study(), and metadata_anchor_id()
// from clinical_trials_gov.php, which includes this file.
if (!defined('CURRENT_SCRIPT')) {
  define('CURRENT_SCRIPT', basename((string) ($_SERVER['PHP_SELF'] ?? '')));
}

define('_CLINICALTRIALS_STATUS_COLOURS', [
  'RECRUITING' => 'success',
  'NOT_YET_RECRUITING' => 'warning text-dark',
  'ACTIVE_NOT_RECRUITING' => 'info text-dark',
  'COMPLETED' => 'secondary',
  'TERMINATED' => 'danger',
  'WITHDRAWN' => 'danger',
  'SUSPENDED' => 'warning text-dark',
]);

/**
 * Outputs the HTML head, navbar, and opening container with an <h1> page title.
 */
function render_page_start(string $title): void {
  echo '<!DOCTYPE html>' . "\n";
  echo '<html lang="en">' . "\n";
  echo '<head>' . "\n";
  echo '<meta charset="UTF-8">' . "\n";
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
  echo '<title>' . htmlspecialchars($title) . ' - ClinicalTrials.gov Explorer</title>' . "\n";
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">' . "\n";
  echo '<style>' . "\n";
  echo 'pre { font-size: .85rem; }' . "\n";
  echo 'details > summary { cursor: pointer; user-select: none; color: #6c757d; }' . "\n";
  echo 'details > summary:hover { color: #495057; }' . "\n";
  echo 'details.param-box { border: 1px solid #dee2e6; border-radius: .375rem; padding: 1rem; }' . "\n";
  echo 'details.param-box > summary { color: #212529; font-size: 1.25rem; font-weight: 500; margin-bottom: 0; }' . "\n";
  echo 'dt { font-weight: 600; }' . "\n";
  echo 'table thead { --bs-table-bg: #e9ecef; --bs-table-color: #212529; }' . "\n";
  echo '.study-meta-card { border: 1px solid #dee2e6; border-radius: .375rem; padding: .75rem 1rem; }' . "\n";
  echo '.study-meta-card .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }' . "\n";
  echo '.study-meta-card .value { font-weight: 600; font-size: .95rem; }' . "\n";
  echo '.breadcrumb-key { font-size: .8rem; white-space: nowrap; }' . "\n";
  echo '.breadcrumb-key a { font-family: monospace; color: #6c757d; text-decoration: none; }' . "\n";
  echo '.breadcrumb-key a:hover { color: #495057; text-decoration: underline; }' . "\n";
  echo '.metadata-row-struct { font-weight: 700; }' . "\n";
  echo 'tr:target { outline: 2px solid #0d6efd; outline-offset: -2px; background-color: #e7f1ff; }' . "\n";
  echo '</style>' . "\n";
  echo '</head>' . "\n";
  echo '<body>' . "\n";
  echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">' . "\n";
  echo '<div class="container">' . "\n";
  echo '<a class="navbar-brand" href="' . htmlspecialchars(CURRENT_SCRIPT) . '">ClinicalTrials.gov Explorer</a>' . "\n";
  echo '<a class="ms-auto text-white-50 small text-decoration-none" href="https://clinicaltrials.gov/data-api/api" target="_blank">ClinicalTrials.gov API</a>' . "\n";
  echo '</div>' . "\n";
  echo '</nav>' . "\n";
  echo '<div class="container py-4">' . "\n";
  echo '<h1>' . htmlspecialchars($title) . '</h1>' . "\n";
}

/**
 * Outputs the Bootstrap JS bundle and closing HTML tags.
 */
function render_page_end(): void {
  echo '</div>' . "\n";
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFEfVZc+/6Cv3w2a1EtIbFDLbY9" crossorigin="anonymous"></script>' . "\n";
  echo '</body>' . "\n";
  echo '</html>' . "\n";
}

/**
 * Renders a Bootstrap alert for API and request errors.
 */
function render_error_message(string $message, string $url = '', string $classes = ''): void {
  $class_suffix = ($classes) ? ' ' . $classes : '';
  echo '<div class="alert alert-danger' . htmlspecialchars($class_suffix) . '">' . htmlspecialchars($message) . '</div>' . "\n";
  if ($url) {
    echo '<p class="small text-muted mb-3">API URL: <a href="' . htmlspecialchars($url) . '" target="_blank" class="font-monospace">' . htmlspecialchars($url) . '</a></p>' . "\n";
  }
}

/**
 * Returns a Bootstrap badge HTML string for a study overall status value.
 */
function build_status_badge(string $status): string {
  $colour = _CLINICALTRIALS_STATUS_COLOURS[$status] ?? 'light text-dark';
  return '<span class="badge bg-' . $colour . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Converts a ClinicalTrials.gov phase enum to a readable label.
 */
function format_phase_label(string $phase): string {
  return match ($phase) {
    'PHASE1' => 'Phase 1',
    'PHASE2' => 'Phase 2',
    'PHASE3' => 'Phase 3',
    'PHASE4' => 'Phase 4',
    'EARLY_PHASE1' => 'Early Phase 1',
    'NA' => 'N/A',
    default => ucwords(strtolower(str_replace('_', ' ', $phase))),
  };
}

/**
 * Renders the endpoint index grouped by section as linked list groups.
 */
function render_endpoint_index(array $endpoints): void {
  $sections = [];
  foreach ($endpoints as $endpoint) {
    $sections[$endpoint['section']][] = $endpoint;
  }

  foreach ($sections as $section_name => $section_endpoints) {
    echo '<h2 class="mt-4 mb-2">' . htmlspecialchars($section_name) . '</h2>' . "\n";
    echo '<ul class="list-group mb-4">' . "\n";
    foreach ($section_endpoints as $endpoint) {
      $path = $endpoint['path'];
      $is_placeholder = str_contains($path, '{');
      $href = $is_placeholder ? NULL : htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode($path));
      echo '<li class="list-group-item d-flex justify-content-between align-items-center">' . "\n";
      echo '<div>' . "\n";
      echo '<span class="font-monospace fw-semibold">' . htmlspecialchars($path) . '</span>' . "\n";
      echo '<span class="text-muted ms-3">' . htmlspecialchars($endpoint['description']) . '</span>' . "\n";
      echo '</div>' . "\n";
      if ($href) {
        echo '<a href="' . $href . '" class="btn btn-sm btn-outline-primary ms-2">Open</a>' . "\n";
      }
      echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
  }
}

/**
 * Renders the /studies search form inside a collapsible <details> widget.
 */
function render_studies_form(array $studies_params, array $current_values, bool $open = TRUE): void {
  echo '<details class="param-box mt-4"' . ($open ? ' open' : '') . '>' . "\n";
  echo '<summary>Search Parameters</summary>' . "\n";
  echo '<form action="' . htmlspecialchars(CURRENT_SCRIPT) . '" method="get" class="mt-3">' . "\n";
  echo '<input type="hidden" name="path" value="/studies">' . "\n";
  foreach ($studies_params as [$key, $type, $description]) {
    $current_value = $current_values[$key] ?? '';
    $input_id = 'param_' . str_replace('.', '_', $key);
    echo '<div class="mb-3">' . "\n";
    echo '<label class="form-label" for="' . htmlspecialchars($input_id) . '">' . htmlspecialchars($description) . ' <small class="text-muted font-monospace">(' . htmlspecialchars($key) . ')</small></label>' . "\n";
    if ($type === 'boolean') {
      echo '<select class="form-select" id="' . htmlspecialchars($input_id) . '" name="' . htmlspecialchars($key) . '">' . "\n";
      echo '<option value=""></option>' . "\n";
      echo '<option value="true"' . ($current_value === 'true' ? ' selected' : '') . '>true</option>' . "\n";
      echo '<option value="false"' . ($current_value === 'false' ? ' selected' : '') . '>false</option>' . "\n";
      echo '</select>' . "\n";
    }
    elseif ($type === 'integer') {
      echo '<input type="number" class="form-control" id="' . htmlspecialchars($input_id) . '" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($current_value) . '">' . "\n";
    }
    else {
      echo '<input type="text" class="form-control" id="' . htmlspecialchars($input_id) . '" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($current_value) . '">' . "\n";
    }
    echo '</div>' . "\n";
  }
  echo '<button type="submit" class="btn btn-primary">Search</button>' . "\n";
  echo '</form>' . "\n";
  echo '</details>' . "\n";
}

/**
 * Renders the minimal field form required by the stats endpoints.
 */
function render_field_stats_form(string $path, string $current_value = ''): void {
  echo '<form action="' . htmlspecialchars(CURRENT_SCRIPT) . '" method="get" class="row gy-2 gx-3 align-items-end mt-4 mb-4">' . "\n";
  echo '<input type="hidden" name="path" value="' . htmlspecialchars($path) . '">' . "\n";
  echo '<div class="col-sm-9">' . "\n";
  echo '<label class="form-label" for="fields">Fields</label>' . "\n";
  echo '<input type="text" class="form-control" id="fields" name="fields" value="' . htmlspecialchars($current_value) . '">' . "\n";
  echo '</div>' . "\n";
  echo '<div class="col-sm-3">' . "\n";
  echo '<button type="submit" class="btn btn-primary w-100">Fetch</button>' . "\n";
  echo '</div>' . "\n";
  echo '</form>' . "\n";
}

/**
 * Renders the /studies results table with a count summary and next-page link.
 */
function render_studies_results(array $data, array $current_params): void {
  $studies = $data['studies'] ?? [];
  $next_page_token = $data['nextPageToken'] ?? NULL;
  $total_count = $data['totalCount'] ?? NULL;
  $count = count($studies);

  if (empty($studies)) {
    echo '<div class="alert alert-info">No studies found.</div>' . "\n";
    return;
  }

  echo '<h2 class="mt-4 mb-3">Results</h2>' . "\n";

  $summary = 'Showing ' . $count . ' ' . (($count === 1) ? 'study' : 'studies');
  if ($total_count !== NULL) {
    $summary .= ' of ' . number_format($total_count) . ' total';
  }
  echo '<p class="text-muted small">' . htmlspecialchars($summary) . '</p>' . "\n";

  echo '<table class="table table-striped table-hover table-sm align-middle">' . "\n";
  echo '<thead>' . "\n";
  echo '<tr>' . "\n";
  echo '<th style="width:10%">NCT ID</th>' . "\n";
  echo '<th style="width:35%">Title</th>' . "\n";
  echo '<th style="width:13%">Status</th>' . "\n";
  echo '<th style="width:10%">Phase</th>' . "\n";
  echo '<th style="width:32%">Conditions</th>' . "\n";
  echo '</tr>' . "\n";
  echo '</thead>' . "\n";
  echo '<tbody>' . "\n";
  foreach ($studies as $study) {
    $protocol = $study['protocolSection'] ?? [];
    $identification = $protocol['identificationModule'] ?? [];
    $status_module = $protocol['statusModule'] ?? [];
    $conditions_module = $protocol['conditionsModule'] ?? [];
    $design_module = $protocol['designModule'] ?? [];

    $nct_id = $identification['nctId'] ?? '';
    $title = $identification['briefTitle'] ?? '';
    $status = $status_module['overallStatus'] ?? '';
    $phases = [];
    foreach (($design_module['phases'] ?? []) as $phase) {
      $phases[] = format_phase_label($phase);
    }
    $conditions = implode(', ', $conditions_module['conditions'] ?? []);

    echo '<tr>' . "\n";
    echo '<td><a href="' . htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode('/studies/' . $nct_id)) . '" class="font-monospace">' . htmlspecialchars($nct_id) . '</a></td>' . "\n";
    echo '<td>' . htmlspecialchars($title) . '</td>' . "\n";
    echo '<td>' . build_status_badge($status) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars(implode(', ', $phases)) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($conditions) . '</td>' . "\n";
    echo '</tr>' . "\n";
  }
  echo '</tbody>' . "\n";
  echo '</table>' . "\n";

  if ($next_page_token) {
    $next_params = $current_params;
    $next_params['pageToken'] = $next_page_token;
    $href = htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode('/studies') . '&' . build_query_string($next_params));
    echo '<a href="' . $href . '" class="btn btn-outline-secondary btn-sm">Next page &rarr;</a>' . "\n";
  }
}

/**
 * Renders /studies/metadata as a flattened table keyed by dotted name paths.
 */
function render_metadata(array $rows): void {
  if (empty($rows)) {
    echo '<div class="alert alert-info">No metadata fields found.</div>' . "\n";
    return;
  }

  echo '<table class="table table-sm table-bordered table-hover">' . "\n";
  echo '<thead><tr><th style="width:24%">Key</th><th style="width:12%">Piece</th><th style="width:18%">Title</th><th style="width:12%">Type</th><th style="width:10%">Source Type</th><th style="width:14%">Children</th><th>Description</th></tr></thead>' . "\n";
  echo '<tbody>' . "\n";
  foreach ($rows as $row) {
    $anchor_id = metadata_anchor_id($row['key']);
    $row_class = ($row['sourceType'] === 'STRUCT') ? ' class="metadata-row-struct"' : '';
    echo '<tr id="' . htmlspecialchars($anchor_id) . '"' . $row_class . '>' . "\n";
    echo '<td class="breadcrumb-key">' . htmlspecialchars($row['key']) . '</td>' . "\n";
    echo '<td class="font-monospace">' . htmlspecialchars($row['piece']) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($row['title']) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($row['type']) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($row['sourceType']) . '</td>' . "\n";
    echo '<td>' . "\n";
    if (!empty($row['children'])) {
      echo '<ul class="mb-0 ps-3">';
      foreach ($row['children'] as $child_key) {
        echo '<li class="breadcrumb-key">' . htmlspecialchars($child_key) . '</li>';
      }
      echo '</ul>' . "\n";
    }
    echo '</td>' . "\n";
    echo '<td>' . htmlspecialchars($row['description']) . '</td>' . "\n";
    echo '</tr>' . "\n";
  }
  echo '</tbody>' . "\n";
  echo '</table>' . "\n";
}

/**
 * Renders /stats/field/values or /stats/field/sizes data as a table.
 *
 * The 'piece' column is linked back to the same path with that field selected.
 */
function render_field_stats(array $data, string $path): void {
  if (empty($data) || !is_array($data[0])) {
    render_generic($data);
    return;
  }

  $keys = array_keys($data[0]);
  echo '<table class="table table-sm table-bordered">' . "\n";
  echo '<thead><tr>' . "\n";
  foreach ($keys as $key) {
    echo '<th>' . htmlspecialchars($key) . '</th>' . "\n";
  }
  echo '</tr></thead>' . "\n";
  echo '<tbody>' . "\n";
  foreach ($data as $row) {
    echo '<tr>' . "\n";
    foreach ($keys as $key) {
      $cell_value = $row[$key] ?? '';
      echo '<td>';
      if ($key === 'piece' && is_string($cell_value) && $cell_value !== '') {
        $href = htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode($path) . '&fields=' . rawurlencode($cell_value));
        echo '<a href="' . $href . '" class="font-monospace">' . htmlspecialchars($cell_value) . '</a>';
      }
      elseif (is_array($cell_value)) {
        render_generic($cell_value);
      }
      else {
        echo htmlspecialchars((string) $cell_value);
      }
      echo '</td>' . "\n";
    }
    echo '</tr>' . "\n";
  }
  echo '</tbody>' . "\n";
  echo '</table>' . "\n";
}

/**
 * Renders a Summary / Data toggle button group for single-study pages.
 */
function render_study_toggle(string $path, string $current_view): void {
  $summary_href = htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode($path) . '&view=summary');
  $data_href = htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode($path) . '&view=data');
  echo '<div class="btn-group mb-4" role="group" aria-label="Display mode">' . "\n";
  echo '<a href="' . $summary_href . '" class="btn btn-sm ' . (($current_view === 'summary') ? 'btn-primary' : 'btn-outline-primary') . '">Summary</a>' . "\n";
  echo '<a href="' . $data_href . '" class="btn btn-sm ' . (($current_view === 'data') ? 'btn-primary' : 'btn-outline-primary') . '">Data</a>' . "\n";
  echo '</div>' . "\n";
}

/**
 * Renders a human-readable summary of a single study.
 */
function render_study_summary(array $data): void {
  $protocol = $data['protocolSection'] ?? [];
  $identification = $protocol['identificationModule'] ?? [];
  $status_module = $protocol['statusModule'] ?? [];
  $description_module = $protocol['descriptionModule'] ?? [];
  $conditions_module = $protocol['conditionsModule'] ?? [];
  $design_module = $protocol['designModule'] ?? [];
  $eligibility_module = $protocol['eligibilityModule'] ?? [];
  $contacts_module = $protocol['contactsLocationsModule'] ?? [];
  $sponsors_module = $protocol['sponsorCollaboratorsModule'] ?? [];
  $outcomes_module = $protocol['outcomesModule'] ?? [];
  $arms_module = $protocol['armsInterventionsModule'] ?? [];

  $nct_id = $identification['nctId'] ?? '';
  $status = $status_module['overallStatus'] ?? '';
  $phases = $design_module['phases'] ?? [];
  $study_type = $design_module['studyType'] ?? '';
  $lead_sponsor = $sponsors_module['leadSponsor']['name'] ?? '';

  echo '<div class="mb-2">' . "\n";
  if ($status) {
    echo build_status_badge($status) . ' ';
  }
  foreach ($phases as $phase) {
    echo '<span class="badge bg-light text-dark border me-1">' . htmlspecialchars(format_phase_label($phase)) . '</span>';
  }
  if ($study_type) {
    echo '<span class="badge bg-light text-dark border">' . htmlspecialchars($study_type) . '</span>';
  }
  echo '</div>' . "\n";

  $subtitle_parts = array_filter([$nct_id, $lead_sponsor]);
  echo '<p class="text-muted mb-4">' . htmlspecialchars(implode(' · ', $subtitle_parts)) . '</p>' . "\n";

  $enrollment_count = $design_module['enrollmentInfo']['count'] ?? NULL;
  $enrollment_type = $design_module['enrollmentInfo']['type'] ?? '';
  $start_date = $status_module['startDateStruct']['date'] ?? '';
  $completion_date = $status_module['completionDateStruct']['date'] ?? '';
  $sex = $eligibility_module['sex'] ?? '';
  $min_age = $eligibility_module['minimumAge'] ?? '';
  $max_age = $eligibility_module['maximumAge'] ?? '';
  $healthy_raw = $eligibility_module['healthyVolunteers'] ?? NULL;
  $healthy_volunteers = (!$healthy_raw) ? NULL : ($healthy_raw ? 'Yes' : 'No');

  $cards = [];
  if ($lead_sponsor) {
    $cards[] = ['Sponsor', $lead_sponsor];
  }
  if ($study_type) {
    $cards[] = ['Study Type', $study_type];
  }
  if ($start_date) {
    $cards[] = ['Start Date', $start_date];
  }
  if ($completion_date) {
    $cards[] = ['Completion Date', $completion_date];
  }
  if ($enrollment_count) {
    $cards[] = ['Enrollment', $enrollment_count . ($enrollment_type ? ' (' . $enrollment_type . ')' : '')];
  }
  if ($sex) {
    $cards[] = ['Sex', $sex];
  }
  if ($min_age || $max_age) {
    $cards[] = ['Age Range', ($min_age ?: 'N/A') . ' - ' . ($max_age ?: 'N/A')];
  }
  if ($healthy_volunteers) {
    $cards[] = ['Healthy Volunteers', $healthy_volunteers];
  }

  if (!empty($cards)) {
    echo '<div class="row g-3 mb-4">' . "\n";
    foreach ($cards as [$label, $value]) {
      echo '<div class="col-sm-6 col-md-4 col-lg-3">' . "\n";
      echo '<div class="study-meta-card h-100">' . "\n";
      echo '<div class="label">' . htmlspecialchars($label) . '</div>' . "\n";
      echo '<div class="value">' . htmlspecialchars((string) $value) . '</div>' . "\n";
      echo '</div>' . "\n";
      echo '</div>' . "\n";
    }
    echo '</div>' . "\n";
  }

  $conditions = $conditions_module['conditions'] ?? [];
  if (!empty($conditions)) {
    echo '<h2 class="h5 mt-4">Conditions</h2>' . "\n";
    echo '<p>';
    foreach ($conditions as $condition) {
      echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($condition) . '</span>';
    }
    echo '</p>' . "\n";
  }

  $brief_summary = $description_module['briefSummary'] ?? '';
  if ($brief_summary) {
    echo '<h2 class="h5 mt-4">Summary</h2>' . "\n";
    $clean_summary = strip_tags(str_replace(['<br/>', '<br />', '<br>'], "\n", $brief_summary));
    echo '<p>' . nl2br(htmlspecialchars(trim($clean_summary))) . '</p>' . "\n";
  }

  $interventions = $arms_module['interventions'] ?? [];
  if (!empty($interventions)) {
    echo '<h2 class="h5 mt-4">Interventions</h2>' . "\n";
    echo '<ul>' . "\n";
    foreach ($interventions as $intervention) {
      $name = $intervention['name'] ?? '';
      $type = $intervention['type'] ?? '';
      $description = $intervention['description'] ?? '';
      echo '<li>';
      echo '<strong>' . htmlspecialchars($name) . '</strong>';
      if ($type) {
        echo ' <span class="text-muted">(' . htmlspecialchars($type) . ')</span>';
      }
      if ($description) {
        echo ' - ' . htmlspecialchars($description);
      }
      echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
  }

  $primary_outcomes = $outcomes_module['primaryOutcomes'] ?? [];
  if (!empty($primary_outcomes)) {
    echo '<h2 class="h5 mt-4">Primary Outcomes</h2>' . "\n";
    echo '<ol>' . "\n";
    foreach ($primary_outcomes as $outcome) {
      $measure = $outcome['measure'] ?? '';
      $time_frame = $outcome['timeFrame'] ?? '';
      echo '<li>';
      echo htmlspecialchars($measure);
      if ($time_frame) {
        echo ' <span class="text-muted small">(' . htmlspecialchars($time_frame) . ')</span>';
      }
      echo '</li>' . "\n";
    }
    echo '</ol>' . "\n";
  }

  $criteria_text = $eligibility_module['eligibilityCriteria'] ?? '';
  if ($criteria_text) {
    echo '<h2 class="h5 mt-4">Eligibility</h2>' . "\n";
    $clean_criteria = strip_tags(str_replace(['<br/>', '<br />', '<br>'], "\n", $criteria_text));
    $parts = preg_split('/(Inclusion Criteria|Exclusion Criteria)\s*:/i', $clean_criteria, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($parts) > 1) {
      for ($i = 1; $i < count($parts); $i += 2) {
        $section_title = trim($parts[$i]);
        $section_body = trim($parts[$i + 1] ?? '');
        echo '<h3 class="h6 mt-3">' . htmlspecialchars($section_title) . ' Criteria</h3>' . "\n";
        echo '<ul>' . "\n";
        foreach (preg_split('/\n+/', $section_body) as $line) {
          $line = trim(preg_replace('/^[\*\-•]\s*/', '', trim($line)));
          if ($line !== '') {
            echo '<li>' . htmlspecialchars($line) . '</li>' . "\n";
          }
        }
        echo '</ul>' . "\n";
      }
    }
    else {
      echo '<p>' . nl2br(htmlspecialchars(trim($clean_criteria))) . '</p>' . "\n";
    }
  }

  $locations = $contacts_module['locations'] ?? [];
  if (!empty($locations)) {
    $total_locations = count($locations);
    echo '<h2 class="h5 mt-4">Locations (' . $total_locations . ')</h2>' . "\n";
    echo '<ul class="list-group mb-4">' . "\n";
    $shown = min(10, $total_locations);
    for ($i = 0; $i < $shown; $i++) {
      $location = $locations[$i];
      $facility = $location['facility'] ?? '';
      $city = $location['city'] ?? '';
      $state = $location['state'] ?? '';
      $country = $location['country'] ?? '';
      $location_status = $location['status'] ?? '';
      $location_parts = array_filter([$city, $state, $country]);
      echo '<li class="list-group-item d-flex justify-content-between align-items-start">' . "\n";
      echo '<div>';
      if ($facility) {
        echo '<strong>' . htmlspecialchars($facility) . '</strong>';
      }
      if (!empty($location_parts)) {
        echo '<br><span class="text-muted small">' . htmlspecialchars(implode(', ', $location_parts)) . '</span>';
      }
      echo '</div>' . "\n";
      if ($location_status) {
        echo build_status_badge($location_status);
      }
      echo '</li>' . "\n";
    }
    if ($total_locations > 10) {
      echo '<li class="list-group-item text-muted small">... and ' . ($total_locations - 10) . ' more locations</li>' . "\n";
    }
    echo '</ul>' . "\n";
  }
}

/**
 * Renders a single flattened value as HTML for the Data table.
 *
 * Dispatches on value type:
 *   null              -> em dash
 *   bool              -> Yes / No
 *   long string       -> scrollable container with nl2br
 *   scalar            -> escaped string
 *   empty array       -> em dash
 *   list of scalars   -> bullet <ul>
 *   list of objects   -> inline <table>
 */
function render_study_data_value(mixed $value): string {
  if (is_null($value)) {
    return '<em class="text-muted">-</em>';
  }

  if (is_bool($value)) {
    return $value ? 'Yes' : 'No';
  }

  if (!is_array($value)) {
    $string_value = (string) $value;
    if (strlen($string_value) > 200) {
      return '<div style="max-height:8rem;overflow-y:auto" class="small">' . nl2br(htmlspecialchars($string_value)) . '</div>';
    }
    return htmlspecialchars($string_value);
  }

  if (empty($value)) {
    return '<em class="text-muted">-</em>';
  }

  if (is_scalar($value[0]) || $value[0] === NULL) {
    $items = '';
    foreach ($value as $item) {
      $items .= '<li>' . htmlspecialchars((string) $item) . '</li>';
    }
    return '<ul class="mb-0 ps-3">' . $items . '</ul>';
  }

  $keys = array_keys($value[0]);
  $table = '<table class="table table-sm table-bordered mb-0"><thead><tr>';
  foreach ($keys as $key) {
    $table .= '<th>' . htmlspecialchars((string) $key) . '</th>';
  }
  $table .= '</tr></thead><tbody>';
  foreach ($value as $object_row) {
    $table .= '<tr>';
    foreach ($keys as $key) {
      $cell = $object_row[$key] ?? NULL;
      $table .= '<td>';
      $table .= is_array($cell) ? htmlspecialchars(json_encode($cell)) : htmlspecialchars((string) ($cell ?? ''));
      $table .= '</td>';
    }
    $table .= '</tr>';
  }
  $table .= '</tbody></table>';
  return $table;
}

/**
 * Renders the flat Data view for a single study as a two-column breadcrumb table.
 */
function render_study_data(array $data): void {
  echo '<table class="table table-sm table-bordered table-hover">' . "\n";
  echo '<thead><tr><th style="width:35%">Key</th><th>Value</th></tr></thead>' . "\n";
  echo '<tbody>' . "\n";
  foreach (flatten_study($data) as $key => $value) {
    $metadata_href = htmlspecialchars(CURRENT_SCRIPT . '?path=' . rawurlencode('/studies/metadata') . '#' . rawurlencode(metadata_anchor_id($key)));
    echo '<tr>' . "\n";
    echo '<td class="breadcrumb-key"><a href="' . $metadata_href . '">' . htmlspecialchars($key) . '</a></td>' . "\n";
    echo '<td>' . render_study_data_value($value) . '</td>' . "\n";
    echo '</tr>' . "\n";
  }
  echo '</tbody>' . "\n";
  echo '</table>' . "\n";
}

/**
 * Recursively renders arbitrary JSON data as definition lists, tables, or lists.
 */
function render_generic(mixed $data): void {
  if (is_array($data)) {
    $is_list = array_is_list($data);

    if (!$is_list) {
      echo '<dl class="row">' . "\n";
      foreach ($data as $key => $value) {
        echo '<dt class="col-sm-3">' . htmlspecialchars((string) $key) . '</dt>' . "\n";
        echo '<dd class="col-sm-9">' . "\n";
        render_generic($value);
        echo '</dd>' . "\n";
      }
      echo '</dl>' . "\n";
      return;
    }

    if (empty($data)) {
      echo '<em class="text-muted">empty</em>' . "\n";
      return;
    }

    if (is_scalar($data[0])) {
      echo '<ul class="list-group list-group-flush">' . "\n";
      foreach ($data as $item) {
        echo '<li class="list-group-item">' . htmlspecialchars((string) $item) . '</li>' . "\n";
      }
      echo '</ul>' . "\n";
      return;
    }

    if (is_array($data[0])) {
      $keys = array_keys($data[0]);
      $column_width = round(100 / count($keys)) . '%';
      echo '<table class="table table-sm table-bordered">' . "\n";
      echo '<thead><tr>' . "\n";
      foreach ($keys as $key) {
        echo '<th style="width:' . $column_width . '">' . htmlspecialchars((string) $key) . '</th>' . "\n";
      }
      echo '</tr></thead>' . "\n";
      echo '<tbody>' . "\n";
      foreach ($data as $row) {
        echo '<tr>' . "\n";
        foreach ($keys as $key) {
          $cell_value = $row[$key] ?? '';
          echo '<td>';
          if (is_array($cell_value)) {
            render_generic($cell_value);
          }
          else {
            echo htmlspecialchars((string) $cell_value);
          }
          echo '</td>' . "\n";
        }
        echo '</tr>' . "\n";
      }
      echo '</tbody>' . "\n";
      echo '</table>' . "\n";
      return;
    }

    echo '<ul class="list-group list-group-flush">' . "\n";
    foreach ($data as $item) {
      echo '<li class="list-group-item">' . "\n";
      render_generic($item);
      echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
    return;
  }

  if ($data === NULL) {
    echo '<em class="text-muted">null</em>' . "\n";
    return;
  }

  echo htmlspecialchars((string) $data);
}

/**
 * Renders a clickable API URL and a collapsible Raw JSON block.
 */
function render_raw_json(mixed $data, string $url = ''): void {
  if ($url) {
    echo '<p class="mt-3 mb-1 small text-muted">API URL: <a href="' . htmlspecialchars($url) . '" target="_blank" class="font-monospace">' . htmlspecialchars($url) . '</a></p>' . "\n";
  }
  echo '<details class="mt-2">' . "\n";
  echo '<summary class="small">Raw JSON</summary>' . "\n";
  echo '<pre class="bg-light p-3 rounded mt-2">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>' . "\n";
  echo '</details>' . "\n";
}
