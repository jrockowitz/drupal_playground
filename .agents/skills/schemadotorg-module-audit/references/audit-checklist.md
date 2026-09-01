# Module audit checklist

Use this checklist with the current findings and definitions in
`docs/schemadotorg-audit.md`. Record evidence or “not applicable”; do not infer
quality from file presence alone.

## Inventory and contract

- Confirm module status, core/PHP/contrib constraints, dependencies, package,
  configure route, and install/uninstall behavior.
- Identify services, hooks, plugins, events, alters, public classes, config,
  routes, permissions, libraries, JavaScript, CSS, README, and tests.
- Compare README claims and configuration instructions with implementation.

## Drupal behavior

- Prefer dependency injection; justify unavoidable service-locator use.
- Preserve API compatibility and document typed extension contracts.
- Validate route parameters and input; require least-privilege access and CSRF
  protection for state changes.
- Request entity/field access-result objects and preserve their cacheability.
- Use render arrays, safe markup, URL, translation, logging, entity, field,
  form, batch/queue, lock, and database APIs appropriately.
- Attach every applicable cache context, tag, dependency, and max-age.
- Check config schema, defaults, dependencies, update hooks, recipes, and
  uninstall/data-loss implications.
- Check Drupal 10.3/11 support, deprecations, PHP typing, and narrow static-
  analysis suppressions.
- Check behaviors, `once()`, detach handling, accessibility, progressive
  enhancement, CSP/privacy, and pinned/reproducible libraries.

## Verification

- Reproduce the behavior or state exactly what prevents reproduction.
- Prefer a regression test that fails before the fix and passes afterward.
- Cover denial, invalid/empty input, missing dependencies, multilingual and
  access/cache variation, and failure paths when relevant.
- Run targeted `ddev phpunit` and file-type-specific PHPCS, PHPStan, CSpell,
  ESLint, or Stylelint targets.
- Use the in-app browser for administrator/rendered behavior that automated
  tests cannot establish; never use Playwright in this project.
- Recheck module and parent working trees after every tool that may write files.

## Finding threshold

Create or select an issue only when evidence identifies a reproducible defect,
compatibility risk, public API problem, meaningful maintainability/test gap, or
specific documentation error. Do not create issues for subjective cleanup,
unclassified analyzer output, or behavior already covered by a current issue.
