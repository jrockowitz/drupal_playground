# AI Schema.org JSON-LD

`ai_schemadotorg_jsonld` is a glue module that combines
[AI Automators](https://project.pages.drupalcode.org/ai/1.3.x/modules/ai_automators/),
[Field Widget Actions](https://www.drupal.org/project/field_widget_actions), and
[JSON Field](https://www.drupal.org/project/json_field) to add a
`field_schemadotorg_jsonld` field to supported content entity bundles.

The field is populated by an LLM using an entity-type-specific prompt. The module then injects the
saved JSON-LD into canonical entity pages as `<script type="application/ld+json">` tags in the
page header.

Supported entity types must be:

- content entities
- fieldable
- bundleable
- canonical

Examples include `node`, `media`, `taxonomy_term`, and `block_content`.

Canonical non-bundleable content entities such as `user` are not supported yet.

## Requirements

- Drupal ^11.3
- [AI](https://www.drupal.org/project/ai) ^1.3 with the `ai_automators` submodule
- [Field Widget Actions](https://www.drupal.org/project/field_widget_actions) ^1.3
- [JSON Field](https://www.drupal.org/project/json_field) ^1.7
- Optional: `json_field:json_field_widget`
  - Enables the `json_editor` widget for `field_schemadotorg_jsonld` and the admin
    `default_jsonld` setting.

## Installation

1. Enable the module with `drush en ai_schemadotorg_jsonld -y`.
2. Configure an AI provider at `/admin/config/ai/settings`.
3. Go to `/admin/config/ai/schemadotorg-jsonld`.
4. Review the enabled entity types, enable any additional supported entity types you need, and
   save the form.
5. For each enabled entity type, select the bundles that should get the
   `field_schemadotorg_jsonld` field and save the form.
6. Open content for a configured bundle, generate Schema.org JSON-LD, review it, and save.

Saving the settings form creates the field, AI automator, status field, and display configuration
for each selected bundle.

## Configuration

Navigate to `/admin/config/ai/schemadotorg-jsonld`.

### Entity type sections

Each enabled entity type gets its own open details section under `entity_types`.

Each section includes:

- a bundle selector
- a default prompt for that entity type
- a `default_jsonld` value injected on canonical pages for that entity type

Once a bundle is configured, its checkbox is disabled. Use the `Edit field` and `Delete field`
operations to manage the generated field configuration.

### Enabled entity types

The `Enabled entity types` details element lists supported entity types that can use this module.

- Existing `ai_schemadotorg_jsonld.settings:entity_types` entries are preselected and disabled.
- Selecting a new entity type adds default `prompt` and `default_jsonld` values to the module
  configuration.
- Newly enabled entity types then appear as full configuration sections on the next page load.

### Other settings

- `prompt`
  - Token-based prompt sent to the LLM for that entity type.
  - Use `[@entity_type:ai_schemadotorg_jsonld:content]` to include the rendered entity content in
    the prompt.
- `default_jsonld`
  - Static JSON-LD injected on canonical pages for that entity type.
  - Leave blank to disable entity-type-specific default JSON-LD output.
- `breadcrumb_jsonld`
  - Attaches a `BreadcrumbList` JSON-LD block when breadcrumb data is available.

## Tokens

The module defines `ai_schemadotorg_jsonld:content` for each enabled entity type.

Examples:

- `[node:ai_schemadotorg_jsonld:content]`
- `[media:ai_schemadotorg_jsonld:content]`
- `[term:ai_schemadotorg_jsonld:content]`

The token renders the entity as the anonymous user in the site default theme, converts
root-relative URLs to absolute URLs, and strips wrapper markup to produce cleaner prompt input for
the LLM.

## Page Header Output

The module can attach up to three JSON-LD script tags on canonical entity routes.

- `ai_schemadotorg_jsonld_breadcrumb`
  - Breadcrumb JSON-LD when `breadcrumb_jsonld` is enabled and breadcrumb data is available.
- `ai_schemadotorg_jsonld_default_{entity_type_id}`
  - The configured `entity_types.{entity_type_id}.default_jsonld` value for the current entity
    type.
- `ai_schemadotorg_jsonld_{entity_type_id}_{id}`
  - The saved `field_schemadotorg_jsonld` value from the current entity.

Other modules can alter these attachments with `hook_page_attachments_alter()`.

## Architecture

- `AiSchemaDotOrgJsonLdBuilder`
  - Creates field storage, field config, AI automators integration, status field integration, and
    display configuration for a bundle.
- `AiSchemaDotOrgJsonLdManager`
  - Tracks supported entity types and seeds default `entity_types` settings for newly enabled
    entity types.
- `AiSchemaDotOrgJsonLdBreadcrumbList`
  - Builds `BreadcrumbList` JSON-LD data from the current route breadcrumb.
- `AiSchemaDotOrgJsonLdTokenResolver`
  - Renders content entities for token output used in AI prompts.
- `AiSchemaDotOrgJsonLdEventSubscriber`
  - Extracts and validates JSON from AI responses before values are saved.
- `AiSchemaDotOrgJsonLdSettingsForm`
  - Provides the admin UI at `/admin/config/ai/schemadotorg-jsonld`.
- `AiSchemaDotOrgJsonLdHooks`
  - Attaches page-head JSON-LD, controls field access, and adds the copy button to supported field
    widgets.
- `AiSchemaDotOrgJsonLdTokenHooks`
  - Defines and resolves the per-entity-type `ai_schemadotorg_jsonld:content` token.

## Extending

- `AiSchemaDotOrgJsonLdBuilder::addFieldToEntity()` accepts any supported `$entity_type_id` and
  `$bundle` pair.
- `AiSchemaDotOrgJsonLdManager::getSupportedEntityTypes()` controls which entity types appear in
  the settings form.
- `hook_page_attachments_alter()` can alter or replace the JSON-LD script tags added by the
  module.
