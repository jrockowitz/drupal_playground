# Schema.org Blueprints OOP hook conversion plan

Convert issue #3622305 from the existing `1.0.x` checkout, one production module
and one reviewed commit at a time. Start with the base `schemadotorg` module,
then work through the `schemadotorg_*` submodules in the local issue ledger.
Use its suggested waves to choose the next submodule; change that order when a
dependency requires it, and record why. `schemadotorg_allowed_formats` is the
first suggested submodule. Keep Experimental in separate linked work.

## Before each module

1. Read the local [issue #3622305 note](../schemadotorg-issue-maintenance/issues/3622305.md),
   its module ledger, the [production hook checklist](../schemadotorg-issue-maintenance/issues/3622305-oop-hook-checklist.md),
   and the historical [Rector POC inventory](../schemadotorg-issue-maintenance/issues/3622305-oop-hook-rector-poc-inventory.md).
   Use the POC as a lead list, not as the current implementation or completion
   record. Check the current issue/fork/MR state.
   Follow `schemadotorg-issue-maintenance` and `drupalorg-issue-maintenance`
   for approval and public-write gates. Run `ddev describe` to identify the
   active project, docroot, and PHP environment.
2. Inspect both repository statuses, the nested checkout branch, upstream, and
   ahead/behind state. Do not reset an ahead branch or create a worktree. Obtain
   approval before creating an issue branch. If the nested checkout has
   uncommitted changes, stop at read-only analysis until the maintainer resolves
   the scope; never discard or include unrelated changes.
3. Run `.agents/skills/schemadotorg-oop-hook-conversion/scripts/inspect-hook-module.sh
   <base|submodule>`. Its output is a starting inventory, not proof of complete
   hook discovery or service usage: it scans top-level procedural files and
   limited reference patterns. Search nested includes, callbacks, service and
   config references, other modules, and cross-module tests separately.
4. Classify every relevant function as a runtime hook to convert, required
   procedural install/update/meta hook, named callback, helper, or API example.
   Trace each hook through delegated service methods and helpers, including
   hidden static service lookups; check all callers before changing a service
   boundary. Record current hook order and behavior, including implementations
   on behalf of another module. Populate or reconcile one production-checklist
   row per runtime hook, including existing OOP hooks and skipped POC hooks,
   and map each row to a behavioral test. Record required procedural functions
   and named callbacks separately.
5. Present the module scope, class/dependency design, service IDs and interfaces
   to preserve or remove, translation choice, ordering and procedural-scan
   decision, cross-module edits, files, per-hook test map, and any functional
   test exception. Identify each proposed public API removal explicitly for
   approval as part of this module's design review. Obtain the local-code
   approval required by the issue workflow before editing. Pause for
   maintainer direction on permissions, access, update hooks, generated
   configuration, or behavior that conflicts with existing tests.

## Implement one module

- Put method-level `#[Hook('hook_name')]` methods under
  `Drupal\MODULE\Hook`. Target Drupal 11.2+; use automatic registration and
  autowiring when possible, constructor injection, and no new static `\Drupal`
  lookups in hook classes. Do not retain Rector's `#[LegacyHook]` wrappers or
  explicit service YAML when automatic registration suffices.
- Put all `form_alter`, `form_FORM_ID_alter`, and `form_BASE_FORM_ID_alter` hooks
  in `ModuleNameFormHooks` (for example, `SchemaDotOrgFormHooks` or
  `SchemaDotOrgAllowedFormatsFormHooks`). Keep associated form handlers there
  when their callback registration supports class methods; preserve callable
  signatures and behavior. Group other related hooks by responsibility, as
  Drupal core does with `EntityHooks`, `TokensHooks`, `ThemeHooks`, and
  `ViewsHooks`. Use `ModuleNameHooks` for isolated hooks. Split by purpose and
  dependencies rather than method count.
- Move hook-specific behavior from delegated service methods and helpers into
  the hook class, not just the procedural wrapper. Inject the services used by
  that behavior, including dependencies hidden behind static helpers. Keep
  reusable behavior in retained managers/builders. Preserve public service IDs
  and interfaces unless their removal was explicitly approved in the module
  design review. Use `StringTranslationTrait` for hook-local strings or inject
  a translator when the design or tests require that boundary. A submodule
  conversion may include the smallest necessary dependency fix elsewhere;
  document its scope and tests in that module's commit.
- Replace the *effect* of `hook_module_implements_alter()` with Drupal 11.2
  ordering attributes (`Hook`, `ReorderHook`, and order objects) and an order
  regression test. That procedural meta hook itself is not convertible to an
  OOP hook. Verify final order alongside remaining procedural implementations.
  Cover the base module's focal-point widget sentinel and the node module's
  `local_tasks_alter` ordering when those modules are active.
- Retain core-required procedural install/update/meta hooks and named
  callbacks. Enable `MODULE.skip_procedural_hook_scan: true` only after checking
  all files Drupal may scan and proving no discoverable procedural runtime hook
  remains, including hooks implemented on behalf of another module. Treat
  `*.api.php` hook definitions as documentation, not implementations.

## Verify, review, and commit

1. Give each hook class in the active module, including classes that existed
   before conversion, a corresponding kernel test. Exercise every hook method
   through Drupal's hook dispatch and assert its observable behavior; a direct
   method call alone does not satisfy this rule. Extend a clearly corresponding
   existing kernel test instead of duplicating it. When a kernel test cannot
   verify a hook's behavior, record the reason and covering functional test in
   the ledger. Cover relevant form changes, by-reference arguments, and hook
   order.
2. Run `ddev drush cr`,
   `ddev phpunit <file|directory>`, `ddev code-review <file|directory>`, and
   `git -C web/modules/sandbox/schemadotorg diff --check`. Compare failures
   with the documented baseline and fix new regressions. Run the complete
   Schema.org suite for the base module, ordering changes, and before each
   main-project merge-request review.
3. Update the production hook checklist and local issue ledger with the final
   class/method, retained functions, per-hook test map and exceptions,
   cross-module edits, commands/results, baseline limitations, scan decision,
   and next gate. Show the complete diff and test results to the maintainer;
   every checklist row must be resolved before commit approval. Do not update
   the historical Rector POC inventory as a progress tracker.
4. After explicit commit approval, commit that module's coherent change before
   starting the next. Name the module in the subject, for example
   `schemadotorg: Convert runtime hooks to OOP` or
   `schemadotorg_allowed_formats: Convert runtime hooks to OOP`. Describe any
   cross-module dependency fix in the body. End every AI-authored commit message
   with `AI-assisted by Codex`. Record the commit, check the module's completion
   box in the checklist, and mark it complete in the issue ledger before starting
   the next. Obtain separate approval before push, MR, or public issue update.
   Tickets and comments begin with that AI-assisted note.
