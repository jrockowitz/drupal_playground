# Plugin Report

A Drupal 10/11 module that provides a **Reports** page and Drush commands for
introspecting all registered `DefaultPluginManager` services and their plugin
definitions.

## Features

- **Plugin managers list** — browse all `DefaultPluginManager` services registered
  in the container; sortable by service ID, provider, alter hook, subdirectory,
  discovery mechanism, plugin interface, and class.
- **Plugins list** — drill into any plugin manager to see all of its registered
  plugins with their definition attributes.
- **Plugin detail** — drill into any individual plugin to see its full definition,
  default configuration, element info, live plugin definition, and the full list of
  PHP interfaces it implements.
- **Client-side filter** — each table has a text filter that narrows rows in real
  time without a page reload.
- **Drush commands** — export plugin managers, plugins, and plugin detail as table,
  JSON, YAML, or CSV.

## Permissions

- **access site reports** — view all Plugin Report pages

## Usage

1. Navigate to **Administration → Reports → Plugins**.
2. Click a plugin manager's **Service ID** to view its plugins.
3. Click a plugin's **ID** to view its full detail.

### Plugin detail sections

Each plugin detail page shows sections that depend on which interfaces the plugin
implements:

- **Definition** — always present; static definition from the plugin manager.
- **Default Configuration** — `Drupal\Component\Plugin\ConfigurableInterface::defaultConfiguration()`
- **Element Info** — `Drupal\Core\Render\Element\ElementInterface::getInfo()`
- **Interfaces** — always present; all PHP interfaces the plugin class implements.

### Drush

List all plugin managers:

```bash
drush plugin-report:managers
drush plugin-report:managers --filter=provider=core
drush plugin-report:managers --fields=id,discovery,interface
drush plugin-report:managers --format=json
```

List plugins for a plugin manager:

```bash
drush plugin-report:plugins plugin.manager.block
drush plugin-report:plugins plugin.manager.block --filter=provider=core
drush plugin-report:plugins plugin.manager.block --format=json
```

Show detail for a single plugin:

```bash
drush plugin-report:plugin plugin.manager.block help_block
drush plugin-report:plugin plugin.manager.block help_block --format=json
```

---

This module was created using AI and understood by humans. See [Never submit code you do not understand](https://dri.es/never-submit-code-you-do-not-understand).
