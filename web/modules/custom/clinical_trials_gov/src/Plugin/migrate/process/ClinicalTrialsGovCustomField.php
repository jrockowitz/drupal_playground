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
    if (array_is_list($value) && count($value) === 2 && is_array($value[1])) {
      $yaml_columns = $value[1];
      $value = $value[0];
    }
    elseif (isset($this->configuration['yaml_columns']) && is_array($this->configuration['yaml_columns'])) {
      $yaml_columns = $this->configuration['yaml_columns'];
    }

    if (!is_array($value) || $yaml_columns === []) {
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
   * Serializes one nested value to YAML with compact list-item formatting.
   */
  protected function serializeYaml(array $value): string {
    $yaml = Yaml::encode($value);

    // Remove the line break after one list item delimiter when the item starts
    // with a plain mapping key or quoted scalar.
    return preg_replace('#((?:\n|^)[ ]*-)\n[ ]+([A-Za-z0-9_]|[\'"])#', '\1 \2', $yaml) ?? $yaml;
  }

}
