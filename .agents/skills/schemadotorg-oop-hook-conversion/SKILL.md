---
name: schemadotorg-oop-hook-conversion
description: Convert one Schema.org Blueprints base module or submodule from procedural runtime hooks to Drupal 11.2+ object-oriented hooks for issue #3622305. Use for module selection, hook/service analysis, implementation, testing, or progress tracking. Do not use for unrelated module audits or general Rector upgrades.
---

# Schema.org OOP hook conversion

Convert and verify one production module per run. A requested batch may share
one issue branch or merge request, but finish the module-level review and test
checkpoint before starting the next module.

## Load the project workflow

1. Read the local note for issue #3622305, especially its conversion todo,
   module ledger, POC evidence, and hook/service inventory.
2. Read [references/conversion-workflow.md](references/conversion-workflow.md)
   before planning or changing a module.
3. Use `schemadotorg-issue-maintenance` and `drupalorg-issue-maintenance` for
   tracker, approval, issue-fork, merge-request, and public-write policy. Do not
   duplicate or weaken their current requirements.

## Establish one-module scope

Run the read-only inspector from the project root:

```bash
.agents/skills/schemadotorg-oop-hook-conversion/scripts/inspect-hook-module.sh <base|submodule>
```

Confirm the main checkout, target branch, existing changes, selected module,
relevant tests, and current ledger status. Treat the raw project-wide Rector
output as evidence, not as the production implementation base.

If the module checkout contains the raw POC or other unscoped changes, perform
read-only analysis only. Do not discard or rewrite those changes until the
maintainer explicitly approves that exact action.

## Prepare the module decision

Before editing, present:

- procedural runtime hooks, required procedural functions, callbacks, helpers,
  and ordering constraints;
- the proposed hook class or cohesive classes;
- dependencies to inject and static lookups to remove;
- service IDs/interfaces to preserve and any separately proposed API change;
- translation approach and procedural-scan eligibility;
- targeted tests, broader verification, expected files, and current approval
  gate.

Resolve these decisions for the selected module before implementation. Do not
carry an unresolved service boundary or hook-order assumption into code.

## Implement after approval

- Use method-level `#[Hook('hook_name')]` attributes under
  `Drupal\MODULE\Hook`.
- Target Drupal 11.2+ directly; do not add `#[LegacyHook]` wrappers.
- Rely on automatic hook-class service registration and autowiring. Register a
  hook class explicitly only when automatic wiring cannot express a required
  dependency.
- Use constructor injection. Hook classes must not introduce static
  `\Drupal` lookups.
- Keep hook-specific adaptation in hook classes and reusable domain behavior in
  managers/builders. Preserve public service IDs and interfaces unless the
  maintainer separately approves a compatibility change.
- Replace ordering implemented through `hook_module_implements_alter()` with
  Drupal 11.2 ordering attributes and regression coverage.
- Retain core-required procedural install, update, and meta hooks.
- Enable `MODULE.skip_procedural_hook_scan: true` only after proving that no
  discoverable runtime hook remains procedural.

Make the smallest coherent change for the selected module. Do not begin the
next module in the same work cycle.

## Verify and record

Run targeted PHPUnit and code review first, rebuild the container, and run
`git diff --check`. Run the complete Schema.org suite at the end of each merge
request batch and whenever ordering or base-module behavior changes.

Update the issue-note ledger and applicable hook inventory with decisions,
commands, results, uncertainty, and the next approval gate. Stop for maintainer
review. Require separate approval before code edits, branch creation, commits,
pushes, merge requests, or public updates.
