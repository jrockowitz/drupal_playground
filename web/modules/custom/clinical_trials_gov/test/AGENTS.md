# ClinicalTrials.gov Agent Notes

## Scope

- This directory contains a standalone PHP proof of concept.
- Keep edits focused on the explorer unless the user explicitly asks to promote or rewrite it.

## File Boundaries

- `clinical_trials_gov.php`
  Keep constants, static endpoint data, request parsing, query helpers, API helpers, non-render data helpers, route helpers, and the thin top-level entry flow here.
- `clinical_trials_gov.inc`
  Keep all render functions and render-only helpers here.

## Router Convention

- Prefer a thin top-level entrypoint in `clinical_trials_gov.php`.
- Put request branching in named route helper functions.
- Keep route matching rules visible in `route_request()` when the checks are small and only used once.
- Add a short inline comment inside each route branch that names the handled paths.
- Do not leave a long inline procedural router at file scope.
- Do not wrap the entire script in one large function unless the user asks for that structure.

## Ordering

- In `clinical_trials_gov.php`, keep this order:
  constants, data, request helpers, API helpers, non-render data helpers, routing helpers, include, entrypoint.
- In `clinical_trials_gov.inc`, keep this order:
  page chrome, small render helpers, endpoint-specific renderers, study-specific renderers, generic/raw renderers.

## Behavior Guardrails

- `/studies` with no query params should render the form and default results.
- `/studies/metadata` should render flattened dotted keys built from each node's `name`, not the generic nested JSON renderer.
- Stats endpoints should use only the documented `fields` query parameter.
- Stats field endpoints with no `fields` param should still call the passed stats route and render that route's overview response.
- Individual stats requests should use the same route with `fields=...`, whether submitted from the form or linked from a `piece` value in the overview table.
- Stats-route error messages should include the failing upstream API URL.
- Preserve the single-study `summary` and `data` toggle unless the user asks to remove it.
- Keep all upstream API calls server-side.

## Working API Examples

- `https://clinicaltrials.gov/api/v2/version`
- `https://clinicaltrials.gov/api/v2/stats/size`
- `https://clinicaltrials.gov/api/v2/studies/enums`
- `https://clinicaltrials.gov/api/v2/studies/metadata`
- `https://clinicaltrials.gov/api/v2/studies/search-areas`
- `https://clinicaltrials.gov/api/v2/studies/NCT04001699`
- `https://clinicaltrials.gov/api/v2/studies?query.cond=cancer&pageSize=10`
- `https://clinicaltrials.gov/api/v2/stats/field/values?fields=OverallStatus`
- `https://clinicaltrials.gov/api/v2/stats/field/values?fields=Phase`
- `https://clinicaltrials.gov/api/v2/stats/field/sizes?fields=Condition`

## Documentation

- Keep `README.md` and `AGENTS.md` in this `test/` directory alongside the runnable PHP files.
- Update `README.md` when supported flows, file boundaries, review steps, or example API URLs change.
- Keep the stats-route docs aligned with the live ClinicalTrials.gov API examples, not outdated local wording.
- If the original spec changes or behavior intentionally diverges, note that clearly in docs or in the change summary.
