# Rector OOP Hooks Proof of Concept

## Summary

Run Drupal Rector's raw OOP-hook conversion across the entire main
`schemadotorg` project to evaluate its output, test behavior, and inventory
service refactoring candidates. Work directly on the existing clean `1.0.x`
checkout. Do not create a branch or worktree, and do not commit, push, create a
merge request, or modify `schemadotorg_experimental`.

The verified standalone dry run found 61 affected input files, 58
`HookConvertRector` applications, and no Rector errors. The generated Git
footprint is larger because Rector writes hook classes and service files
directly to disk. The Allowed Formats baseline passes with 2 tests and 32
assertions.

## Implementation

- Leave the root `rector.php` unchanged. Follow Drupal Rector's upstream
  guidance and use its standalone hook-conversion configuration:
  - Run `ddev exec rector process web/modules/sandbox/schemadotorg --config=/usr/local/composer/vendor/palantirnet/drupal-rector/rector-hook-convert.php`.
  - Bypass `ddev rector process` because the local wrapper always appends the
    root `rector.php` and would override the intended standalone configuration.
  - Keep `HookConvertRector` isolated from the Drupal deprecation sets because
    Rector cannot feed newly created hook classes back through the same rule
    pipeline.
- Capture baseline evidence:
  - Confirm the nested module checkout is clean and on `1.0.x`.
  - Run the normal project configuration as a dry run only. It currently
    reports three unrelated changed files; the only deprecation conversion is
    `check_markup()` inside an API documentation example. Do not apply these
    changes during the POC.
  - Run the complete Schema.org Blueprints PHPUnit suite and code review.
  - Record existing failures, deprecations, and tool warnings separately.
- Apply Rector once to `web/modules/sandbox/schemadotorg`.
  - Preserve the generated `#[LegacyHook]` wrappers and explicit hook-service
    registrations unchanged for this review.
  - Do not add dependency injection, remove services, rename generated classes,
    add procedural-scan parameters, or manually fix generated output.
  - Leave all generated changes uncommitted on `1.0.x` for maintainer
    inspection.
- Inspect and classify the raw output:
  - Generated hook classes and converted methods.
  - Procedural hooks Rector intentionally skipped, including install/update
    hooks and `hook_module_implements_alter()`.
  - Callbacks and helper functions that remain procedural.
  - Static `\Drupal` lookups copied into hook methods.
  - Generated translation-trait usage, class naming, service registrations,
    formatting, and hook signatures.
  - Hook ordering behavior that will require later manual conversion.
  - Incidental import normalization in `*.api.php` and non-hook PHP files caused
    by the standalone configuration's import-name settings.
- Update the local issue note for #3622305 with:
  - Commands and baseline/post-conversion results.
  - Changed-file counts and observed Rector limitations.
  - A table with columns: module, hook, generated class/method, service ID or
    static dependency, delegated method, other callers, recommended action,
    and rationale.
  - Recommendations limited to `move into hook class`, `inject retained
    service`, `manual hook conversion`, `retain procedural-only`, or `refactor
    callback later`.
  - No actual service-boundary refactoring during this POC.

## Verification

Run after the raw conversion:

- Rebuild Drupal's container/cache to verify hook discovery and generated
  service definitions.
- Run the complete `schemadotorg` PHPUnit tree and compare results with the
  baseline.
- Run the two Allowed Formats tests explicitly as a known-good focused check.
- Run `ddev code-review web/modules/sandbox/schemadotorg`.
- Run `git diff --check` and inspect both the root-repository and nested-module
  statuses.
- Treat every new failure as POC evidence; diagnose and record it without fixing
  it during this phase.

## Execution results

- Baseline PHPUnit passed with 280 tests, 4,778 assertions, 245 deprecations,
  and 538 PHPUnit deprecations. Baseline PHPCS, CSpell, and ESLint passed;
  PHPStan reported 130 errors and Stylelint reported 2,464 errors.
- Rector completed with 61 changed input files, 58 rule applications, and no
  Rector errors. The resulting Git footprint is 111 modified tracked files and
  62 untracked files, including 58 generated hook classes.
- Drupal container/cache rebuilding passed. The two Allowed Formats tests
  passed with 32 assertions, and `git diff --check` passed.
- Post-conversion PHPUnit reported one failure in
  `SchemaDotOrgFocalPointKernelTest::testFocalPoint()`: `image_focal_point` was
  replaced by `image_image`, demonstrating hook-order drift in the raw output.
  A focused rerun reproduced the failure with 1 test and 24 assertions.
- Post-conversion PHPCS reported 4,194 errors and 429 warnings, PHPStan reported
  328 errors, and Stylelint reported 2,517 errors. CSpell and ESLint still
  passed. These results are retained as POC evidence and were not fixed.
- The local #3622305 note contains the full 236-hook service inventory and the
  24-function skipped runtime/helper inventory.

## Assumptions and guardrails

- The POC targets the entire main project, not only one pilot submodule.
- Rector's automated output is intentionally retained exactly as generated for
  review.
- No public service IDs, interfaces, or implementations are removed or changed.
- `schemadotorg_experimental` remains untouched and clean on `1.0.x`.
- After maintainer review, the reproducible raw output was intentionally
  discarded on 2026-09-10. The complete findings and inventory were retained,
  and both module checkouts were returned to clean `1.0.x` before production
  conversion work begins.
