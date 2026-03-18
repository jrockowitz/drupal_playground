<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the entity_labels module.
 */
class EntityLabelsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'entity_labels.entity':
        return '<p>' . $this->t('The <strong>Entities</strong> label page lists all entity types filterable by individual entity type.') . '</p>';

      case 'entity_labels.entity.report':
        return '<p>' . $this->t('Displays entity and bundle labels for a specific entity type.') . '</p>';

      case 'entity_labels.entity.export':
        return '<p>' . $this->t('Exports entity labels as a downloadable CSV file.') . '</p>';

      case 'entity_labels.entity.import':
        return '<p>' . $this->t('Imports entity labels from an uploaded CSV file.') . '</p>';

      case 'entity_labels.field':
        return '<p>' . $this->t('The <strong>Fields</strong> label page lists all field labels filterable by entity type and bundle.') . '</p>';

      case 'entity_labels.field.report':
        $output = '<p>' . $this->t('Displays field labels for a specific entity type or bundle.') . '</p>';
        if ($route_match->getParameter('bundle') !== NULL) {
          $output .= '<p>' . $this->t('Note: %allowed_values and %field_type cannot be updated via import.', [
            '%allowed_values' => 'allowed_values',
            '%field_type' => 'field_type',
          ]) . '</p>';
        }
        return $output;

      case 'entity_labels.field.export':
        return '<p>' . $this->t('Exports field labels as a downloadable CSV file.') . '</p>';

      case 'entity_labels.field.import':
        return '<p>' . $this->t('Imports field labels from an uploaded CSV file.') . '</p>'
          . '<p>' . $this->t('Note: %allowed_values and %field_type columns are ignored during import.', [
            '%allowed_values' => 'allowed_values',
            '%field_type' => 'field_type',
          ]) . '</p>';
    }

    return '';
  }

}
