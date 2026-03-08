# Drupal Modules

This file is the canonical reference for modules installed in this Drupal playground. It covers all enabled contrib modules and notable core modules. Use it to understand what's installed and why it's here.

**How to update:** Run the prompt at the bottom of this file.

---

## Contrib Modules

### AI

These modules are provided by the [AI module project](https://www.drupal.org/project/ai) (core + submodules) and related contrib packages.

- **[AI Core](https://www.drupal.org/project/ai)** — Abstraction layer for integrating AI providers (LLMs, image generators, etc.) into Drupal. Required by all other AI modules.
- **[AI Assistant API](https://www.drupal.org/project/ai)** *(submodule)* — Adds decoupled AI assistants usable from any frontend.
- **[AI Chatbot](https://www.drupal.org/project/ai)** *(submodule)* — Frontend chatbot UI built on top of the AI Assistant API.
- **[AI CKEditor Integration](https://www.drupal.org/project/ai)** *(submodule)* — Adds a CKEditor 5 plugin allowing editors to prompt AI for inline text generation.
- **[AI Content Suggestions](https://www.drupal.org/project/ai)** *(submodule)* — Passes content to a configured AI to suggest alterations (rewrites, summaries, etc.).
- **[AI Agents](https://www.drupal.org/project/ai_agents)** — Makes Drupal taskable by AI agents; enables autonomous multi-step AI operations.
- **[AI Dashboard](https://www.drupal.org/project/ai_dashboard)** — Central dashboard for managing and monitoring AI modules and features.
- **[AI Image Alt Text](https://www.drupal.org/project/ai_image_alt_text)** — Automatically generates alt text for image fields using AI.
- **[Anthropic Provider](https://www.drupal.org/project/ai)** — Connects the AI Core module to Anthropic (Claude) as an LLM provider.
- **[OpenAI Provider](https://www.drupal.org/project/ai)** — Connects the AI Core module to OpenAI (GPT) as an LLM provider.

### Admin / DX

- **[Coffee](https://www.drupal.org/project/coffee)** — Alfred-style keyboard-triggered search box for navigating the Drupal admin UI quickly.
- **[Dashboard](https://www.drupal.org/project/dashboard)** — Customizable admin dashboards with drag-and-drop layout support (requires Layout Builder).
- **[Gin Toolbar](https://www.drupal.org/project/gin_toolbar)** — Admin toolbar integration for the Gin theme; replaces the default toolbar with the Gin-styled one.
- **[Navigation Extra Tools](https://www.drupal.org/project/navigation_extra_tools)** — Adds quick-action links (flush cache, run cron, run updates) to the core Navigation menu.

### Developer Tools

- **[Devel](https://www.drupal.org/project/devel)** — Developer utilities: variable dumping (`kint`/`dpm`), query log, Twig debugging, and more.
- **[Devel Generate](https://www.drupal.org/project/devel)** *(submodule)* — Generates dummy content (nodes, users, taxonomy terms, menus) for testing.

### Utilities

- **[Key](https://www.drupal.org/project/key)** — Centralised, provider-agnostic key/secret management. Used by AI providers for API keys.
- **[Modeler API](https://www.drupal.org/project/modeler_api)** — Provides an API for embedding BPMN.iO and similar visual modelers in Drupal.
- **[Token](https://www.drupal.org/project/token)** — Exposes a UI for the Token API and fills in missing core token definitions.

---

## Core Modules (Notable)

Standard Drupal 11 core modules enabled on this site. Obvious foundational modules (Field, Filter, System, User, Node, Text) are omitted; only modules worth calling out explicitly are listed.

### Content & Media

- **Block Content** — Custom block types managed through the UI.
- **CKEditor 5** — Rich-text editor for body/text fields.
- **Comment** — Threaded comments on content entities.
- **Datetime** — Date and time field types.
- **Image** — Image field type with styles and effects.
- **Layout Builder** — Drag-and-drop per-entity or per-content-type layout overrides.
- **Media** — Core media entity system (images, files, remote video, etc.).
- **Media Library** — Widget and management UI for the media entity system.
- **Search** — Basic full-text search infrastructure.
- **Taxonomy** — Vocabulary and term management for categorizing content.
- **Views** — Flexible query builder and display system for any Drupal data.
- **Views UI** — Admin interface for building and managing views.

### Admin & Configuration

- **Configuration Manager** — Config import/export via the UI (`/admin/config/development/configuration`).
- **Contextual Links** — Inline "edit this block/view" links in the frontend.
- **Dashboard** *(core contrib)* — See contrib section above.
- **Field UI** — Admin UI for managing field definitions on entity bundles.
- **Help** — Built-in help pages for modules and themes.
- **Menu UI** — UI for assigning menu links when editing content.
- **Navigation** — Drupal's new sidebar navigation system (replaces the classic toolbar for admins).
- **Path** — URL alias UI on content edit forms.
- **Path Alias** — Storage and resolution of URL aliases.
- **Shortcut** — User-configurable shortcut bar in the admin toolbar.
- **Update Status** — Checks drupal.org for available module/core updates.

### Performance & Caching

- **BigPipe** — Streams pages to the browser and fills in personalised blocks after the fact, dramatically improving perceived performance.
- **Internal Dynamic Page Cache** — Caches pages for authenticated users, varying by cache contexts.
- **Internal Page Cache** — Full-page cache for anonymous users.

### Multilingual

- **Configuration Translation** — Translates config strings (labels, descriptions, etc.).
- **Content Translation** — Adds per-language translations to content entities.
- **Interface Translation** — Downloads and applies `.po` translation files from localize.drupal.org.
- **Language** — Core language negotiation and language entity management.

### Developer / Logging

- **Automated Cron** — Runs Drupal cron automatically on page requests when the CLI cron is not configured.
- **Announcements Feed** — Displays Drupal security/news announcements on the admin status page.
- **Database Logging** — Stores watchdog log entries in the database (`/admin/reports/dblog`).
- **History** — Tracks per-user node read timestamps (powers "new" markers).

---

## Contrib Theme

- **[Gin](https://www.drupal.org/project/gin)** — Modern, accessible admin theme. Used as the default admin theme on this site. Requires the Gin Toolbar module.

---

## Updating This File

Run the following prompt with an AI agent that has shell access to this repository:

```
Update docs/DRUPAL-MODULES.md to reflect the current state of installed modules.

Steps:
1. Run: ddev exec drush pm:list --status=enabled --type=module --format=json
   This gives you all enabled modules with their package and path.

2. Separate contrib modules (path starts with "modules/") from core modules
   (path starts with "core/").

3. For each contrib module, read web/modules/contrib/[name]/[name].info.yml
   (or find the correct .info.yml if the module is a submodule of a parent package)
   to confirm the description.

4. Rebuild the "Contrib Modules" section. Group by category. Preserve existing
   "Purpose" wording where the machine-readable description is too terse — only
   update it if the description has genuinely changed.

5. Rebuild the "Core Modules (Notable)" section. Omit truly foundational modules
   (field, filter, system, user, node, text) and keep only the ones that are
   notable or non-obvious.

6. Update the "Contrib Theme" section if the gin version has changed.

7. Preserve this "Updating This File" section verbatim at the bottom.

8. Do not change the file structure, headings, or intro paragraph.
```
