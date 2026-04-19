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
- canonical

Examples include `node`, `media`, `taxonomy_term`, `block_content`, `comment`, and `user`.
Only `node` is configured out of the box. Other supported entity types can be enabled after installation.

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
By default, only `node` is enabled.

Each section includes:

- a bundle selector
- a default prompt for that entity type
- a `default_jsonld` value injected on canonical pages for configured bundles of that entity type

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
  - Static JSON-LD injected on canonical pages for bundles of that entity type that already have the generated field.
  - Leave blank to disable entity-type-specific default JSON-LD output.
- `development.edit_prompt`
  - Displays an edit-prompt button on saved entity edit forms for site configuration administrators.

Install the optional `ai_schemadotorg_jsonld_breadcrumb` submodule to attach a
`BreadcrumbList` JSON-LD block when breadcrumb data is available.

## Prompt files

The module ships curated default prompts for common entity types in the
`prompts/entity_types/` directory inside the module. Each file is named
`ai_schemadotorg_jsonld.{entity_type_id}.prompt.txt`.

When `addEntityTypes()` builds the default prompt for a newly enabled entity type, it checks
for a matching file in that directory first. If the file exists, its contents are used verbatim.
If not, a minimal prompt is generated from the entity type definition.

Prompt files are provided for: `block_content`, `comment`, `media`, `node`, `taxonomy_term`, `user`.

To customize the default prompt for an entity type, either:

- Edit the prompt directly in the settings form at `/admin/config/ai/schemadotorg-jsonld`.
- Add or override `prompts/entity_types/ai_schemadotorg_jsonld.{entity_type_id}.prompt.txt` in
  a custom module and extend `AiSchemaDotOrgJsonLdManager` to override `getPromptFilePath()`.

## Tokens

The module defines `ai_schemadotorg_jsonld:content` for each enabled entity type.

Examples:

- `[node:ai_schemadotorg_jsonld:content]`
- `[term:ai_schemadotorg_jsonld:content]`
- `[user:ai_schemadotorg_jsonld:content]`

The token renders the entity as the anonymous user in the site default theme, converts
root-relative URLs to absolute URLs, and strips wrapper markup to produce cleaner prompt input for
the LLM.

## Page Header Output

The module can attach up to three JSON-LD script tags on canonical entity routes.

- `ai_schemadotorg_jsonld_breadcrumb`
  - Breadcrumb JSON-LD when the optional `ai_schemadotorg_jsonld_breadcrumb` submodule is enabled and breadcrumb data is available.
- `ai_schemadotorg_jsonld_default_{entity_type_id}`
  - The configured `entity_types.{entity_type_id}.default_jsonld` value for the current entity
    type when the current entity bundle has the generated field.
- `ai_schemadotorg_jsonld_{entity_type_id}_{id}`
  - The saved `field_schemadotorg_jsonld` value from the current entity.

Other modules can alter these attachments with `hook_page_attachments_alter()`.
