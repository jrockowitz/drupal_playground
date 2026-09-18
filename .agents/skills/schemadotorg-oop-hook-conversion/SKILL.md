---
name: schemadotorg-oop-hook-conversion
description: Convert one Schema.org Blueprints base module or submodule from procedural runtime hooks to Drupal 11.2+ object-oriented hooks for issue #3622305. Use for module selection, hook/service analysis, implementation, testing, or progress tracking. Do not use for unrelated module audits or general Rector upgrades.
---

# Schema.org OOP hook conversion

Follow the [production plan](../../plans/schemadotorg-oop-hooks.md), local
[issue #3622305 note](../../schemadotorg-issue-maintenance/issues/3622305.md),
and [production hook checklist](../../schemadotorg-issue-maintenance/issues/3622305-oop-hook-checklist.md).
Use the [Rector POC inventory](../../schemadotorg-issue-maintenance/issues/3622305-oop-hook-rector-poc-inventory.md)
only as historical evidence and candidate hooks; reconcile it against current
source. Record each runtime hook's conversion, final class/method, and relevant
existing behavior coverage or coverage gap in the production checklist.
Use `schemadotorg-issue-maintenance` and `drupalorg-issue-maintenance` for the
current tracker, branch, approval, and public-write rules.

Start with the base `schemadotorg` module. Then convert one `schemadotorg_*`
submodule at a time, using the issue ledger and dependency order. Finish each
module's inventory, implementation, focused tests, ledger update, diff review,
and approved module-named commit before starting another. Keep Experimental in
separate linked work. A module commit may include a necessary, scoped dependency
fix in another module; document it and seek a separate decision for public API
changes. Use `Issue #3622305: Convert <module_machine_name> hooks to OOP` as
the subject for each module conversion commit. End AI-authored commit messages
with `AI-assisted by Codex`.

Run the read-only inspector from the project root:

```bash
.agents/skills/schemadotorg-oop-hook-conversion/scripts/inspect-hook-module.sh <base|submodule>
```

Its scan is incomplete: inspect nested files, callbacks, cross-module callers,
services, and tests before deciding scope. Check the nested checkout's branch,
upstream divergence, and uncommitted changes. Preserve existing changes and
follow the issue workflow's approval gates before edits, branch creation,
commits, pushes, MRs, or public updates.

Key conversion traps are hook ordering, publicly used services, named
callbacks, and procedural-scan eligibility. Replace the ordering effect of
`hook_module_implements_alter()`; do not convert that meta hook itself. Retain
required procedural hooks and callbacks. Put every form-alter hook in
`ModuleNameFormHooks`; put the module's own `MODULE_*` hooks in
`ModuleNameHooks`. Put hooks implemented on behalf of optional contributed
modules in `ModuleNameContribHooks`. Do not create `IntegrationHooks`.
Group other generic hooks by responsibility, such as Help, Module, Page, Field,
and Node hook classes. Document the final naming convention in
`docs/DECISIONS.md` after the conversion. Move
hook-specific logic out of delegated services, inject its dependencies, and
present any service API removal in the module design review. Use existing tests
to check behavior and ordering. Do not add, move, or expand tests just because
hooks move to classes; change an existing test only if the move breaks it, and
preserve its assertions. Record coverage gaps for a separate testing decision.
When a module's `*.module` file has no remaining functions, callbacks, or other
runtime code and nothing loads it explicitly, delete the empty file. A Drupal
module does not need a `*.module` file solely to register OOP hooks.
The plan contains the implementation and verification checklist.
