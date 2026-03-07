# Drupal Recipes

## What Is a Recipe?

A Drupal Recipe is a declarative tool that automates the installation of modules, import of configuration, modification of existing configuration, and creation of default content on a Drupal site. Think of it like a cooking recipe: it provides a series of steps that, if followed manually, would arrive at the same result — but the recipe runner executes them all at once.

Recipes were added to Drupal core as **experimental APIs in Drupal 10.3** and are considered functional and stable as of Drupal 11.1+. They are the foundation of **Drupal CMS** (formerly the Starshot initiative), where curated recipes power the "recommended add-ons" that users can enable with a single click.

Key characteristics:

- **Applied, not installed.** A recipe is run once. After application, the resulting configuration and content become the site's responsibility. There is no ongoing dependency on the recipe itself.
- **Ephemeral.** Once applied, the recipe can be removed from the codebase. There is nothing to upgrade or maintain.
- **Composable.** Recipes can include other recipes as dependencies, applied sequentially before the current recipe runs.
- **Declarative, not functional.** Recipes contain no custom PHP code, hooks, or plugins. If you need code, the recipe should install a module that provides it.
- **Additive only.** Config actions support updates and additions, not removals. This ensures recipes can coexist without breaking each other.
- **Idempotent (by convention).** Recipes should be safe to apply multiple times without causing errors, using strategies like `createIfNotExists`.

## How Recipes Differ from Distributions and Install Profiles

Distributions and install profiles have historically been the way to package functionality for Drupal. However, they have significant limitations:

- Install profiles can only be used at site creation time; you cannot switch profiles later.
- Distributions couple everything together and are notoriously difficult to maintain as core and contrib evolve.
- Update paths are complex and fragile.

Recipes solve these problems by being applicable **at any point** in a Drupal site's lifecycle — whether it's brand new or years old. They are lightweight, composable, and do not create ongoing maintenance burdens.

A helpful mental model from the community: recipes can be **"meals"** (whole site setups), **"dishes"** (individual features like a blog or events section), or **"ingredients"** (very small, single-purpose config changes like creating a role).

## The Four Core APIs

The Recipe system is built on four APIs that were added to Drupal core:

1. **Recipe API** (`Drupal\Core\Recipe`) — Orchestrates the overall process: parses `recipe.yml`, resolves dependencies, and applies each step in order.
2. **Config Action API** (`Drupal\Core\Config\Action`) — The key innovation. Allows recipes to modify *existing* configuration on a site, including core config. Config actions are defined as PHP attributes on config entity methods.
3. **Checkpoint API** (`Drupal\Core\Config\Checkpoint`) — Creates a snapshot of site configuration before a recipe is applied. If application fails, configuration can be rolled back.
4. **DefaultContent API** (`Drupal\Core\DefaultContent`) — Allows recipes to ship content entities (nodes, taxonomy terms, media, etc.) as YAML files. Originally based on the contributed Default Content module; a core `content:export` command was added in Drupal 11.3+.

## Recipe Folder Structure

A recipe lives in a named directory and requires only a `recipe.yml` file at minimum. The full structure looks like this:

```
my_recipe/
├── recipe.yml          # Required: defines the recipe
├── composer.json       # Recommended: declares dependencies
├── config/             # Optional: static config YAML files to import
│   ├── node.type.event.yml
│   └── field.field.node.event.field_date.yml
├── content/            # Optional: default content YAML files
│   ├── node/
│   │   └── <uuid>.yml
│   └── taxonomy_term/
│       └── <uuid>.yml
├── README.md           # Optional
└── LICENSE.md          # Optional
```

## The recipe.yml File

This is the heart of every recipe. It supports the following top-level keys:

### name (required)

A human-readable name for the recipe.

```yaml
name: 'Event Content Type'
```

### description (required)

A longer description of what the recipe does.

```yaml
description: 'Creates an Event content type with date, location, and description fields.'
```

### type

Groups related recipes together for UI display. The special type `Site` allows a recipe to be listed in the Drupal installer.

```yaml
type: 'Content type'
```

Common values: `Site`, `Content type`, `Feature`, `Privacy`, `SEO`, etc.

### recipes

An array of other recipes to apply *before* this one. These can be core recipes (referenced by path) or contributed recipes (referenced by name).

```yaml
recipes:
  - core/recipes/administrator_role
  - my_org/base_config
```

### install

A list of modules and/or themes to install. Drupal automatically detects whether each item is a module or theme.

```yaml
install:
  - datetime_range
  - node
  - address
  - pathauto
  - metatag
```

### config

The config section has two sub-keys: `import` and `actions`.

#### config.import

Import configuration that a module or theme ships. Use `'*'` to import all of a module's config, or list specific config items:

```yaml
config:
  import:
    # Import everything from the media module
    media: '*'
    # Import only specific config from the node module
    node:
      - views.view.content
    # Import specific config from the user module
    user:
      - views.view.user_admin_people
```

#### config.actions

Modify existing configuration on the site. This is the most powerful part of the Recipe API. Actions call methods on config entity classes.

```yaml
config:
  actions:
    # Grant permissions to a role
    user.role.editor:
      grantPermissions:
        - 'delete any article content'
        - 'edit any article content'

    # Update simple configuration values
    system.site:
      simpleConfigUpdate:
        page.front: /homepage

    # Add a field to an entity view display
    core.entity_view_display.node.article.default:
      setComponent:
        name: field_tags
        options:
          type: entity_reference_label
          label: above
          settings:
            link: true
          weight: 10
          region: content

    # Ensure a role exists before acting on it
    user.role.content_editor:
      createIfNotExists:
        label: 'Content Editor'

    # Set third-party settings
    node.type.article:
      setThirdPartySetting:
        module: scheduler
        key: publish_enable
        value: true

    # Create config only if it doesn't already exist
    block.block.my_custom_block:
      createIfNotExists:
        # full config entity values here
```

### input (Drupal 11.1+)

Recipes can accept user input at the command line and use the values as tokens in config actions.

```yaml
input:
  recipient:
    data_type: email
    description: 'The email address that should receive form submissions.'
    constraints:
      NotBlank: []
    default:
      source: config
      config: ['system.site', 'mail']

config:
  actions:
    contact.form.feedback:
      setRecipients:
        - ${recipient}
```

Input tokens (`${name}`) can be used anywhere in the `config.actions` values but **not** in array keys.

## Available Config Actions

Config actions are defined as `#[ActionMethod]` PHP attributes on config entity methods. The most commonly used ones include:

| Action | Purpose | Example Target |
|--------|---------|----------------|
| `simpleConfigUpdate` | Update key-value pairs in any config | `system.site` |
| `grantPermission` / `grantPermissions` | Add permissions to a role | `user.role.editor` |
| `createIfNotExists` | Conditionally create a config entity if it doesn't already exist | `user.role.content_editor` |
| `setComponent` / `setComponents` | Add fields to form/view displays | `core.entity_view_display.*` |
| `setThirdPartySetting` / `setThirdPartySettings` | Set third-party settings | `node.type.article` |

Singular actions (e.g., `grantPermission`) take a single argument. Pluralized actions (e.g., `grantPermissions`) take an array and call the singular method once per item. Modules can define their own custom config actions via the `#[ActionMethod]` attribute.

## Providing Default Content

Recipes can ship content entities as YAML files in a `content/` directory, organized by entity type. Each file is named by UUID.

### Exporting Content (Drupal 11.3+)

Core provides a CLI command for exporting content:

```bash
# Export a single node
php core/scripts/drupal content:export node 42

# Export a node and all its dependencies (media, terms, etc.) to a directory
php core/scripts/drupal content:export node 42 --with-dependencies --dir=content

# Export all media of a specific bundle
php core/scripts/drupal content:export media --bundle=image --with-dependencies --dir=../recipes/my_recipe/content
```

### Exporting Content (Drupal 11.2 and earlier)

Use the contributed **Default Content** module:

```bash
composer require --dev drupal/default_content
drush en -y default_content

# Export a node and all referenced entities to a recipe's content folder
drush dcer node 123 --folder=recipes/my_recipe/content
```

### Content YAML Format

Exported content files use a `_meta` header followed by field values:

```yaml
_meta:
  version: '1.0'
  entity_type: taxonomy_term
  uuid: 386059fa-39bb-4fa4-ae79-1b204a6d55c6
  bundle: tags
  default_langcode: en
default:
  status:
    - value: true
  name:
    - value: 'Drupal Development'
  weight:
    - value: 0
```

Translations are embedded in the same file under a `translations` key:

```yaml
translations:
  de:
    status:
      - value: true
    name:
      - value: 'Drupal-Entwicklung'
```

Important notes about content:
- On import, entities receive **new local IDs** (not the original IDs from export). UUIDs are used internally for dependency resolution.
- Content is organized in subdirectories by entity type: `content/node/`, `content/taxonomy_term/`, `content/media/`, etc.
- The `recipe.yml` does not need to explicitly list content files — everything in the `content/` directory is imported automatically.
- Configuration that content depends on (vocabularies, content types, fields) must be applied first, either by the recipe itself or by a dependency recipe.

## The composer.json File

For distributable recipes, include a `composer.json` with `type` set to `drupal-recipe`:

```json
{
  "name": "my_org/event_recipe",
  "description": "Creates an Event content type with fields and views.",
  "type": "drupal-recipe",
  "require": {
    "drupal/address": "^2.0",
    "drupal/smart_date": "^4.0",
    "drupal/pathauto": "^1.12"
  }
}
```

This ensures that when someone runs `composer require my_org/event_recipe`, all module dependencies are pulled in automatically.

## Creating a Recipe: Step by Step

### 1. Plan Your Recipe

Decide what the recipe should accomplish:
- Which modules need to be installed?
- What content types, fields, views, or other config entities need to exist?
- Does any existing configuration need to be modified (e.g., adding permissions)?
- Should it compose other, smaller recipes?

### 2. Create the Directory Structure

```bash
mkdir -p recipes/my_event_recipe/config
```

### 3. Write recipe.yml

Start with the basics and build up:

```yaml
name: 'Event Content Type'
description: 'Provides an Event content type with date and location fields, plus a listing view.'
type: 'Content type'

recipes:
  - core/recipes/administrator_role

install:
  - datetime_range
  - node
  - address
  - views
  - pathauto

config:
  import:
    pathauto: '*'
  actions:
    user.role.administrator:
      grantPermissions:
        - 'create event content'
        - 'edit any event content'
        - 'delete any event content'
```

### 4. Add Configuration Files

Export configuration from a working site and place the YAML files in the `config/` directory:

```bash
# Export specific config with Drush
drush cex --destination=/tmp/config

# Copy the files you need
cp /tmp/config/node.type.event.yml recipes/my_event_recipe/config/
cp /tmp/config/field.storage.node.field_event_date.yml recipes/my_event_recipe/config/
cp /tmp/config/field.field.node.event.field_event_date.yml recipes/my_event_recipe/config/
# ... etc.
```

Remove the `uuid` and `_core` keys from exported config files — these are site-specific and should not be in a recipe.

### 5. Add Default Content (Optional)

If you want to ship sample content:

```bash
# Drupal 11.3+
php core/scripts/drupal content:export node 42 --with-dependencies --dir=recipes/my_event_recipe/content

# Drupal 11.2 and earlier
drush dcer node 42 --folder=recipes/my_event_recipe/content
```

### 6. Create composer.json

```json
{
  "name": "my_org/event_recipe",
  "description": "Event content type recipe",
  "type": "drupal-recipe",
  "require": {
    "drupal/address": "^2.0",
    "drupal/datetime_range": "*"
  }
}
```

### 7. Test the Recipe

Apply it to a clean or existing site:

```bash
# From the webroot (web/ or docroot/)
php core/scripts/drupal recipe ../recipes/my_event_recipe -v

# Or with Drush
drush recipe ../recipes/my_event_recipe

# Clear caches afterward
drush cr
```

The `-v` flag provides verbose output showing each step as it is applied.

## Applying Recipes

### Adding a Contributed Recipe via Composer

```bash
# Configure installer paths for recipes (already done on Drupal 11.2+)
composer config allow-plugins.drupal/core-recipe-unpack true
composer require drupal/core-recipe-unpack
composer require composer/installers:^2.3
composer config --merge --json extra.installer-paths '{"recipes/{$name}":["type:drupal-recipe"]}'

# Require the recipe
composer require drupal/user_privacy_core
```

### Running the Recipe

From the webroot:

```bash
# Using core's script
php core/scripts/drupal recipe recipes/user_privacy_core

# Using Drush
drush recipe recipes/user_privacy_core
```

## Complete Example: Blog Recipe

Here is a full example of a blog recipe that composes sub-recipes, installs modules, imports config, modifies existing config, and provides content:

```yaml
# recipes/my_blog/recipe.yml
name: 'Blog'
description: 'Adds a blog post content type with tagging, paths, and a listing page.'
type: 'Content type'

recipes:
  - core/recipes/tags_taxonomy
  - core/recipes/administrator_role

install:
  - node
  - text
  - taxonomy
  - path
  - views
  - pathauto
  - metatag
  - metatag_open_graph

config:
  import:
    pathauto: '*'
    metatag:
      - metatag.metatag_defaults.node
  actions:
    user.role.administrator:
      grantPermissions:
        - 'create blog_post content'
        - 'edit any blog_post content'
        - 'delete any blog_post content'
    user.role.content_editor:
      createIfNotExists:
        label: 'Content Editor'
      grantPermissions:
        - 'create blog_post content'
        - 'edit own blog_post content'
```

With the corresponding config files in `config/`:
- `node.type.blog_post.yml`
- `field.storage.node.field_blog_body.yml`
- `field.field.node.blog_post.field_blog_body.yml`
- `field.field.node.blog_post.field_tags.yml`
- `core.entity_view_display.node.blog_post.default.yml`
- `core.entity_form_display.node.blog_post.default.yml`
- `views.view.blog_listing.yml`

## What Recipes Cannot Do

- **Contain custom code.** No hooks, services, plugins, or PHP. If you need code, write a module and have the recipe install it.
- **Remove configuration.** Config actions are additive only to prevent recipes from breaking each other.
- **Be uninstalled.** There is no formal mechanism to reverse a recipe. To undo it, manually remove the configuration and content it created.
- **Provide ongoing functionality.** Once applied, the recipe is done. It does not run again on cache clears or deployments.
- **Include nested recipes inside their own directory.** Recipes cannot physically contain other recipe directories; they reference them as dependencies.

## Best Practices

1. **Keep recipes small and focused.** A single recipe should do one thing well (e.g., create one content type). Compose larger functionality from smaller recipes.
2. **Use `createIfNotExists` for roles.** Don't assume roles exist; use the `createIfNotExists` action to create them if missing.
3. **Use `createIfNotExists` for idempotency.** This prevents errors when a recipe is applied to a site that already has some of the config.
4. **Strip `uuid` and `_core` from config files.** These are site-specific and will cause conflicts.
5. **Test on both fresh and existing sites.** Recipes should work in both scenarios.
6. **Document your recipe.** Include a `README.md` explaining what the recipe does, what modules it requires, and any manual steps needed after application.
7. **Prefer config actions over config files for modifying existing config.** Static config files in `config/` are for *new* config entities. To change existing config, use `config.actions`.

## Resources

- [Drupal Recipes Initiative Documentation](https://project.pages.drupalcode.org/distributions_recipes/recipe.html)
- [Drupal.org: How to Download and Apply Recipes](https://www.drupal.org/docs/extending-drupal/drupal-recipes/how-to-download-and-apply-drupal-recipes)
- [Config Actions Reference](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html)
- [Recipes Cookbook (community-submitted recipes)](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/distributions-and-recipes-initiative/recipes-cookbook)
- [Default Content Documentation](https://project.pages.drupalcode.org/distributions_recipes/default_content.html)
- [Drupalize.Me: Drupal Recipes Course](https://drupalize.me/blog/release-day-drupal-recipes-api)
