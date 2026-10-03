---
name: schemadotorg-oop-hook-review
description: Review the complete Schema.org Blueprints procedural-to-OOP hook conversion against 1.0.x. Use for read-only, module-by-module conversion audits and maintainer-readiness reports; do not use to implement fixes.
---

# Schema.org Blueprints OOP hook review

Perform a read-only review of the Schema.org Blueprints OOP hook conversion across the base module and every submodule. Compare all committed, staged, unstaged, and untracked work with `1.0.x`. Do not modify or stage files, create or alter tests, change commits or branches, or update issue or merge-request comments.

Use at least one sub-agent for a bounded, independent share of the module review. Partition modules explicitly so every module is covered once, then independently verify any high-risk finding before reporting it. Sub-agents must follow the same read-only constraint.

## Establish the review state

Run `ddev describe`. In the Schema.org checkout, record the branch and inspect:

```bash
git status --short
git diff --stat 1.0.x
git diff --name-status 1.0.x
git diff 1.0.x --
git diff --check
git diff --cached --check
git diff 1.0.x --check
git ls-files --others --exclude-standard
```

`git diff 1.0.x` does not include untracked files. Inspect every relevant untracked file separately and account for it in the report. Recheck status after verification commands so a supposedly read-only command cannot silently contaminate the review.

Review the base module as well as every directory under `modules/`. For every submodule, run the literal module-scoped comparison:

```bash
git diff 1.0.x -- modules/{module_name}
```

Always run and inspect this focused comparison explicitly:

```bash
git diff 1.0.x -- modules/schemadotorg_node
```

## Review each conversion

Read the pre-conversion implementation from `1.0.x` alongside the current hook classes, services, callbacks, and tests. Verify that:

- Every procedural runtime hook moved to the correct OOP hook method.
- Hook names, signatures, ordering, mutations, return values, side effects, and service calls are preserved.
- Logic was moved rather than condensed, rewritten, deduplicated unsafely, or unnecessarily reorganized.
- Inline and rationale comments, `@var` annotations, type-narrowing variables, and meaningful formatting remain where they explain behavior.
- Required procedural callbacks, install/update/uninstall hooks, schema or requirements hooks, and meta hooks remain procedural when Drupal requires them.
- Hook attributes, namespaces, class names, and responsibility groupings follow the project convention: plural `Hooks` suffixes, including `HooksBase`, and family-specific classes such as `FormHooks`, `EntityHooks`, `FieldHooks`, `JsonLdHooks`, and `ParagraphsHooks`.
- Callbacks formerly registered from a `.module` file are valid class callables after conversion.
- Ordering-sensitive hooks use the correct supported ordering mechanism and preserve the previous order.
- A `.module` file was removed only when no runtime hook, callback, loader, or required procedural implementation remains.
- The module service YAML contains exactly the module-specific `<module_machine_name>.skip_procedural_hook_scan: true` parameter when procedural scanning can safely be skipped.
- No test was deleted, renamed, disabled, ignored, weakened, or made undiscoverable without a necessary compatibility reason.
- No callers, service IDs, public APIs, dependencies, callbacks, autowiring, or runtime discovery behavior were broken.
- A manager or interface removed as temporary hook scaffolding has no genuine non-hook consumer; reusable services with cross-module or domain consumers remain services.

Search outside the module before concluding that a service, interface, callback, or method is unused. Treat lexical searches as evidence leads, not proof.

## Verification

Run existing focused kernel or functional tests where practical; do not create, edit, rename, or disable tests. Run `ddev code-review <module-path>` where practical. Record exact commands, pass/fail status, assertion counts, deprecations, and whether failures are introduced by the conversion or are existing/tooling issues.

Do not fix findings during the review. If a verification command changes the checkout, stop, identify the changed paths, and report that the read-only guarantee was violated rather than silently cleaning up or continuing.

## Report

Keep the report concise and evidence-based:

1. Overall conversion status.
2. Highest-risk findings first, with file and line references.
3. A short section for the base module and each submodule containing:
   - conversion status;
   - OOP hook classes added or changed;
   - `.module` and service-YAML status;
   - test-file status;
   - logic and comment preservation status;
   - potential breakage or follow-up work.
4. Verification commands and results.
5. Whether the conversion is ready for maintainer review.

For clean modules, one compact “pass, no meaningful inconsistency found” entry is enough. Do not produce an exhaustive change inventory. Report meaningful inconsistencies, risks, and concrete evidence that behavior, comments, discovery, APIs, or tests changed.
