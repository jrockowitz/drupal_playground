---
name: schemadotorg-oop-hook-conversion
description: Convert Schema.org Blueprints base module or submodule from procedural runtime hooks to Drupal 11.2+ object-oriented hooks for issue #3622305.
---

# Schema.org OOP hook conversion

Convert requested modules  and track completed modules in the
[issue note](../../schemadotorg-issue-maintenance/issues/3622305.md). Work in the
existing Schema.org checkout; do not create a worktree. Review the complete
module diff and verification results before starting another module.

## Inspect the module

1. Run `ddev describe`, then inspect the root and nested checkout status,
   current branch, upstream divergence, and recent conversion commits. Preserve
   unrelated changes.
2. Search the module's `*.module`, `*.inc`, `*.install`, `src`, service YAML,
   and tests. Inventory runtime hooks, install/update/meta hooks, named
   callbacks, helpers, services, interfaces, callers, and hook ordering. Search
   other modules for cross-module callers before changing a service or public
   API.
3. Compare the procedural hook, delegated service method, and existing tests
   line by line. Decide which behavior belongs in the hook class, which
   reusable behavior stays in a service, and which functions must remain
   procedural.

Use `rg` and `find` directly for discovery. Treat lexical matches as review
leads, not proof that a service is unused or a conversion is complete.

## Convert the hooks

- Add method-level `#[Hook('hook_name')]` methods under
  `Drupal\MODULE\Hook`. Rely on automatic hook registration and autowiring;
  inject dependencies through the constructor and introduce no new static
  `\Drupal` service lookups.

- Group hooks by responsibility. Put form alters and their class-callable
  handlers in `ModuleNameFormHooks`; JSON-LD hooks in
  `ModuleNameJsonLdHooks`; and help, node, field, entity, page, module,
  local-task, or similar hooks in corresponding responsibility classes. Use
  `ModuleNameHooks` for the module's own isolated hooks and
  `ModuleNameContribHooks` for hooks implemented on behalf of optional
  contributed modules.

- Move hook-specific orchestration out of pass-through services into OOP hook classes.
  Consider moving reusable behavior into a base hook class.

- Preserve hook signatures, ordering, mutations, return values, inline
  rationale comments, line breaks, `@var` annotations, storage declarations,
  and type-narrowing variables. Use concise `Implements hook_name().` method
  docblocks and omit redundant constructor docblocks.

- Replace the effect of `hook_module_implements_alter()` with supported hook
  ordering attributes such as `order`, `ReorderHook`, or `RemoveHook`; do not
  convert that procedural meta hook itself. Verify ordering-sensitive behavior,
  including focal-point and local-task behavior where applicable.

- Retain core-required install, update, uninstall, requirements, schema, and
  meta hooks. Retain named callbacks unless their registration safely supports
  a class callable, and preserve their callable signatures.

- Remove converted procedural wrappers and `LegacyHook` attributes. Delete an
  empty `*.module` file only when no runtime code, callback, or explicit loader
  still requires it.

- Set `MODULE.skip_procedural_hook_scan: true` only after checking every file
  Drupal may scan and confirming that no discoverable procedural runtime hook
  remains. `*.api.php` definitions are documentation, not implementations.

## Verify and finish

1. Rebuild caches with `ddev drush cr` and run the module's existing focused
   kernel and functional tests with `ddev phpunit`. Do not add or expand tests
   solely because code moved into hook classes; change an existing test only
   when it directly invokes a removed procedural function, preserving its
   behavior assertions.
2. Run `ddev code-review <module-path>` and
   `git -C web/modules/sandbox/schemadotorg diff --check`. Run broader tests for
   shared behavior, ordering changes, the base module, and final merge-request
   review.
3. Review the full diff for behavior changes, missed callers, public API
   changes, and retained procedural functions. A module is complete only when
   every runtime hook and ordering requirement is resolved and verification
   passes.
4. After maintainer review, check the module in the issue note. Require explicit
   maintainer approval before committing or pushing code, changing the merge
   request, or posting an issue comment. Use
   `Issue #3622305: Convert <module_machine_name> hooks to OOP` for a module
   commit and end AI-authored commits with `AI-assisted by Codex`. Begin
   AI-authored tickets and comments with the same note.
