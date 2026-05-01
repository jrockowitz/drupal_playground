# ClinicalTrials.gov API Explorer — PHP POC Spec

## Overview

A single self-contained PHP file (`clinicaltrialgov.php`) that acts as a browser-friendly
explorer for the [ClinicalTrials.gov REST API v2](https://clinicaltrials.gov/api/v2).
It lists all available endpoints, provides a parameter form for `/studies`, and renders
both a formatted view and a raw JSON `<details>` block for every response.

**Goals**

- Proof-of-concept only — no framework, no dependencies, no auth
- Simple functional PHP (no classes)
- All API calls made server-side (avoids browser CORS)
- Every request is stateless and self-contained

---

## Base URL

```
https://clinicaltrials.gov/api/v2
```

All responses are requested with `format=json`.

---

## URL / Routing Design

The file's own URL is the only entry point. Routing is driven by the `path` query parameter.

| Browser URL | Behaviour |
|---|---|
| `clinicaltrialgov.php` | Index: list all endpoints |
| `clinicaltrialgov.php?path=/studies` | Show `/studies` parameter form |
| `clinicaltrialgov.php?path=/studies&query.cond=cancer&pageSize=10` | Fetch & render `/studies` results |
| `clinicaltrialgov.php?path=/studies/NCT04001699` | Fetch & render single study |
| `clinicaltrialgov.php?path=/version` | Fetch & render `/version` |
| `clinicaltrialgov.php?path=/studies/enums` | Fetch & render `/studies/enums` |
| *(etc.)* | Any other path is fetched directly |

**Pass-through logic:** After reading `$_GET['path']`, all remaining `$_GET` keys are
forwarded verbatim as query string parameters to the upstream API.

---

## Endpoints

All endpoints are GET requests; no API key is required.

| Path | Description | Params? |
|---|---|---|
| `GET /studies` | Search / list studies | Yes — see below |
| `GET /studies/{nctId}` | Single study record | Path only |
| `GET /studies/metadata` | Study data model / field tree | None |
| `GET /studies/search-areas` | Full-text search areas and their syntax | None |
| `GET /studies/enums` | All enumeration types and their allowed values | None |
| `GET /stats/size` | Total number of studies in the database | None |
| `GET /stats/field/values` | Value stats for a specific field | `field` param |
| `GET /stats/field/sizes` | Size stats for a specific field | `field` param |
| `GET /version` | API version and data timestamp | None |

---

## `/studies` Query Parameters

Only `/studies` renders a form. Parameters are defined as a flat PHP array and looped
over to produce form elements.

```php
$studies_params = [
  // key,                   type,      description
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
```

**Form element rules:**

- `string` → `<input type="text" class="form-control">`
- `integer` → `<input type="number" class="form-control">`
- `boolean` → `<select class="form-select">` with options `(blank)`, `true`, `false`
- All fields are optional; empty fields are omitted from the query string
- Form `action` is `clinicaltrialgov.php`; hidden `<input name="path" value="/studies">`
- Submitted values pre-populate the form on reload

### `/stats/field/values` and `/stats/field/sizes` forms

These endpoints require a single `field` query parameter. They each render a minimal
inline form — just one labeled text input and a submit button — using the same
Bootstrap form classes as the `/studies` form.

---

## CSS / Styling

**Bootstrap 5** loaded from CDN. No custom CSS file; a small `<style>` block handles
only the few things Bootstrap doesn't cover out of the box.

```html
<!-- In <head> -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">

<!-- Before </body> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFEfVZc+/6Cv3w2a1EtIbFDLbY9"
        crossorigin="anonymous"></script>
```

**Minimal `<style>` additions** (only what Bootstrap doesn't provide):

```css
pre { font-size: .85rem; }
details > summary { cursor: pointer; }
dt { font-weight: 600; }
```

### Bootstrap classes used throughout

| Element | Classes |
|---|---|
| Page wrapper | `container py-4` |
| Top navbar | `navbar navbar-expand-lg navbar-dark bg-dark mb-4` |
| Endpoint index cards | `row row-cols-1 row-cols-md-2 g-3` / `card h-100` / `card-body` |
| Endpoint path label | `card-title font-monospace` |
| Endpoint description | `card-text text-muted` |
| Studies table | `table table-striped table-hover table-sm align-middle` |
| Status badges | `badge` + contextual colour (see below) |
| Study detail term/value | `row mb-1` / `col-sm-3 fw-semibold` / `col-sm-9` |
| Forms | `mb-3` / `form-label` / `form-control` / `form-select` |
| Submit button | `btn btn-primary` |
| Pagination link | `btn btn-outline-secondary btn-sm` |
| Raw JSON `<details>` | `mt-3` on wrapper; `bg-light p-3 rounded` on `<pre>` |
| Error alert | `alert alert-danger` |
| Page heading | `h4` |
| Section headings | `h5 mt-4 mb-3` |

**Status badge colour mapping:**

```php
$status_colours = [
  'RECRUITING'              => 'success',
  'NOT_YET_RECRUITING'      => 'warning text-dark',
  'ACTIVE_NOT_RECRUITING'   => 'info text-dark',
  'COMPLETED'               => 'secondary',
  'TERMINATED'              => 'danger',
  'WITHDRAWN'               => 'danger',
  'SUSPENDED'               => 'warning text-dark',
  // default                => 'light text-dark'
];
```

---

## File Structure

```
clinicaltrialgov.php
│
├── CONSTANTS
│   └── API_BASE  'https://clinicaltrials.gov/api/v2'
│
├── DATA
│   ├── $endpoints[]      — all endpoints (path, description, has_param_form)
│   └── $studies_params[] — /studies form field definitions
│
├── FUNCTIONS
│   ├── fetch_api(string $path, array $params): array
│   │     Calls the API via cURL (falling back to file_get_contents).
│   │     Always appends format=json.
│   │     Returns ['data' => $decoded] or ['error' => $message].
│   │
│   ├── build_query_string(array $params): string
│   │     Omits empty values. Handles dot-notation keys correctly.
│   │
│   ├── render_page_start(string $title): void
│   │     Outputs <!DOCTYPE html> … <body> with Bootstrap CDN link and navbar.
│   │
│   ├── render_page_end(): void
│   │     Outputs Bootstrap JS bundle tag then </body></html>.
│   │
│   ├── render_endpoint_index(array $endpoints): void
│   │     Renders endpoint cards in a 2-column Bootstrap grid.
│   │     Each card: monospace path as title, description as body text, linked button.
│   │
│   ├── render_studies_form(array $params, array $current_values): void
│   │     Renders the /studies parameter form with Bootstrap form classes.
│   │     Two-column layout (row/col-md-6) for the inputs.
│   │
│   ├── render_studies_results(array $data): void
│   │     Renders a Bootstrap striped table of study summaries.
│   │     NCT ID column links to ?path=/studies/{nctId}.
│   │     Status column rendered as a coloured badge.
│   │     "Next page →" button rendered if nextPageToken is present.
│   │
│   ├── render_study_detail(array $data): void
│   │     Renders key fields of a single study using Bootstrap row/col.
│   │     Status rendered as a coloured badge.
│   │
│   ├── render_generic(mixed $data): void
│   │     Recursive renderer for any other JSON response.
│   │     Associative arrays → <dl class="row"> with col-sm-3/col-sm-9.
│   │     Sequential arrays of scalars → <ul class="list-group list-group-flush">.
│   │     Sequential arrays of objects → Bootstrap table table-sm table-bordered.
│   │     Scalar → plain text.
│   │
│   └── render_raw_json(mixed $data): void
│         Outputs a Bootstrap-styled <details> block with a <pre> inside.
│
└── ROUTER  (executed at top level, after all function definitions)
    ├── $path and $params parsed from $_SERVER['QUERY_STRING'] (preserves dot keys)
    │
    ├── if !$path
    │     → render_endpoint_index()
    │
    ├── elseif $path === '/studies' && empty($params)
    │     → render_studies_form()
    │
    ├── elseif $path === '/studies' && !empty($params)
    │     → fetch_api('/studies', $params)
    │     → render_studies_form()  (pre-populated, above results)
    │     → render_studies_results() + render_raw_json()
    │
    ├── elseif preg_match('/^\/studies\/[A-Z0-9]+$/', $path)
    │     → fetch_api($path)
    │     → render_study_detail() + render_raw_json()
    │
    ├── elseif $path === '/stats/field/values' && empty($params['field'])
    │     → render single-field form
    │
    ├── elseif $path === '/stats/field/sizes' && empty($params['field'])
    │     → render single-field form
    │
    └── else
          → fetch_api($path, $params)
          → render_generic() + render_raw_json()
```

---

## Results Rendering

### `/studies` list

Rendered as a Bootstrap table with columns:

| NCT ID | Title | Status | Phase | Conditions |
|---|---|---|---|---|
| Linked to `?path=/studies/{nctId}` | `BriefTitle` | coloured `badge` | `Phase` | comma-joined |

- If `nextPageToken` is present, render a `btn btn-outline-secondary btn-sm` "Next page →"
  link that appends `pageToken={nextPageToken}` plus all current params to the URL.
- If `totalCount` is in the response, display it above the table as `text-muted small`.

### `/studies/{nctId}` detail

Key fields rendered with Bootstrap `row` / `col-sm-3` (label) / `col-sm-9` (value).
Skip any field absent from the response.

- NCT ID
- Brief Title
- Official Title
- Overall Status (as coloured badge)
- Phase
- Study Type
- Conditions
- Interventions
- Sponsor
- Start Date
- Primary Completion Date
- Brief Summary

### Generic renderer

Used for `/version`, `/studies/enums`, `/stats/*`, `/studies/search-areas`, `/studies/metadata`:

- Associative array → `<dl class="row">` with `<dt class="col-sm-3">` / `<dd class="col-sm-9">` (recursive)
- Sequential array of scalars → `<ul class="list-group list-group-flush">`
- Sequential array of objects → `<table class="table table-sm table-bordered">` (first object's keys as `<th>`)
- Scalar → plain text

### Raw JSON block

Always rendered below the formatted output:

```html
<details class="mt-3">
  <summary class="btn btn-sm btn-outline-secondary mb-2">Raw JSON</summary>
  <pre class="bg-light p-3 rounded"><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) ?></pre>
</details>
```

---

## Error Handling

`fetch_api()` returns `['error' => $message]` in these cases:

- cURL error (connection refused, timeout, DNS failure)
- HTTP status code ≥ 400 (include status code and response body in message)
- `json_decode()` returns `null` for an unexpected non-JSON body

The router checks for `isset($result['error'])` and renders
`<div class="alert alert-danger">…</div>` before any other output.

---

## Implementation Notes

1. **Dot-notation keys**: PHP's `$_GET` converts `query.cond` → `query_cond`. Read
   `$_SERVER['QUERY_STRING']` directly and use `parse_str()` to preserve dot keys.

2. **cURL preferred**: Use cURL with a 10 s timeout and `CURLOPT_RETURNTRANSFER`.
   Fall back to `file_get_contents` with a stream context only if cURL is unavailable.

3. **`format=json` only for `/studies` and `/studies/{nctId}`**: These are the only two
   endpoints that support multiple output formats (JSON and CSV). All other endpoints
   return JSON exclusively and will return an error if `format` is passed. The PHP file
   should only append `format=json` when proxying `/studies` or `/studies/{nctId}`.

4. **Security**: All output passed through `htmlspecialchars()`. Query string values are
   never `eval()`'d or used in file paths. No user data is written anywhere.

4. **No sessions**: Every render is fully driven by the current request's query string.
   Back-button navigation works naturally.

---

## API Example Links

Live URLs for testing each endpoint directly against the ClinicalTrials.gov v2 API.
Open in a browser or paste into `curl` to verify responses.

> **Note on `format` parameter:** Only `/studies` and `/studies/{nctId}` accept a
> `format` parameter (`json` or `csv`) because they are the only endpoints that support
> multiple output formats. All other endpoints return JSON exclusively and will return
> an error if `format` is passed.
>
> **Note on URL accuracy:** These example URLs were constructed from the official
> OpenAPI spec and third-party documentation, but could not all be verified by direct
> execution due to network restrictions in the authoring environment. Some URLs may
> return 400 errors due to incorrect parameter names or values. Use them as a starting
> point and consult the interactive API docs at
> https://clinicaltrials.gov/data-api/api to confirm exact parameter names before
> implementing.

### `GET /studies`

| Description | URL |
|---|---|
| Cancer studies, recruiting, 5 results | [/api/v2/studies?query.cond=cancer&filter.overallStatus=RECRUITING&pageSize=5](https://clinicaltrials.gov/api/v2/studies?query.cond=cancer&filter.overallStatus=RECRUITING&pageSize=5) |
| Diabetes, phase 2, with total count (`aggFilters` for phase) | [/api/v2/studies?query.cond=diabetes&aggFilters=phase:phase2&pageSize=5&countTotal=true](https://clinicaltrials.gov/api/v2/studies?query.cond=diabetes&aggFilters=phase:phase2&pageSize=5&countTotal=true) |
| Interventional studies, sorted by last update (`aggFilters` for study type) | [/api/v2/studies?aggFilters=studyType:int&pageSize=5&sort=LastUpdatePostDate:desc](https://clinicaltrials.gov/api/v2/studies?aggFilters=studyType:int&pageSize=5&sort=LastUpdatePostDate:desc) |
| Search by sponsor (Memorial Sloan Kettering) | [/api/v2/studies?query.spons=memorial+sloan+kettering&pageSize=5](https://clinicaltrials.gov/api/v2/studies?query.spons=memorial+sloan+kettering&pageSize=5) |
| Search by intervention (immunotherapy), recruiting | [/api/v2/studies?query.intr=immunotherapy&filter.overallStatus=RECRUITING&pageSize=5](https://clinicaltrials.gov/api/v2/studies?query.intr=immunotherapy&filter.overallStatus=RECRUITING&pageSize=5) |

### `GET /studies/{nctId}`

| Description | URL |
|---|---|
| A known cancer trial | [/api/v2/studies/NCT04001699](https://clinicaltrials.gov/api/v2/studies/NCT04001699) |
| A completed COVID-19 trial | [/api/v2/studies/NCT04280705](https://clinicaltrials.gov/api/v2/studies/NCT04280705) |
| A diabetes trial | [/api/v2/studies/NCT03107884](https://clinicaltrials.gov/api/v2/studies/NCT03107884) |

### `GET /studies/metadata`

| Description | URL |
|---|---|
| Full study data model / field tree | [/api/v2/studies/metadata](https://clinicaltrials.gov/api/v2/studies/metadata) |

### `GET /studies/search-areas`

| Description | URL |
|---|---|
| All full-text search areas | [/api/v2/studies/search-areas](https://clinicaltrials.gov/api/v2/studies/search-areas) |

### `GET /studies/enums`

| Description | URL |
|---|---|
| All enumeration types and allowed values | [/api/v2/studies/enums](https://clinicaltrials.gov/api/v2/studies/enums) |

### `GET /stats/size`

| Description | URL |
|---|---|
| Total number of studies in the database | [/api/v2/stats/size](https://clinicaltrials.gov/api/v2/stats/size) |

### `GET /stats/field/values`

| Description | URL |
|---|---|
| Value distribution for `Phase` | [/api/v2/stats/field/values?fields=Phase](https://clinicaltrials.gov/api/v2/stats/field/values?fields=Phase) |
| Value distribution for `OverallStatus` | [/api/v2/stats/field/values?fields=OverallStatus](https://clinicaltrials.gov/api/v2/stats/field/values?fields=OverallStatus) |
| Value distribution for `StudyType` | [/api/v2/stats/field/values?fields=StudyType](https://clinicaltrials.gov/api/v2/stats/field/values?fields=StudyType) |

### `GET /stats/field/sizes`

| Description | URL |
|---|---|
| Size stats for `BriefTitle` | [/api/v2/stats/field/sizes?fields=BriefTitle](https://clinicaltrials.gov/api/v2/stats/field/sizes?fields=BriefTitle) |
| Size stats for `BriefSummary` | [/api/v2/stats/field/sizes?fields=BriefSummary](https://clinicaltrials.gov/api/v2/stats/field/sizes?fields=BriefSummary) |

### `GET /version`

| Description | URL |
|---|---|
| API version and database timestamp | [/api/v2/version](https://clinicaltrials.gov/api/v2/version) |

---

## Example URLs

```
# Index
clinicaltrialgov.php

# /studies form (no params)
clinicaltrialgov.php?path=/studies

# /studies search (format=json sent by PHP automatically for this endpoint)
clinicaltrialgov.php?path=/studies&query.cond=cancer&filter.overallStatus=RECRUITING&pageSize=20&countTotal=true

# Single study (format=json sent by PHP automatically for this endpoint)
clinicaltrialgov.php?path=/studies/NCT04001699

# Paginate to next page
clinicaltrialgov.php?path=/studies&query.cond=cancer&pageSize=20&pageToken=NEXT_TOKEN_HERE

# Study metadata / field tree
clinicaltrialgov.php?path=/studies/metadata

# Search areas
clinicaltrialgov.php?path=/studies/search-areas

# Enumerations
clinicaltrialgov.php?path=/studies/enums

# Stats — total size
clinicaltrialgov.php?path=/stats/size

# Stats — field values
clinicaltrialgov.php?path=/stats/field/values&fields=Phase

# Stats — field sizes
clinicaltrialgov.php?path=/stats/field/sizes&fields=BriefTitle

# Version info
clinicaltrialgov.php?path=/version
```
