# ClinicalTrials.gov

This directory contains a standalone PHP proof of concept for exploring the ClinicalTrials.gov REST API v2.

The runnable files are [clinicaltrialsgov.php](/Users/rockowij/Sites/drupal_playground/web/modules/custom/clinicaltrialsgov/test/clinicaltrialsgov.php) and [clinicaltrialsgov.inc](/Users/rockowij/Sites/drupal_playground/web/modules/custom/clinicaltrialsgov/test/clinicaltrialsgov.inc).

## Purpose

- Provide a browser-friendly explorer for the ClinicalTrials.gov API.
- Keep all upstream API calls server-side.
- Keep the implementation dependency-free and easy to inspect.

## File Layout

- `clinicaltrialsgov.php`
  Contains constants, endpoint metadata, request parsing, query helpers, API helpers, non-render data helpers, route helpers, and the thin top-level entrypoint.
- `clinicaltrialsgov.inc`
  Contains page rendering, forms, tables, study summary/data views, generic JSON rendering, and render-only helpers.
- `README.md`
  Human-facing notes, review steps, and direct API examples.
- `AGENTS.md`
  Agent-facing maintenance rules for this proof of concept.

## Main Behaviors

- `?path=` omitted
  Shows the endpoint index.
- `?path=/studies`
  Shows the search form and default study results.
- `?path=/studies&...`
  Fetches and renders search results.
- `?path=/studies/NCT...`
  Shows a single study with `summary` and `data` views.
- `?path=/studies/metadata`
  Shows a flattened metadata table keyed by dotted `name` paths, plus raw JSON.
- `?path=/stats/field/values&fields=...`
  Shows field value statistics.
- `?path=/stats/field/sizes&fields=...`
  Shows field size statistics.
- `?path=/stats/field/values` and `?path=/stats/field/sizes`
  Show the overview returned by that stats endpoint, plus a `fields` form for drilling into an individual field.
- Other supported endpoints
  Render through the generic JSON renderer plus raw JSON output.

## API Notes

Studies - These are the main endpoints which we can use to search and fetch studies.


- /studies - Used to search for studies.
- /studies/{nctId} - Used to fetch a single study.
- /studies/metadata - Use to fetch the study metadata (aka field name and data types)
- /studies/search-areas - Use to how search rankings are set up.
- /studies/enums - Enumerations for fields keyed by piece.

Stats - These can be ignored.

- /stats/size
- /stats/field/values
- /stats/field/sizes

Version

- /version

## Working API Examples

### Studies

- Study search
  `https://clinicaltrials.gov/api/v2/studies?query.cond=cancer&pageSize=10`
- Single study
  `https://clinicaltrials.gov/api/v2/studies/NCT04001699`
- Metadata
  `https://clinicaltrials.gov/api/v2/studies/metadata`
- Enums
  `https://clinicaltrials.gov/api/v2/studies/enums`
- Search areas
  `https://clinicaltrials.gov/api/v2/studies/search-areas`

### Stats

- Field value stats
  `https://clinicaltrials.gov/api/v2/stats/field/values?fields=OverallStatus`
- Field size stats
  `https://clinicaltrials.gov/api/v2/stats/field/sizes?fields=Condition`
- Total study count
  `https://clinicaltrials.gov/api/v2/stats/size`

### Version

- Version
  `https://clinicaltrials.gov/api/v2/version`

## Review Steps

1. Run `php -l web/modules/custom/clinicaltrialsgov/test/clinicaltrialsgov.php`.
2. Run `php -l web/modules/custom/clinicaltrialsgov/test/clinicaltrialsgov.inc`.
3. Open the explorer and confirm the index page loads with endpoint links.
4. Open `?path=/studies` and confirm the search form and default results appear on first load.
5. Submit a `/studies` search and confirm results plus raw JSON render.
6. Open a single study and confirm both `summary` and `data` views work.
7. Open `?path=/stats/field/values` and `?path=/stats/field/sizes` and confirm each calls its passed stats route, shows the overview returned by that route, and includes a `fields` form.
8. Click a linked `piece` value from either stats overview and confirm it loads the individual field stats using `fields=...`.
9. From the single-study data table, click a field link and confirm it lands on `/stats/field/values&fields=...`.
10. Open a generic endpoint such as `?path=/version` and confirm generic rendering plus raw JSON still work.
11. Confirm the direct ClinicalTrials.gov example URLs above match supported live API routes.
12. Trigger one bad stats request and confirm the error message shows the failing API URL.

## Notes

- This is still a proof of concept, not integrated Drupal runtime code.
- We should focus on the /studies and /studies/{nctId} endpoints while using the /studies/metadata for field information.
- When referencing fields, we should use the dotted `name` path. (ie derivedSection.conditionBrowseModule.meshes)
- The API should return JSON for all endpoints, including stats and metadata.
- The Manager should wrap the API calls
  - Method
  - getStudies(array $params = [])
  - getStudy
  - getStudyMetadata
  - getStudyFieldMetadata(string $index_field);

## Todo

- We need to pull a glossary from  https://clinicaltrials.gov/data-api/about-api/study-data-structure


