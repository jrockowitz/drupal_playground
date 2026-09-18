---
name: schemadotorg-oop-hook-conversion
description: Convert one Schema.org Blueprints base module or submodule from procedural runtime hooks to Drupal 11.2+ object-oriented hooks for issue #3622305. Use for module selection, hook/service analysis, implementation, testing, or progress tracking. Do not use for unrelated module audits or general Rector upgrades.
---

# Schema.org OOP hook conversion

Follow the [production plan](../../plans/schemadotorg-oop-hooks.md) and local
[issue #3622305 note](../../schemadotorg-issue-maintenance/issues/3622305.md).
Use `schemadotorg-issue-maintenance` and `drupalorg-issue-maintenance` for the
current tracker, branch, approval, and public-write rules.

Start with the base `schemadotorg` module. Then convert one `schemadotorg_*`
submodule at a time, using the issue ledger and dependency order. Finish each
module's inventory, implementation, focused tests, ledger update, diff review,
and approved module-named commit before starting another. Keep Experimental in
separate linked work. A module commit may include a necessary, scoped dependency
fix in another module; document it and seek a separate decision for public API
changes.

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
`ModuleNameFormHooks`; group other related hooks by responsibility. Move
hook-specific logic out of delegated services, inject its dependencies, and
present any service API removal in the module design review. Map every hook
method in the active module, including existing OOP methods, to a kernel test
through Drupal's hook dispatch or a documented functional-test exception. The
plan contains the implementation and verification checklist.
