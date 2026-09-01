# Schema.org Blueprints phased audit

**Audit date:** 2026-08-10  
**Target:** `web/modules/sandbox/schemadotorg`, branch `1.0.x`  
**Compatibility claim:** Drupal `^10.3 || ^11`  
**Pilot depth:** base module, Schema.org Additional Type, and Schema.org JSON-LD  
**Scope:** recommendations only; no module code, Drupal.org issue, or public comment was changed

## Executive summary

The pilot found no confirmed P0 issue. It found four high-confidence P1 correctness or access-cache risks, with the two JSON-LD cache findings deserving the first remediation pass:

1. JSON-LD emits the unrecognized keyword `@url` instead of either the Schema.org property `url` or the JSON-LD keyword `@id`.
2. JSON-LD writes page cache metadata to `#cache.context` (singular), so its calculated cache contexts do not bubble through the render API's `#cache.contexts` key.
3. JSON-LD converts entity and field access checks to booleans and discards the access results' cacheability metadata.
4. Additional Type returns cacheable field access results without attaching the contexts and dependencies used to make the decision.

The pilot also confirmed documentation drift: the top-level README contains invalid installation JSON, identifies the wrong Composer package, recommends enabling every submodule, and links three times to a nonexistent `docs/MODULES.md`. All 51 submodules have a README and at least one test, but none has a testing section and the depth and accuracy of the READMEs vary substantially.

The module checkout was clean before and after the audit. The report was added in the parent repository, outside the sandbox checkout. This is a focused pilot, not a complete security review of all 51 submodules.

## Method and priorities

Each finding records priority, confidence, evidence, impact, remediation, verification, and a suggested issue boundary.

- **P0:** security, data loss, or critical access defect.
- **P1:** functional correctness, broken compatibility, or public API risk.
- **P2:** maintainability, test coverage, performance, or developer-experience concern.
- **P3:** documentation, consistency, and minor cleanup.

The audit combined source and metadata review, tool-specific lint/static-analysis runs, PHPUnit, and targeted browser checks. A successful check means only that the selected tool and scope passed; it is not proof that the behavior is defect-free.

## Findings overview

| ID | Priority | Confidence | Area | Summary |
|---|---|---:|---|---|
| AUD-001 | P1 | High | JSON-LD | Replace invalid `@url` output and its test expectations. |
| AUD-002 | P1 | High | Cache API | Correct singular `#cache.context` to `#cache.contexts`. |
| AUD-003 | P1 | High | Access/cache | Preserve entity and field access-result cacheability in JSON-LD. |
| AUD-004 | P1 | High | Access/cache | Make Additional Type field access results cacheable by their inputs. |
| AUD-005 | P2 | Medium | Access | Check referenced-entity view access in the Additional Type report. |
| AUD-006 | P2 | High | Input validation | Constrain autocomplete route parameters and handle invalid input. |
| AUD-007 | P2 | High | Dependencies | Reassess runtime JavaScript loaded directly from public CDNs. |
| AUD-008 | P2 | High | Static analysis | Triage 130 PHPStan findings and replace broad suppressions. |
| AUD-009 | P2 | High | Tests | Resolve the missing test dependency and reduce deprecation debt. |
| AUD-010 | P2 | High | README | Replace broken and risky installation/onboarding guidance. |
| AUD-011 | P2 | High | Documentation | Restore or replace the missing module catalog and reorganize docs. |
| AUD-012 | P3 | High | API docs | Correct hook examples and complete useful example coverage. |

## Detailed findings

### AUD-001 — JSON-LD emits `@url`

- **Priority / confidence:** P1 / High.
- **Evidence:** `modules/schemadotorg_jsonld/src/SchemaDotOrgJsonLdBuilder.php:227-230` writes `$default_data['@url']`; the referenced-entity path does the same. Tests explicitly assert `@url`. JSON-LD 1.1 defines its keywords, including `@id` and `@type`, but not `@url`.
- **Impact:** Consumers may ignore the property as an unrecognized keyword, preventing the canonical URL from identifying or describing the entity as intended.
- **Recommendation:** Decide the intended semantics. Use `@id` when the canonical URL identifies the JSON-LD node; use Schema.org `url` when it is a property. Update every construction path, fixtures, and tests together.
- **Verification:** Parse representative output with a conforming JSON-LD processor and confirm expansion retains the URL. Add unit and rendered-page regression tests for top-level and referenced entities.
- **Suggested issue:** One JSON-LD correctness issue, kept separate from cache work.

### AUD-002 — JSON-LD page cache context key is singular

- **Priority / confidence:** P1 / High.
- **Evidence:** `modules/schemadotorg_jsonld/schemadotorg_jsonld.module:89` writes and reads `$page['#cache']['context']`. Drupal render arrays use `$build['#cache']['contexts']`. The adjacent cache-tags implementation uses the correct plural key.
- **Impact:** Contexts accumulated while building JSON-LD may not bubble to the page render cache, allowing one cache variation to reuse structured data produced for another variation.
- **Recommendation:** Change both occurrences to `contexts`, preserving existing contexts through `Cache::mergeContexts()`.
- **Verification:** Add a functional test that varies a mapped value or access decision by a context not already present on the page, then asserts both cache headers/metadata and rendered JSON-LD across users.
- **Suggested issue:** A narrowly scoped cache correctness issue with a regression test.

### AUD-003 — JSON-LD discards access-result cacheability

- **Priority / confidence:** P1 / High.
- **Evidence:** `SchemaDotOrgJsonLdBuilder.php:128`, `:199-200`, and the referenced-entity path call `access('view')` as a boolean. The resulting `AccessResultInterface` is therefore unavailable to `BubbleableMetadata`. Static entity contexts include `user.permissions` and `route`, but do not represent every possible node-grant, field, bundle, or custom access dependency.
- **Impact:** Correct access may be calculated for the first request but cached without every dependency that made the decision. This can create stale or inappropriate structured-data variants even when visible markup is correctly protected.
- **Recommendation:** Call access with `$return_as_object = TRUE`, add each result as a cacheable dependency, and use `isAllowed()` for the decision. Apply consistently to root entities, fields, referenced entities, and URL generation.
- **Verification:** Add grant-aware and field-access test modules whose results vary by user/context/config; assert both exclusion and the bubbled contexts/tags/max-age.
- **Suggested issue:** One cross-cutting JSON-LD access-cache issue; do not split individual call sites.

### AUD-004 — Additional Type field-access decisions lack cache metadata

- **Priority / confidence:** P1 / High.
- **Evidence:** `modules/schemadotorg_additional_type/src/SchemaDotOrgAdditionalTypeFieldAccessHandler.php:61-69` returns `AccessResult::neutral()` or `AccessResult::forbiddenIf()` without cache contexts or dependencies. The rule evaluation can depend on account permissions/roles, entity access and bundle, Schema.org type, and module configuration.
- **Impact:** Drupal can reuse a cached field-access decision outside the conditions under which it was calculated.
- **Recommendation:** Build an access result that carries the relevant account contexts and adds the rule config, entity, and nested access results as cacheable dependencies. Avoid a blanket `cachePerUser()` if narrower contexts are sufficient.
- **Verification:** Add kernel tests that evaluate the same field for two roles, permissions, bundles, and rule configurations and assert both decisions and cacheability metadata.
- **Suggested issue:** One Additional Type access/cache issue.

### AUD-005 — Additional Type report links referenced entities without view access

- **Priority / confidence:** P2 / Medium.
- **Evidence:** `modules/schemadotorg_additional_type/src/Controller/SchemaDotOrgAdditionalTypeReportController.php` loads configured target entities and renders labels/canonical links without checking each target entity's `view` access. Route access is the broad `access site reports` permission.
- **Impact:** A report user may learn the label and identifier of an entity they cannot view. The practical sensitivity depends on supported field targets and site permissions.
- **Recommendation:** Obtain an access-result object for every target, omit or redact inaccessible targets, and add its cacheability to the render array. Confirm whether the report permission intentionally overrides entity access and document that decision if so.
- **Verification:** Functional test with a report-authorized user who cannot view one referenced entity.
- **Suggested issue:** Separate report access-hardening issue after confirming expected product behavior.

### AUD-006 — Autocomplete route accepts unconstrained table and entity type values

- **Priority / confidence:** P2 / High.
- **Evidence:** The public autocomplete route requires `access content` but does not constrain `{table}` or `{entity_type_id}`. `SchemaDotOrgAutocompleteController.php:68-75` concatenates `table` into a known prefix for a database table; arbitrary entity type IDs are passed into bundle discovery. Drupal's database API quotes identifiers, so SQL injection was not demonstrated, but invalid values can produce exceptions and 500 responses.
- **Impact:** Malformed requests can trigger avoidable errors, log noise, and fragile public behavior.
- **Recommendation:** Constrain `table` to the explicit supported values, validate that the entity type exists and is bundleable where required, cap/normalize query input, and return a controlled 400 or empty result for invalid combinations.
- **Verification:** Functional tests for valid values, unknown tables, unknown entity types, empty/oversized input, and characters significant to SQL `LIKE`.
- **Suggested issue:** One route/input-hardening issue.

### AUD-007 — Browser libraries are fetched from third-party CDNs at runtime

- **Priority / confidence:** P2 / High.
- **Evidence:** `schemadotorg.libraries.yml` references cdnjs and jsDelivr assets for jsTree, Mermaid, Panzoom, and CodeMirror. `docs/DECISIONS.md` records external CDN use as an intentional choice. No local fallback or integrity metadata was observed. By contrast, `composer.libraries.json` already packages Masonry locally.
- **Impact:** Admin/report functionality can depend on third-party availability and site CSP, while client requests disclose network metadata. Reproducibility and supply-chain governance are harder for downstream sites.
- **Recommendation:** Prefer version-pinned local assets installed through the project's dependency strategy. If remote assets remain, document CSP/privacy/offline implications, integrity and fallback policy, and who owns version/security updates.
- **Verification:** Exercise affected pages under a restrictive `script-src 'self'` policy and without network access; run the relevant JS tests with the packaged assets.
- **Suggested issue:** One architectural dependency issue, with follow-ups per library only if migrations differ materially.

### AUD-008 — PHPStan baseline mixes real debt with Drupal dynamic typing

- **Priority / confidence:** P2 / High.
- **Evidence:** A full run using the workspace level-6 configuration reported 130 errors: 80 `property.notFound`, 21 `missingType.generics`, 10 `method.notFound`, 9 `function.deprecated`, 7 `ignore.unmatchedLine`, 2 `method.deprecated`, and 1 `assign.propertyType`. The module's own PHPStan config broadly ignores function and method deprecations, while the workspace run exposes calls such as `filter_formats()`, `node_is_page()`, `check_markup()`, `FileSystemInterface::basename()`, and `Request::get()`.
- **Impact:** Genuine Drupal 11/forward-compatibility defects are obscured by dynamic-property false positives and broad ignores; stale ignores make the baseline less trustworthy.
- **Recommendation:** Classify each result. Add precise types/stubs for Drupal dynamic properties, fix current deprecations, remove unmatched ignores, and replace identifier-wide suppressions with narrow, explained entries. Raise strictness incrementally only after the signal is useful.
- **Verification:** CI should run the same committed configuration and fail on newly introduced errors. Track totals by identifier during cleanup.
- **Suggested issue:** One baseline/configuration issue plus small, API-focused deprecation issues.

### AUD-009 — Pilot tests pass except for an unavailable development dependency

- **Priority / confidence:** P2 / High.
- **Evidence:** Base tests ran 87 tests/1,376 assertions with one bootstrap error because `media_library_media_modify` is unavailable; the package is declared in development requirements but is absent from this workspace. Additional Type passed 9 tests/350 assertions. JSON-LD passed 13 tests/162 assertions. The runs also reported 75/48/57 Drupal deprecations and 186/18/17 PHPUnit deprecations respectively.
- **Impact:** The missing dependency prevents a clean contributor baseline, and deprecation volume makes regressions harder to notice. Existing JSON-LD tests encode `@url` and do not cover node-grant/access cache variation.
- **Recommendation:** Document and automate complete development dependency installation; distinguish unavailable-environment failures from product failures in CI. Reduce deprecations, and add meaningful denial, invalid-input, grant, and cache-variation paths rather than only increasing test counts.
- **Verification:** Repeat the three suites on supported Drupal 10.3 and current Drupal 11 after dependencies are resolved, with deprecation output retained as an artifact or budgeted gate.
- **Suggested issue:** One test-environment issue and focused coverage issues attached to behavioral fixes.

### AUD-010 — Top-level README onboarding is not safely executable

- **Priority / confidence:** P2 / High.
- **Evidence:** The installation snippet at `README.md:117-139` is invalid JSON because of trailing commas and requires the nonexistent/wrong package `schemadotorg/schemadotorg/` instead of `drupal/schemadotorg`. It relies on Composer Merge Plugin instructions, while the repository's dependency strategy needs explicit current guidance. The README also recommends a grep/xargs command that enables every matching submodule and describes APIs/test coverage with absolute terms such as “pristine” and “every sub-module includes extensive test coverage.”
- **Impact:** New users can copy a failing Composer example or enable experimental/optional integrations whose dependencies and effects they have not reviewed.
- **Recommendation:** Rewrite onboarding around a minimal supported installation, a small recommended module set, optional recipes/integrations, configuration, verification, rollback/troubleshooting, support/security, and deeper docs. Use valid tested commands and qualify claims.
- **Verification:** Test every command from a clean recommended-project install on both supported core lines; have a site builder follow only the README.
- **Suggested issue:** One site-builder onboarding issue; dependency architecture may need a separate issue.

### AUD-011 — Documentation hierarchy and module catalog have drifted

- **Priority / confidence:** P2 / High.
- **Evidence:** `README.md` links to nonexistent `docs/MODULES.md` three times. The approved plan expected six tracked docs, but current `1.0.x` HEAD (`9b1682fa`) removed `docs/TESTING.md`; only five files exist. `DEVELOPMENT.md` is primarily destructive alpha-update instructions, `UPDATE.md` is a maintainer release checklist with version-specific/local values, and `DECISIONS.md` mixes current policy, history, stale Drupal links, retired components, and malformed links.
- **Impact:** Site builders cannot discover module choices, contributors cannot find a current test workflow, and historical decisions can be mistaken for supported policy.
- **Recommendation:** Add a maintained module catalog or remove all references; add current contributor testing guidance; separate site-builder operations from maintainer/release architecture; convert durable decisions into dated ADRs and retire stale notes with redirects where useful.
- **Verification:** Automated internal-link check, command smoke tests, metadata-to-catalog consistency check, and audience review by one site builder and one contributor.
- **Suggested issue:** One documentation information-architecture issue with independently reviewable follow-ups.

### AUD-012 — API examples contain defects and placeholders

- **Priority / confidence:** P3 / High.
- **Evidence:** `schemadotorg.api.php` includes an `endData` typo where `endDate` is expected and a Smart Date condition whose `|| moduleExists('smart_date')` behavior appears broader than its accompanying intent. Several hooks retain “Provide example code” placeholders.
- **Impact:** Consumers can copy an incorrect extension example or misunderstand hook contracts.
- **Recommendation:** Correct and test examples, state mutability/cacheability expectations, and add examples only where they clarify a non-obvious contract. Remove empty boilerplate examples rather than padding the API file.
- **Verification:** Exercise code snippets in a lightweight test fixture or static example test.
- **Suggested issue:** One public API documentation cleanup issue.

## Tool and runtime baseline

Environment observed with `ddev describe`: DDEV project `drupal-schemadotorg`, Drupal 11, PHP 8.3, docroot `web`, site URL `https://drupal-schemadotorg.ddev.site`.

| Check | Pilot target/result | Interpretation |
|---|---|---|
| PHPCS | Base, Additional Type, JSON-LD: pass | No coding-standard failure in the scoped PHP targets. |
| ESLint | Base and pilot-module JS: pass | No scoped ESLint failure. |
| Stylelint | Six CSS sources: pass | Tool-specific CSS result; PHP/YAML/Markdown were not misclassified as CSS. |
| CSpell | 57 README/docs files: pass with a custom-config migration warning | Spelling passed; update configuration for the current CSpell default-word migration. |
| PHPStan | Full checkout: 130 errors; pilot subset: 63 | Requires classification, not a blanket “130 defects” claim. |
| Base PHPUnit | 87 tests, 1,376 assertions, 1 dependency/bootstrap error | `media_library_media_modify` unavailable locally; 75 Drupal and 186 PHPUnit deprecations. |
| Additional Type PHPUnit | 9 tests, 350 assertions, pass | 48 Drupal and 18 PHPUnit deprecations. |
| JSON-LD PHPUnit | 13 tests, 162 assertions, pass | 57 Drupal and 17 PHPUnit deprecations; tests currently bless `@url`. |
| Browser smoke check | Admin settings loaded; front page contained one valid WebSite JSON-LD block | No mapped content existed for a rendered `@url` check; code/tests provide that evidence. |

The all-purpose `ddev code-review <directory>` wrapper passed the same directory to Stylelint, causing PHP, YAML, Markdown, and binary files to be reported as CSS. Those 2,466 messages are tooling noise and are not code findings. Use file-type-specific targets, or change the wrapper to select extensions per tool.

## Drupal best-practice checklist for later phases

Apply this checklist to each remaining submodule and record evidence, including explicit “not applicable” results:

- Services are injected; unavoidable static service-locator use is isolated and documented.
- Plugins, hooks, events, alters, and public APIs have typed contracts, examples, and backward-compatibility consideration.
- Routes have least-privilege permissions, parameter constraints/converters, CSRF protection for state changes, and controlled invalid-input behavior.
- Entity, field, and referenced-entity access is checked with access-result objects whose cacheability is retained.
- Output uses render arrays and safe markup APIs; URLs, query values, JSON, and logs are escaped/validated for their target context.
- Cache contexts, tags, and max-age represent all configuration, access, language, route, entity, and request dependencies.
- Configuration has complete schema, sensible defaults, valid dependencies, uninstall behavior, and update paths; recipes are repeatable and declare prerequisites.
- `.info.yml`, Composer constraints, and CI agree on Drupal/PHP/contrib support; deprecated APIs are fixed before their removal version.
- Entity, field, form, URL, translation, logging, batch, queue, lock, temp-store, and database APIs are used instead of hand-rolled substitutes.
- JavaScript uses Drupal behaviors and `once()`, detaches safely, supports keyboard/screen-reader use, and degrades progressively.
- Libraries are pinned, locally reproducible where practical, license-aware, and compatible with CSP/privacy requirements.
- Tests cover denied access, invalid input, empty/missing dependencies, multilingual/cache variation, install/uninstall/update, and failure paths.
- Static-analysis ignores are narrow, documented, and matched; fixture/test-only suppressions do not leak into production coverage.

## Documentation review

### Recommended hierarchy

| Audience | Document | Recommendation |
|---|---|---|
| Site builder | Top-level `README.md` | Rewrite as concise first-run guide and index. |
| Site builder | `docs/MODULES.md` | Restore as a generated or verified catalog, or remove every link and replace it with another stable catalog. |
| Site builder | `docs/CUSTOM_SCHEMAS.md` | Keep; add prerequisites, validation, rollback/safety, and a tested example. |
| Site builder | New `docs/UPGRADING.md` | Replace the useful portion of `DEVELOPMENT.md`; remove alpha-only/destructive assumptions. |
| Contributor | New `docs/CONTRIBUTING.md` or restored `docs/TESTING.md` | Document DDEV setup, scoped lint/static checks, fixtures, test suites, and expected dependency installation. |
| Maintainer | `docs/UPDATE.md` | Rename to `RELEASING.md`; parameterize Schema.org versions and local URLs, and state prerequisites. |
| Maintainer/architect | `docs/DECISIONS.md` | Split current policy from history; use dated ADRs for decisions still governing implementation. |
| All | `docs/ROADMAP.md` | Keep concise, date/scope entries, and link each item to its authoritative issue. |

### Top-level README structure

1. Purpose and intended audience.
2. Supported Drupal/PHP versions and stability expectations.
3. Minimal Composer installation and enabling the base module.
4. Recommended combinations: JSON-LD, common authoring integrations, and optional recipes.
5. First configuration workflow.
6. Verification: mapping page, rendered JSON-LD, cache rebuild, and validation tool.
7. Troubleshooting and safe rollback/uninstall caveats.
8. Security reporting, support, issue queue, and contribution/testing links.
9. Module catalog and deeper documentation.

### Submodule README template

Keep simple integrations short. Include only sections that add information:

1. One-sentence purpose and user outcome.
2. Status/support (production, experimental, or internal) and compatibility.
3. Requirements, including contrib modules and whether they are optional.
4. Installation/enabling command when it differs from normal Drupal practice.
5. Configuration location and defaults, or “No configuration.”
6. How to verify the integration.
7. Important access, cache, privacy, uninstall, or dependency caveats.
8. Relevant tests and deeper links for contributors.

Avoid repeating the project overview, generic Composer instructions, or identical contribution prose in every README.

## 51-submodule documentation matrix

“Tests” is the number of discovered test classes/files in the inventory pass, not a coverage score. “Cfg” indicates discovered configuration files; it does not prove schema completeness.

| Submodule | Prod. | Tests | Routes/permissions/Cfg | README audit and recommended action |
|---|:---:|---:|---|---|
| `additional_mappings` | Yes | 5 | — / — / Yes | Add requirements, verification, tests, and config-schema/default behavior. |
| `additional_type` | Yes | 9 | Yes / — / Yes | Document report access/cache behavior, rule examples, verification, and tests. |
| `address` | Yes | 2 | — / — / Yes | Add verification/tests and state supported Address versions and mapping behavior. |
| `allowed_formats` | No | 2 | — / — / Yes | Mark support status clearly; add verification/tests and deprecation roadmap. |
| `auto_entitylabel` | No | 2 | — / — / Yes | Mark support status; document automatic-label side effects and verification. |
| `block_content` | Yes | 3 | — / — / Yes | Add explicit requirements, configuration, verification, and test pointers. |
| `cer` | No | 5 | — / — / Yes | Document patch/dependency assumptions, status, verification, and tests. |
| `content_model_documentation` | No | 4 | — / — / Yes | Mark support status; add output/permission caveats, verification, and tests. |
| `content_moderation` | No | 2 | — / — / Yes | Clarify moderation-state behavior, status, failure paths, and tests. |
| `custom_field` | Yes | 6 | — / — / Yes | Document supported versions/property mapping limits, verification, and tests. |
| `descriptions` | Yes | 4 | — / — / Yes | Add requirements, where descriptions appear, sanitization expectations, and tests. |
| `diagram` | No | 3 | Yes / Yes / Yes | Document CDN/CSP/access implications, route verification, and tests. |
| `entity_reference_override` | Yes | 4 | — / — / Yes | Explain precedence/override effects and add verification/test guidance. |
| `epp` | Yes | 3 | — / — / Yes | Explain supported editing flows, access behavior, verification, and tests. |
| `existing_values_autocomplete_widget` | No | 2 | — / — / Yes | Mark status; document data exposure/access and input/performance considerations. |
| `export` | No | 1 | Yes / — / No | Add permission/access rationale, output format, sensitive-data caveat, and tests. |
| `field_group` | No | 2 | — / — / Yes | Mark status and supported layouts; add verification and tests. |
| `field_prefix` | No | 2 | — / — / Yes | Add requirements, concrete before/after example, verification, and tests. |
| `field_validation` | No | 2 | — / — / Yes | Mark status; document validation failure behavior and test coverage. |
| `geolocation` | Yes | 3 | — / — / No | State “No configuration,” privacy/geocoding assumptions, verification, and tests. |
| `help` | No | 1 | Yes / — / No | Explain audience/status and route/access behavior; avoid duplicating main docs. |
| `inline_entity_form` | No | 2 | — / — / Yes | Mark status; document nested-form/cardinality behavior and tests. |
| `jsonapi` | No | 4 | Yes / — / Yes | Document API exposure/access/cache behavior, example request, and tests. |
| `jsonapi_preview` | No | 3 | Yes / Yes / Yes | Add permissions, preview security/caching, failure paths, and tests. |
| `jsonld` | Yes | 8 | Yes / — / Yes | Add cache/access semantics, validation workflow, extension APIs, and tests. |
| `jsonld_breadcrumb` | Yes | 1 | — / — / No | Keep concise; state prerequisites/no configuration and add verification/test pointer. |
| `jsonld_custom` | Yes | 4 | — / — / Yes | Add requirements, trust/escaping rules for custom JSON-LD, verification, and tests. |
| `jsonld_embed` | Yes | 1 | — / — / No | State prerequisites/no configuration, embedding rules, verification, and tests. |
| `jsonld_endpoint` | No | 3 | Yes / — / Yes | Document endpoint access, cache/content type, status, examples, and tests. |
| `jsonld_preview` | No | 5 | Yes / Yes / Yes | Document permissions, unpublished data exposure, caching, and tests. |
| `layout_paragraphs` | Yes | 5 | — / — / Yes | State supported versions/layout limits; add verification and tests. |
| `mapping_set` | No | 4 | Yes / — / Yes | Explain lifecycle/import effects, access, rollback, verification, and tests. |
| `media` | No | 4 | — / — / Yes | Add explicit requirements/status, media access behavior, verification, and tests. |
| `mercury_editor` | Unset | 1 | — / — / No | Set status metadata; document version matrix, no-config behavior, and tests. |
| `metatag` | No | 2 | — / — / Yes | Clarify ownership/precedence between Metatag and JSON-LD; add tests/verification. |
| `node` | Yes | 3 | — / — / Yes | Add requirements, configuration/default behavior, verification, and tests. |
| `office_hours` | Yes | 2 | — / — / No | State no configuration, timezone/format semantics, verification, and tests. |
| `options` | No | 3 | — / — / Yes | Mark status; document allowed-value synchronization and update behavior. |
| `paragraphs` | Yes | 3 | — / — / Yes | Explain nested entity access/cache behavior, supported versions, and tests. |
| `pathauto` | Yes | 3 | Yes / — / Yes | Document route/config side effects, token/pattern precedence, and tests. |
| `physical` | Yes | 2 | — / — / No | State no configuration, unit/value semantics, verification, and tests. |
| `recipe` | No | 6 | Yes / — / No | Separate recipe authoring from use; document prerequisites, repeatability, and rollback. |
| `report` | No | 5 | Yes / — / Yes | Document every report permission/access boundary, cache behavior, and tests. |
| `role` | Yes | 5 | — / — / Yes | Fix TOC/section mismatch; add requirements, permission effects, and tests. |
| `scheduler` | Yes | 3 | — / — / Yes | Document publish/unpublish JSON-LD/cache timing, cron assumptions, and tests. |
| `simple_sitemap` | No | 1 | — / — / No | Add configuration/no-config statement, supported versions, verification, and tests. |
| `smart_date` | Yes | 2 | — / — / No | Clarify recurrence/timezone/end-date semantics, compatibility, and tests. |
| `taxonomy` | Yes | 6 | — / — / Yes | Replace malformed requirements wording; explain vocabulary/mapping effects and tests. |
| `translation` | Yes | 3 | — / — / Yes | Document language negotiation, fallback/cache contexts, verification, and tests. |
| `type_tray` | No | 2 | — / — / Yes | Mark status, document patch/version assumptions, admin UX effects, and tests. |
| `ui` | No | 5 | Yes / — / No | State audience/status, route/access model, no-config behavior, and test guidance. |

Cross-cutting matrix conclusions:

- 51 of 51 submodules have a README and at least one discovered test.
- 0 of 51 READMEs have a dedicated testing section.
- Several READMEs omit requirements or configuration even when metadata/config exists; others repeat generic prose without a concrete verification step.
- The `production` flag is not a sufficient user-facing support statement, and it is unset for `mercury_editor`.
- README remediation should be generated only for factual inventory fields; purpose, caveats, and verification require human review against behavior.

## Recommended phased issue grouping

1. **Pilot correctness:** AUD-001 and AUD-002 as separate changes with regression tests.
2. **Pilot access/cache:** AUD-003 and AUD-004; assess AUD-005 privately before describing any exposure publicly.
3. **Public input hardening:** AUD-006.
4. **Developer baseline:** AUD-008 and AUD-009, including reproducible development dependencies.
5. **Site-builder docs:** AUD-010, missing module catalog, and the first README batch.
6. **Architecture/dependencies:** AUD-007 and maintainer decision records.
7. **Remaining submodules:** review in coherent integration families (JSON-LD/API, entity authoring, fields/widgets, editorial workflow, reports/UI, recipes) rather than one giant issue.

No issue should combine a functional code change with a broad documentation rewrite. Security-sensitive details should be validated and routed through Drupal's private security process before a public draft.

## Skill recommendation

The reusable workflow has now been created as
[`schemadotorg-module-audit`](../.agents/skills/schemadotorg-module-audit/SKILL.md)
for the remaining submodule phases and future release audits. It advances one
coherent, evidence-backed finding per run and does not force an issue for every
module.

The skill complements `schemadotorg-issue-maintenance` rather than duplicating issue-queue workflows. It contains:

- safe inventory commands for modules, metadata, routes, permissions, config/schema, libraries, docs, and tests;
- the Drupal checklist above and priority/confidence definitions;
- an evidence-first finding and public-issue template;
- tool-specific DDEV targets that avoid the Stylelint wrapper problem;
- bounded pilot test selection and clean-checkout verification;
- explicit stop points before code changes, public issue drafts, comments, commits, or pushes.

Keep issue discovery, Drupal.org CLI usage, local issue notes, and merge-request handling in the existing issue-maintenance skills. The new skill orchestrates those workflows and adds audit-specific selection, verification, and approval gates without duplicating them.

## References

- [JSON-LD 1.1 Recommendation](https://www.w3.org/TR/json-ld11/)
- [Drupal render-array cacheability](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
- [Drupal cache contexts](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-contexts)
- [Drupal Renderer API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Renderer.php/function/Renderer%3A%3Arender/11.x)

## Audit integrity

- Sandbox module checkout at completion: clean, branch `1.0.x`, HEAD `9b1682fa`.
- Module source/config/docs changed by this audit: none.
- Drupal.org issues/comments created: none.
- Commits/pushes created: none.
- Report location: parent repository `docs/schemadotorg-audit.md`.
