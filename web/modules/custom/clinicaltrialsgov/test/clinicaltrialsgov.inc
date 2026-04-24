<?php

// Rendering functions only.
// Requires constants (API_BASE, SELF), $status_colours, and build_query_string()
// from clinical-trials-gov.php, which includes this file.

/**
 * Outputs the HTML head, navbar, and opening container with an <h1> page title.
 */
function render_page_start(string $title): void {
  echo '<!DOCTYPE html>' . "\n";
  echo '<html lang="en">' . "\n";
  echo '<head>' . "\n";
  echo '<meta charset="UTF-8">' . "\n";
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
  echo '<title>' . htmlspecialchars($title) . ' — ClinicalTrials.gov Explorer</title>' . "\n";
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">' . "\n";
  echo '<style>' . "\n";
  echo 'pre { font-size: .85rem; }' . "\n";
  echo 'details > summary { cursor: pointer; user-select: none; color: #6c757d; }' . "\n";
  echo 'details > summary:hover { color: #495057; }' . "\n";
  echo 'details.param-box { border: 1px solid #dee2e6; border-radius: .375rem; padding: 1rem; }' . "\n";
  echo 'details.param-box > summary { color: #212529; font-size: 1.25rem; font-weight: 500; margin-bottom: 0; }' . "\n";
  echo 'dt { font-weight: 600; }' . "\n";
  echo 'table thead { --bs-table-bg: #e9ecef; --bs-table-color: #212529; }' . "\n";
  echo '</style>' . "\n";
  echo '</head>' . "\n";
  echo '<body>' . "\n";
  echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">' . "\n";
  echo '<div class="container">' . "\n";
  echo '<a class="navbar-brand" href="' . htmlspecialchars(SELF) . '">ClinicalTrials.gov Explorer</a>' . "\n";
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
 * Renders the endpoint index grouped by section as linked list groups.
 */
function render_endpoint_index(array $endpoints): void {
  // Group endpoints by section.
  $sections = [];
  foreach ($endpoints as $endpoint) {
    $sections[$endpoint['section']][] = $endpoint;
  }

  foreach ($sections as $section_name => $section_endpoints) {
    echo '<h2 class="mt-4 mb-2">' . htmlspecialchars($section_name) . '</h2>' . "\n";
    echo '<ul class="list-group mb-4">' . "\n";
    foreach ($section_endpoints as $endpoint) {
      $path = $endpoint['path'];
      $is_placeholder = (str_contains($path, '{'));
      $href = $is_placeholder ? NULL : htmlspecialchars(SELF . '?path=' . rawurlencode($path));
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
  echo '<form action="' . htmlspecialchars(SELF) . '" method="get" class="mt-3">' . "\n";
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
        $href = htmlspecialchars(SELF . '?path=' . rawurlencode($path) . '&fields=' . rawurlencode($cell_value));
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
 * Returns a Bootstrap badge HTML string for a study overall status value.
 */
function build_status_badge(string $status): string {
  global $status_colours;
  $colour = $status_colours[$status] ?? 'light text-dark';
  return '<span class="badge bg-' . $colour . '">' . htmlspecialchars($status) . '</span>';
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

  $summary = 'Showing ' . $count . ' ' . ($count === 1 ? 'study' : 'studies');
  if ($total_count !== NULL) {
    $summary .= ' of ' . number_format($total_count) . ' total';
  }
  echo '<p class="text-muted small">' . htmlspecialchars($summary) . '</p>' . "\n";

  echo '<table class="table table-striped table-hover table-sm align-middle">' . "\n";
  echo '<thead>' . "\n";
  echo '<tr>';
  echo '<th style="width:10%">NCT ID</th>';
  echo '<th style="width:35%">Title</th>';
  echo '<th style="width:13%">Status</th>';
  echo '<th style="width:10%">Phase</th>';
  echo '<th style="width:32%">Conditions</th>';
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
    $phases = $design_module['phases'] ?? [];
    $phase = implode(', ', $phases);
    $conditions = implode(', ', $conditions_module['conditions'] ?? []);

    echo '<tr>' . "\n";
    echo '<td><a href="' . htmlspecialchars(SELF . '?path=' . rawurlencode('/studies/' . $nct_id)) . '" class="font-monospace">' . htmlspecialchars($nct_id) . '</a></td>' . "\n";
    echo '<td>' . htmlspecialchars($title) . '</td>' . "\n";
    echo '<td>' . build_status_badge($status) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($phase) . '</td>' . "\n";
    echo '<td>' . htmlspecialchars($conditions) . '</td>' . "\n";
    echo '</tr>' . "\n";
  }
  echo '</tbody>' . "\n";
  echo '</table>' . "\n";

  if ($next_page_token) {
    $next_params = $current_params;
    $next_params['pageToken'] = $next_page_token;
    $href = htmlspecialchars(SELF . '?path=' . rawurlencode('/studies') . '&' . build_query_string($next_params));
    echo '<a href="' . $href . '" class="btn btn-outline-secondary btn-sm">Next page &rarr;</a>' . "\n";
  }
}

/**
 * Recursively renders arbitrary JSON data as definition lists, tables, or lists.
 */
function render_generic(mixed $data, int $depth = 0): void {
  if (is_array($data)) {
    $is_list = array_is_list($data);

    if (!$is_list) {
      echo '<dl class="row">' . "\n";
      foreach ($data as $key => $value) {
        echo '<dt class="col-sm-3">' . htmlspecialchars((string) $key) . '</dt>' . "\n";
        echo '<dd class="col-sm-9">' . "\n";
        render_generic($value, $depth + 1);
        echo '</dd>' . "\n";
      }
      echo '</dl>' . "\n";
    }
    elseif (empty($data)) {
      echo '<em class="text-muted">empty</em>' . "\n";
    }
    elseif (is_scalar($data[0])) {
      echo '<ul class="list-group list-group-flush">' . "\n";
      foreach ($data as $item) {
        echo '<li class="list-group-item">' . htmlspecialchars((string) $item) . '</li>' . "\n";
      }
      echo '</ul>' . "\n";
    }
    elseif (is_array($data[0])) {
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
            render_generic($cell_value, $depth + 1);
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
    else {
      echo '<ul class="list-group list-group-flush">' . "\n";
      foreach ($data as $item) {
        echo '<li class="list-group-item">' . "\n";
        render_generic($item, $depth + 1);
        echo '</li>' . "\n";
      }
      echo '</ul>' . "\n";
    }
  }
  elseif ($data === NULL) {
    echo '<em class="text-muted">null</em>' . "\n";
  }
  else {
    echo htmlspecialchars((string) $data);
  }
}

/**
 * Renders a clickable API URL and a collapsible Raw JSON block.
 */
function render_raw_json(mixed $data, string $url = ''): void {
  if ($url !== '') {
    echo '<p class="mt-3 mb-1 small text-muted">API URL: <a href="' . htmlspecialchars($url) . '" target="_blank" class="font-monospace">' . htmlspecialchars($url) . '</a></p>' . "\n";
  }
  echo '<details class="mt-2">' . "\n";
  echo '<summary class="small">Raw JSON</summary>' . "\n";
  echo '<pre class="bg-light p-3 rounded mt-2">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>' . "\n";
  echo '</details>' . "\n";
}
