# AI Schema.org JSON-LD Module Guide

`ai_schemadotorg_jsonld` adds a generated `field_schemadotorg_jsonld` field to supported
content entity bundles, configures AI automators to populate it, and injects saved or
default Schema.org JSON-LD into canonical entity pages.

The module is a glue layer between `ai`/`ai_automators`, `field_widget_actions`, `json_field`,
and Drupal entity, token, and page attachment APIs. Most changes should preserve that role
instead of adding unrelated business logic.

## Core Concepts

- **Supported entity type** — a fieldable content entity type with a canonical route, not excluded by the manager
- **Enabled entity type** — has an entry in `ai_schemadotorg_jsonld.settings:entity_types`
- **Generated field** — `field_schemadotorg_jsonld` plus its automator and display configuration
- **Bundle setup** — creating or reusing field storage, field config, automator config, form display, and view display
- **Default prompt** — entity-type-level prompt stored in config, used when creating bundle automators
- **Default JSON-LD** — entity-type-level static JSON-LD attached to canonical pages

When referencing the field name in PHP, use `AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME`
instead of hardcoding `field_schemadotorg_jsonld`.

## Architecture

### Manager — `AiSchemaDotOrgJsonLdManager`

Owns entity-type-level concerns:

- determining which entity types are supported
- initializing `entity_types.{entity_type_id}` settings
- building default prompts and reading curated prompt files from `prompts/entity_types/`
- syncing enabled entity types in module config

Use the manager when the concern is "is this entity type valid?" or "what defaults should exist?"

### Builder — `AiSchemaDotOrgJsonLdBuilder`

Owns bundle-level generated configuration:

- field storage and field instance on a bundle
- AI automator creation
- form and view display integration

Public entry points:

- `addFieldToBundles(string $entity_type_id, array $bundles): void`
- `addFieldToBundle(string $entity_type_id, string $bundle): void`

The builder is idempotent. Repeated calls reuse existing generated config instead of duplicating it.

### Token Resolver — `AiSchemaDotOrgJsonLdTokenResolver`

Renders entities for prompt input: switches to anonymous user and default theme, renders the entity,
removes outer wrapper markup, and converts root-relative URLs to absolute URLs.

### Runtime Output And AI Response Handling

- `Hook\AiSchemaDotOrgJsonLdPageHooks` — attaches default and saved field JSON-LD to canonical pages
- `EventSubscriber\AiSchemaDotOrgJsonLdEventSubscriber` — cleans prompt text before LLM submission; extracts and validates JSON from AI responses
- `Hook\AiSchemaDotOrgJsonLdTokenHooks` — exposes token integration for prompt building

Use these classes for request-time behavior, not the builder or manager.

## Primary Entry Points

All entry points should route through shared services.

- **Admin UI** — `Form\AiSchemaDotOrgJsonLdSettingsForm` (enable entity types, edit defaults, trigger bundle setup) and `Form\AiSchemaDotOrgJsonLdPromptForm` (edit prompts)
- **Drush** — `Drush\Commands\AiSchemaDotOrgJsonLdCommands::addField()` — thin wrapper around the builder
- **Recipes** — `Plugin\ConfigAction\AddField` — delegates to the builder; supports explicit bundle lists or `bundles: ['*']` for all current bundles
- **OOP Hooks** — `Hook\AiSchemaDotOrgJsonLdFieldHooks`, `Hook\AiSchemaDotOrgJsonLdPageHooks`, `Hook\AiSchemaDotOrgJsonLdTokenHooks`

## Configuration Model

Primary config object: `ai_schemadotorg_jsonld.settings`

Important keys:

- `entity_types.{entity_type_id}.default_prompt`
- `entity_types.{entity_type_id}.default_jsonld`
- `development.edit_prompt`

Key behaviors:

- `node` is configured by default
- enabling an entity type initializes its settings before bundle setup
- bundle setup does not assume entity-type settings already exist
- entity types without bundles resolve to a synthetic bundle internally

## Prompt And Token Approach

Prompt defaults come from curated files in `prompts/entity_types/`, with fallback generation in the manager.
Token usage affects prompt and AI output quality — keep changes deliberate.

## Runtime JSON-LD Output

Canonical pages receive up to three JSON-LD sources:

- breadcrumb JSON-LD from the optional breadcrumb submodule
- entity-type-level `default_jsonld`
- saved `field_schemadotorg_jsonld` field value

If a page `<head>` bug is suspected, start with page hooks and submodules before changing builder logic.

## Testing Guidance

Prefer Kernel tests. Focus on behavior:

- supported entity type detection
- default prompt initialization
- bundle setup through the builder (including wildcard and non-bundle entity types)
- idempotent repeated setup
- Drush command delegation
- recipe config action validation and delegation
- runtime JSON extraction and page attachment behavior

Avoid tests that depend on exact labels or markup unless that output is the feature itself.

## Submodules

- **`ai_schemadotorg_jsonld_breadcrumb`** — adds `BreadcrumbList` JSON-LD to canonical pages
- **`ai_schemadotorg_jsonld_log`** — records prompt/response history and adds log-related UI

When changing shared runtime behavior, check whether either submodule also needs adjustment.

## Guidelines

- Route all bundle setup through the builder — not ad hoc in forms, Drush, or hooks.
- Route all entity-type validation through the manager — do not bypass supported-type checks.
- Keep UI, Drush, and recipe behavior consistent by sharing services, not duplicating logic.
- Keep request-time output in hooks, token services, or event subscribers — not the builder or manager.
- Preserve idempotency for all generated config.
- Do not hardcode `field_schemadotorg_jsonld` when `FIELD_NAME` is available.
- Do not assume every supported entity type has bundles.
