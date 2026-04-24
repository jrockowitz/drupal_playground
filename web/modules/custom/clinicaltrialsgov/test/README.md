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
- `?path=/stats/field/values&fields=...`
  Shows field value statistics.
- `?path=/stats/field/sizes&fields=...`
  Shows field size statistics.
- `?path=/stats/field/values` and `?path=/stats/field/sizes`
  Show the overview returned by that stats endpoint, plus a `fields` form for drilling into an individual field.
- Other supported endpoints
  Render through the generic JSON renderer plus raw JSON output.

## Working API Examples

- Version
  `https://clinicaltrials.gov/api/v2/version`
- Total study count
  `https://clinicaltrials.gov/api/v2/stats/size`
- Enums
  `https://clinicaltrials.gov/api/v2/studies/enums`
- Single study
  `https://clinicaltrials.gov/api/v2/studies/NCT04001699`
- Study search
  `https://clinicaltrials.gov/api/v2/studies?query.cond=cancer&pageSize=10`
- Metadata
  `https://clinicaltrials.gov/api/v2/studies/metadata`
- Search areas
  `https://clinicaltrials.gov/api/v2/studies/search-areas`
- Field value stats
  `https://clinicaltrials.gov/api/v2/stats/field/values?fields=OverallStatus`
- Phase value stats
  `https://clinicaltrials.gov/api/v2/stats/field/values?fields=Phase`
- Field size stats
  `https://clinicaltrials.gov/api/v2/stats/field/sizes?fields=Condition`

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

- The original spec is in [docs/CLINICAL-TRIALS-GOV-PHP-SPEC.md](/Users/rockowij/Sites/drupal_playground/docs/CLINICAL-TRIALS-GOV-PHP-SPEC.md).
- The stats routes follow the live ClinicalTrials.gov examples that use `fields`, even though older parts of the local spec still mention `field`.
- The no-parameter stats pages still call their passed stats route and render that overview response instead of substituting `/studies/metadata`.
- This is still a proof of concept, not integrated Drupal runtime code.
