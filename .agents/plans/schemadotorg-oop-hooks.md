# Rector OOP Hooks Proof of Concept

## Summary

Run Drupal Rector's raw OOP-hook conversion across the entire main
`schemadotorg` project to evaluate its output, test behavior, and inventory
service refactoring candidates. Work directly on the existing clean `1.0.x`
checkout. Do not create a branch or worktree, and do not commit, push, create a
merge request, or modify `schemadotorg_experimental`.

The preliminary dry run found 61 affected files and 58 `HookConvertRector`
applications. The Allowed Formats baseline passes with 2 tests and 32
assertions.

## Implementation

- Update the root `rector.php` into a dedicated hook-conversion configuration:
  - Add `DrupalRector\Rector\Convert\HookConvertRector`.
  - Comment out the Drupal 9-11 sets and annotation-to-attribute configuration
    rather than deleting them.
  - Register only `HookConvertRector`.
  - Retain the import-name configuration.
  - Document that this configuration must remain isolated from deprecation
    rules.
- Capture baseline evidence:
  - Confirm the nested module checkout is clean and on `1.0.x`.
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

## Assumptions and guardrails

- The POC targets the entire main project, not only one pilot submodule.
- Rector's automated output is intentionally retained exactly as generated for
  review.
- No public service IDs, interfaces, or implementations are removed or changed.
- `schemadotorg_experimental` remains untouched on its existing unrelated
  branch.
- Generated changes remain uncommitted until the maintainer reviews them and
  decides which cleanup and architectural refactors should form the real
  implementation.
