<?php

declare(strict_types=1);

namespace Drupal\plugin_report\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\plugin_report\PluginReportManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Plugin Report module.
 */
final class PluginReportCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a PluginReportCommands object.
   */
  public function __construct(
    private readonly PluginReportManagerInterface $pluginReportManager,
  ) {}

  /**
   * List all DefaultPluginManager services.
   *
   * @command plugin-report:managers
   * @field-labels
   *   id: ID
   *   provider: Provider
   *   alter_hook: Alter Hook
   *   subdir: Subdirectory
   *   discovery: Discovery
   *   interface: Interface
   *   class: Class
   * @default-table-fields id,provider,alter_hook,subdir
   * @filter-default-field id
   * @usage drush plugin-report:managers
   *   Table of all DefaultPluginManager services.
   * @usage drush plugin-report:managers --format=json
   *   JSON export of all plugin managers.
   * @usage drush plugin-report:managers --filter=provider=block
   *   Filter managers by provider.
   */
  public function managers(): RowsOfFields {
    return new RowsOfFields($this->pluginReportManager->getPluginManagers());
  }

  /**
   * List plugins for a plugin manager.
   *
   * @param string $plugin_manager
   *   Plugin manager service ID.
   *
   * @command plugin-report:plugins
   * @field-labels
   *   id: ID
   *   label: Label
   *   description: Description
   *   provider: Provider
   *   class: Class
   *   category: Category
   *   deriver: Deriver
   * @default-table-fields id,label,provider,class
   * @filter-default-field id
   * @usage drush plugin-report:plugins plugin.manager.block
   *   Table of block plugins.
   * @usage drush plugin-report:plugins plugin.manager.block --format=json
   *   JSON export of all block plugins.
   */
  public function plugins(string $plugin_manager): RowsOfFields {
    try {
      $plugins = $this->pluginReportManager->getPlugins($plugin_manager);
    }
    catch (\InvalidArgumentException $exception) {
      throw new \RuntimeException($exception->getMessage(), 0, $exception);
    }

    $rows = [];
    foreach ($plugins as $pluginId => $definition) {
      $row = [];
      foreach ($definition as $key => $value) {
        $row[$key] = $this->valueToString($value);
      }
      $rows[$pluginId] = $row;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Show detail for a single plugin.
   *
   * @param string $plugin_manager
   *   Plugin manager service ID.
   * @param string $plugin_id
   *   Plugin ID.
   *
   * @command plugin-report:plugin
   * @field-labels
   *   definition: Definition
   *   defaultConfiguration: Default Configuration
   *   getInfo: Element Info
   *   interfaces: Interfaces
   * @usage drush plugin-report:plugin plugin.manager.block system_menu_block
   *   Detail for the system_menu_block plugin.
   * @usage drush plugin-report:plugin plugin.manager.block system_menu_block --format=json
   *   JSON export of plugin detail.
   */
  public function plugin(string $plugin_manager, string $plugin_id): PropertyList {
    try {
      $data = $this->pluginReportManager->getPlugin($plugin_manager, $plugin_id);
    }
    catch (\InvalidArgumentException $exception) {
      throw new \RuntimeException($exception->getMessage(), 0, $exception);
    }

    $rows = [];
    foreach ($data as $section => $value) {
      $rows[$section] = $this->valueToString($value);
    }
    return new PropertyList($rows);
  }

  /**
   * Converts a value to a string suitable for Drush formatter output.
   *
   * Scalars and objects with __toString are cast directly. Arrays and other
   * objects are YAML-encoded after resolving any nested TranslatableMarkup.
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return string
   *   A displayable string representation.
   */
  private function valueToString(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) $value;
    }
    return Yaml::encode($this->prepareForYaml($value));
  }

  /**
   * Recursively prepares a value for YAML encoding.
   *
   * Resolves TranslatableMarkup and other stringable objects to plain strings
   * so that Yaml::encode() does not encounter raw PHP objects.
   *
   * @param mixed $value
   *   The value to prepare.
   *
   * @return mixed
   *   A YAML-safe value.
   */
  private function prepareForYaml(mixed $value): mixed {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (is_array($value)) {
      return array_map($this->prepareForYaml(...), $value);
    }
    if (is_object($value)) {
      return method_exists($value, '__toString') ? (string) $value : get_class($value);
    }
    return $value;
  }

}
