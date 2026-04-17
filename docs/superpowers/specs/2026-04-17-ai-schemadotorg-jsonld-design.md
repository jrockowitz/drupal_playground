# AI Schema.org JSON-LD Module — Design Spec

**Date:** 2026-04-17
**Module:** `ai_schemadotorg_jsonld`
**Location:** `web/modules/sandbox/ai_schemadotorg_jsonld/`

---

## Overview

A glue module that leverages AI Automators, Field Widget Actions, and JSON Field to add a Schema.org JSON-LD field to selected content types. The field is populated by an LLM via a configurable token-based prompt. The generated JSON-LD is attached to node pages as a `<script type="application/ld+json">` tag in the page header.

**Target versions:** Drupal ^11.3, AI ^1.3, Field Widget Actions ^1.3, JSON Field ^1.7. Always targeting the latest stable release of core and contrib modules.

---

## Namespacing

- Module machine name: `ai_schemadotorg_jsonld`
- PHP namespace: `Drupal\ai_schemadotorg_jsonld`
- Class prefix: `AiSchemaDotOrgJsonLd*`
- Service IDs: `ai_schemadotorg_jsonld.*`

---

## File Structure

```
web/modules/sandbox/ai_schemadotorg_jsonld/
├── ai_schemadotorg_jsonld.info.yml
├── ai_schemadotorg_jsonld.module            # empty shell — all hooks via OOP
├── ai_schemadotorg_jsonld.services.yml
├── ai_schemadotorg_jsonld.routing.yml
├── ai_schemadotorg_jsonld.links.menu.yml
├── ai_schemadotorg_jsonld.libraries.yml     # Copy JSON-LD JS/CSS assets
├── ai_schemadotorg_jsonld.token.inc         # hook_token_info + hook_token (thin wrappers)
├── composer.json
├── README.md
├── phpcs.xml.dist
├── phpstan.neon
├── .gitlab-ci.yml                           # copied from entity_labels
├── config/
│   └── install/
│       └── ai_schemadotorg_jsonld.settings.yml
├── js/
│   └── ai_schemadotorg_jsonld.copy.js
├── css/
│   └── ai_schemadotorg_jsonld.copy.css
└── src/
    ├── AiSchemaDotOrgJsonLdBuilderInterface.php
    ├── AiSchemaDotOrgJsonLdBuilder.php
    ├── AiSchemaDotOrgJsonLdBreadcrumbListInterface.php
    ├── AiSchemaDotOrgJsonLdBreadcrumbList.php
    ├── AiSchemaDotOrgJsonLdTokenResolverInterface.php
    ├── AiSchemaDotOrgJsonLdTokenResolver.php
    ├── EventSubscriber/
    │   └── AiSchemaDotOrgJsonLdEventSubscriber.php
    ├── Form/
    │   └── AiSchemaDotOrgJsonLdSettingsForm.php
    └── Hook/
        └── AiSchemaDotOrgJsonLdHooks.php
└── tests/
    └── src/
        ├── Kernel/
        │   └── AiSchemaDotOrgJsonLdBuilderTest.php
        ├── Functional/
        │   ├── AiSchemaDotOrgJsonLdSettingsFormTest.php
        │   ├── AiSchemaDotOrgJsonLdTokenResolverTest.php
        │   └── AiSchemaDotOrgJsonLdPageAttachmentsTest.php
        └── Unit/
            └── AiSchemaDotOrgJsonLdEventSubscriberTest.php
```

### Dependencies (`info.yml`)

- Required: `ai:ai_automators`, `json_field:json_field`, `field_widget_actions:field_widget_actions`
- Optional (soft): `json_field:json_field_widget` — enables `json_editor` widget support

---

## Settings Configuration

### `config/install/ai_schemadotorg_jsonld.settings.yml`

```yaml
prompt: |
  Generate valid Schema.org JSON-LD for the content below:

  # Input

  Type: [node:content-type]
  URL: [node:url]
  Title: [node:title]
  Summary: [node:summary]
  Image: [node:field_image]

  Body:
  [node:body]

  Content:
  [node:ai_schemadotorg_jsonld:content]

  Current JSON-LD: (This will be omitted for new content)
  [node:field_schemadotorg_jsonld]

  # Requirements

  ## Response

  - Return ONLY the JSON-LD object. No explanatory text, no markdown fences, no preamble.
  - Output must begin with { and end with }.

  ## Schema.org JSON-LD
  - Use only valid Schema.org types and properties (https://schema.org).
  - Set @context to "https://schema.org".
  - Set url to the canonical URL provided above.
  - Choose the most specific applicable @type for the content type given.
  - Use absolute URLs for all links and images.

  ## Schema.org properties and values

  - Try to include all text in the 'Body' and 'Full Content'
  - Do not fabricate values or URLs, only include text/values/urls that are in the content.

  ## Misc

  - If Current JSON-LD is provided, preserve any manually curated properties and improve or extend — do not discard existing values without cause.
  - Follow Google's Structured Data guidelines: https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data

  # Output format

  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "url": "{url}",
    "name": "{title}",
    "description": "{summary}",
    "text": "{content}",
    "image": [
      {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "{image:src}",
        "description": "{image:alt}"
      }
    ]
  }
default_jsonld: ''
breadcrumb_jsonld: false
bundles: []
```

---

## Settings Form (`AiSchemaDotOrgJsonLdSettingsForm`)

- Path: `/admin/config/ai/schemadotorg-jsonld`
- Title: AI Schema.org JSON-LD
- Extends `ConfigFormBase` with `RedundantEditableConfigNamesTrait`

### Layout

**1. Content types** (fieldset, displayed first)

A `tableselect` with columns: Content type label, Machine name, Operations.

- Content types that already have `field_schemadotorg_jsonld`: pre-checked and `#disabled = TRUE`. Their bundle IDs are always merged back into `bundles` config so they cannot be removed via the form.
- Operations column (only shown for existing fields): Edit link (opens field edit form in modal), Delete link (standard Drupal field delete route with `?destination=` back to the settings page).
- On submit: newly checked bundles call `AiSchemaDotOrgJsonLdBuilder::addFieldToEntity()`.

**2. Additional settings** (`<details>`, collapsed by default)

All elements use `#config_target` pointing to `ai_schemadotorg_jsonld.settings`.

| Element | Type | Config target key |
|---|---|---|
| Prompt | `textarea` | `prompt` |
| Default JSON-LD | `json_editor` or `textarea` fallback | `default_jsonld` |
| Include breadcrumb JSON-LD | `checkbox` | `breadcrumb_jsonld` |

The Default JSON-LD element:
- Uses the `json_editor` widget and attaches the `json_field_widget/json_editor.widget` library only when `json_field_widget` is installed (checked via injected `ModuleHandlerInterface`). Falls back to a plain `textarea` otherwise.
- Has `#element_validate` callback that runs `json_decode()` on the submitted value and sets a form error if the JSON is invalid. Empty value is allowed.

---

## Builder Service (`AiSchemaDotOrgJsonLdBuilder`)

Implements `AiSchemaDotOrgJsonLdBuilderInterface`. Autowired. Both `AiSchemaDotOrgJsonLdBuilder` and `AiSchemaDotOrgJsonLdBuilderInterface` live directly in `src/` (no subdirectory).

```
const FIELD_NAME = 'field_schemadotorg_jsonld'
```

### `public function addFieldToEntity(string $entity_type_id, string $bundle): void`

Orchestrates the five private methods below in order. Step 1 always runs. Steps 3–5 are skipped if the field instance already existed before step 2 (i.e. `createField` detected a pre-existing `FieldConfig`).

### `private function createFieldStorage(string $entity_type_id): void`

Checks whether `FieldStorageConfig` for `{entity_type_id}.field_schemadotorg_jsonld` already exists. Creates it if not; loads it if so. Always saves to keep storage config current. Type: `json_native`, cardinality: 1, translatable: true.

### `private function createField(string $entity_type_id, string $bundle): void`

Returns early (no-op) if `FieldConfig` for `{entity_type_id}.{bundle}.field_schemadotorg_jsonld` already exists — signals to `addFieldToEntity` to skip steps 3–5. Otherwise creates it. Label: "Schema.org JSON-LD", not required, translatable: true.

### `private function createAutomator(string $entity_type_id, string $bundle): void`

Creates an `ai_automator` config entity (`{entity_type_id}.{bundle}.field_schemadotorg_jsonld.default`) if it does not already exist. Rule: `llm_json_native_field`, input mode: token, worker: `field_widget_actions`, provider: `default_json`. Prompt token value pulled from `ai_schemadotorg_jsonld.settings:prompt`.

### `private function addFormDisplayComponent(string $entity_type_id, string $bundle): void`

Loads the default form display for the bundle. Sets `field_schemadotorg_jsonld` with widget `json_editor` if `json_field_widget` is installed, otherwise `json_textarea`. Weight 99, with a `field_widget_actions` action configured for the automator button (label: "Generate Schema.org JSON-LD"). Saves only if changed.

### `private function addViewDisplayComponent(string $entity_type_id, string $bundle): void`

Loads the default view display. Sets `field_schemadotorg_jsonld` with the `pretty` formatter at weight 99. Only modifies the default display — leaves teaser and other view modes untouched. Saves only if changed.

---

## Event Subscriber (`AiSchemaDotOrgJsonLdEventSubscriber`)

Subscribes to `\Drupal\ai_automators\Event\ValuesChangeEvent`. Autowired. Only acts when the event's field name matches `AiSchemaDotOrgJsonLdBuilder::FIELD_NAME`.

### JSON extraction logic

1. Trim the raw value string.
2. If `str_starts_with('{')` AND `str_ends_with('}')` → proceed to validation.
3. Otherwise: find the first `{` and last `}` in the string and extract the substring (inclusive). If neither character is found, log a warning and set an empty value.
4. Validate via `json_decode()` with `JSON_THROW_ON_ERROR`. If invalid, log a warning to the `ai_schemadotorg_jsonld` channel and add a `MessengerInterface::addWarning()` to the end user.
5. Set the cleaned value back on the event.

All services (logger, messenger) injected via constructor.

---

## Breadcrumb Service (`AiSchemaDotOrgJsonLdBreadcrumbList`)

Implements `AiSchemaDotOrgJsonLdBreadcrumbListInterface`. Generates a `BreadcrumbList` JSON-LD array for the current page. Called by `hook_page_attachments` when `breadcrumb_jsonld` is enabled. Modelled on `SchemaDotOrgJsonLdBreadcrumbManager` but with no dependency on the `schemadotorg` module. Named `BreadcrumbList` (not `BreadcrumbBuilder`) to signal it produces data, not a Drupal breadcrumb object.

### `public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): ?array`

1. Check whether `ChainBreadcrumbBuilderInterface::applies()` returns true for the current route. Return `NULL` if not.
2. Build the breadcrumb via `ChainBreadcrumbBuilderInterface::build()`. Return `NULL` if the result has no links.
3. Add the breadcrumb's cache metadata to `$bubbleable_metadata`.
4. Iterate over breadcrumb links, building `ListItem` entries (position, `@id` as absolute URL, `name` as rendered text). Render array text via `RendererInterface::renderInIsolation()`.
5. Append the current route's node as a final `ListItem` using the node label and its canonical URL (resolved from `RouteMatchInterface`).
6. Return the completed `BreadcrumbList` array:

```php
[
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => $items,
]
```

### Injected services (general → specific)

1. `RendererInterface`
2. `RouteMatchInterface` (current_route_match)
3. `ChainBreadcrumbBuilderInterface`

---

## Hooks (`AiSchemaDotOrgJsonLdHooks`)

All methods in `src/Hook/AiSchemaDotOrgJsonLdHooks.php` use `#[Hook]` attributes. All services injected via constructor (autowired).

### `hook_field_widget_action_info_alter()`

Adds `json_editor` to the `widget_types` array for the `automator_json` `FieldWidgetAction` plugin definition. Only runs when `json_field_widget` module is installed (checked via injected `ModuleHandlerInterface`).

### `hook_page_attachments(array &$attachments)`

Each JSON-LD block is attached as a separately keyed item in `$attachments['#attached']['html_head']` so other modules can target them via `hook_page_attachments_alter()`:

- `ai_schemadotorg_jsonld_default` — site-wide default JSON-LD from settings (if non-empty).
- `ai_schemadotorg_jsonld_breadcrumb` — breadcrumb JSON-LD built by `AiSchemaDotOrgJsonLdBreadcrumbList::build()` (if `breadcrumb_jsonld` setting is true and `build()` returns non-null).
- `ai_schemadotorg_jsonld_node_{nid}` — node's `field_schemadotorg_jsonld` value (only on node canonical routes, only when the field is non-empty).

### `hook_field_widget_complete_form_alter()`

A single `#[Hook]` implementation. Guards on widget type being `json_textarea` or `json_editor` (the latter only when `json_field_widget` is installed), and on the field name matching `FIELD_NAME`. Adds a "Copy JSON-LD" button element below the field widget and attaches the `ai_schemadotorg_jsonld/copy` library.

### `hook_entity_field_access()`

- Denies edit access to `field_schemadotorg_jsonld` when the entity has no ID (unsaved node) to prevent "entity cannot have a URI" errors.
- Denies view access to `field_schemadotorg_jsonld` for users who cannot update the node.

---

## Token (`AiSchemaDotOrgJsonLdTokenResolver`)

Implements `AiSchemaDotOrgJsonLdTokenResolverInterface`. `ai_schemadotorg_jsonld.token.inc` provides `hook_token_info()` and `hook_token()` as thin wrappers that delegate to this service.

### Token

`[node:ai_schemadotorg_jsonld:content]` — renders the node's full display for use in the AI prompt.

### Resolution steps

1. Switch to the anonymous user via `AccountSwitcherInterface` (uses `new AnonymousUserSession()`).
2. Switch to the site default theme (read from `system.theme` config) via `ThemeManagerInterface` + `ThemeInitializationInterface`.
3. Render the node using the default view mode via `EntityTypeManagerInterface` + `RendererInterface`.
4. Restore the original account and theme.
5. Post-process the HTML:
   - **Strip wrapping divs:** remove `<div><div>…</div></div>` outer wrapper pairs where the outer div contains only a single direct child div. Preserve deeper nesting.
   - **Absolutize URLs:** convert root-relative `href` and `src` attributes to absolute using the site base URL from `RequestStack`.
   - **Preserve semantic markup:** retain `<p>`, `<h1>`–`<h6>`, `<ul>`, `<ol>`, `<li>`, `<blockquote>`, `<figure>`, etc.
6. Return the processed HTML string.

### Injected services (general → specific)

1. `ConfigFactoryInterface`
2. `RendererInterface`
3. `RequestStack`
4. `AccountSwitcherInterface`
5. `ThemeManagerInterface`
6. `ThemeInitializationInterface`
7. `EntityTypeManagerInterface`

---

## Assets

`ai_schemadotorg_jsonld.libraries.yml` declares:

```yaml
copy:
  js:
    js/ai_schemadotorg_jsonld.copy.js: {}
  css:
    component:
      css/ai_schemadotorg_jsonld.copy.css: {}
  dependencies:
    - core/drupal
    - core/once
```

`ai_schemadotorg_jsonld.copy.js` copies the JSON field value to the clipboard when the "Copy JSON-LD" button is clicked. Duplicates the copy-button pattern from `schemadotorg_jsonld_preview`.

---

## Testing

### `AiSchemaDotOrgJsonLdBuilderTest` (KernelTest)

`::testAddField()`:
- Install required modules, create `page` node type.
- Call `addFieldToEntity('node', 'page')`.
- Check that `FieldStorageConfig` for `node.field_schemadotorg_jsonld` exists.
- Check that `FieldConfig` for `node.page.field_schemadotorg_jsonld` exists and is translatable.
- Check that the `ai_automator` config entity `node.page.field_schemadotorg_jsonld.default` exists.
- Check that the default form display for `node.page` includes `field_schemadotorg_jsonld` at weight 99.
- Check that the default view display for `node.page` includes `field_schemadotorg_jsonld` at weight 99.
- Check that calling `addFieldToEntity` a second time does not throw (idempotency).
- Delete the `FieldConfig` for `node.page.field_schemadotorg_jsonld`.
- Check that the `ai_automator` config entity `node.page.field_schemadotorg_jsonld.default` no longer exists. (This verifies correct cascade delete behaviour — or flags a bug in `ai_automators` if the automator persists as an orphan.)

### `AiSchemaDotOrgJsonLdSettingsFormTest` (BrowserTest)

`::testSettingsForm()`:
- Check that the settings form loads at `/admin/config/ai/schemadotorg-jsonld`.
- Check that the content types tableselect lists available node bundles.
- Check that selecting `page` and saving persists `page` in `bundles` config.
- Check that `page` is pre-checked and disabled after being configured.
- Check that the Operations column shows Edit and Delete links for `page`.
- Check that the Additional settings details element is present and collapsed.

### `AiSchemaDotOrgJsonLdTokenResolverTest` (BrowserTest)

`::testContentToken()`:
- Create a `page` node with body text and an image.
- Resolve `[node:ai_schemadotorg_jsonld:content]` for that node.
- Check that the rendered output contains expected body text.
- Check that root-relative URLs have been converted to absolute URLs.
- Check that bare wrapping `<div><div>` patterns are absent.
- Check that rendering was performed as the anonymous user (no admin markup, no contextual links).

### `AiSchemaDotOrgJsonLdPageAttachmentsTest` (BrowserTest)

`::testPageAttachments()`:
- Configure `default_jsonld` with a valid JSON-LD string.
- Enable `breadcrumb_jsonld`.
- Configure `page` bundle and create a `page` node with a `field_schemadotorg_jsonld` value.
- Visit the node's canonical URL.
- Check that the page `<head>` contains a `<script type="application/ld+json">` tag with the default JSON-LD (`ai_schemadotorg_jsonld_default`).
- Check that the page `<head>` contains a `<script type="application/ld+json">` tag with breadcrumb JSON-LD (`ai_schemadotorg_jsonld_breadcrumb`).
- Check that the page `<head>` contains a `<script type="application/ld+json">` tag with the node's field value (`ai_schemadotorg_jsonld_node_{nid}`).
- Visit a non-node page and check that the node-specific tag is absent.

### `AiSchemaDotOrgJsonLdEventSubscriberTest` (UnitTest)

`::testValidJson()` — clean `{"@type":"WebPage"}` is returned unchanged.

`::testJsonInMarkdownFence()` — ` ```json\n{"@type":"WebPage"}\n``` ` extracts to `{"@type":"WebPage"}`.

`::testJsonWithSurroundingText()` — `"Here is the JSON: {"@type":"WebPage"} Hope that helps!"` extracts to `{"@type":"WebPage"}`.

`::testInvalidJson()` — `{not valid json}` results in empty value, logged warning, and messenger warning.

`::testNoJsonFound()` — `"No JSON here at all"` results in empty value and warning triggered.
