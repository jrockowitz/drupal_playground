<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\migrate\process;

use Drupal\Component\Serialization\Yaml;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Applies ClinicalTrials.gov custom field value transformations.
 */
#[MigrateProcess(id: 'clinical_trials_gov_custom_field')]
class ClinicalTrialsGovCustomField extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    if (!is_array($value)) {
      return $value;
    }

    $yaml_columns = [];
    $source_key_map = [];
    if (array_is_list($value)) {
      if (isset($value[1]) && is_array($value[1])) {
        $yaml_columns = $value[1];
      }
      if (isset($value[2]) && is_array($value[2])) {
        $source_key_map = $value[2];
      }
      if ((isset($value[1]) && is_array($value[1])) || (isset($value[2]) && is_array($value[2]))) {
        $value = $value[0];
      }
    }
    else {
      if (isset($this->configuration['yaml_columns']) && is_array($this->configuration['yaml_columns'])) {
        $yaml_columns = $this->configuration['yaml_columns'];
      }
      if (isset($this->configuration['source_key_map']) && is_array($this->configuration['source_key_map'])) {
        $source_key_map = $this->configuration['source_key_map'];
      }
    }

    if (!is_array($value)) {
      return $value;
    }

    if ($source_key_map) {
      $value = $this->remapSourceKeys($value, $source_key_map);
    }

    if (!$yaml_columns) {
      return $value;
    }

    foreach ($yaml_columns as $column_name) {
      if (!is_string($column_name)) {
        continue;
      }

      if (array_is_list($value)) {
        foreach ($value as &$item) {
          if (is_array($item) && isset($item[$column_name]) && is_array($item[$column_name])) {
            $item[$column_name] = $this->serializeYaml($item[$column_name]);
          }
        }
        unset($item);
        continue;
      }

      if (isset($value[$column_name]) && is_array($value[$column_name])) {
        $value[$column_name] = $this->serializeYaml($value[$column_name]);
      }
    }

    return $value;
  }

  /**
   * Remaps source struct keys to the generated custom field property names.
   */
  protected function remapSourceKeys(array $value, array $source_key_map): array {
    if (array_is_list($value)) {
      foreach ($value as $delta => $item) {
        if (!is_array($item)) {
          continue;
        }
        $value[$delta] = $this->remapAssociativeArray($item, $source_key_map);
      }
      return $value;
    }

    return $this->remapAssociativeArray($value, $source_key_map);
  }

  /**
   * Remaps one associative source array to destination property keys.
   */
  protected function remapAssociativeArray(array $value, array $source_key_map): array {
    $remapped_value = [];

    foreach ($value as $key => $item) {
      if (is_string($key) && isset($source_key_map[$key]) && is_string($source_key_map[$key])) {
        $remapped_value[$source_key_map[$key]] = $item;
        continue;
      }

      $remapped_value[$key] = $item;
    }

    return $remapped_value;
  }

  /**
   * Serializes one nested value to YAML with compact list-item formatting.
   */
  protected function serializeYaml(array $value): string {
    $yaml = Yaml::encode($value);

    // Remove the line break after one list item delimiter when the item starts
    // with a plain mapping key or quoted scalar.
    return preg_replace('#((?:\n|^)[ ]*-)\n[ ]+([A-Za-z0-9_]|[\'"])#', '\1 \2', $yaml) ?? $yaml;
  }

}
