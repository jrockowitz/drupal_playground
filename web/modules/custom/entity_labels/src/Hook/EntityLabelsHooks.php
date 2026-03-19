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
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'entity_labels.entity':
      case 'entity_labels.entity.report':
        return '<p>' . $this->t('The <strong>Entity labels</strong> export page lists all entity types filterable by individual entity type.') . '</p>';

      case 'entity_labels.entity.import':
        return '<p>' . $this->t('The <strong>Entity labels</strong> import form updates entity labels from an uploaded CSV file.') . '</p>';

      case 'entity_labels.field':
      case 'entity_labels.field.report':
        $t_args = [
          '%allowed_values' => 'allowed_values',
          '%field_type' => 'field_type',
        ];
        return '<p>'
          . $this->t('The <strong>Field labels</strong> export page lists all field labels filterable by entity type and bundle.')
          . '</br><em>'
          . $this->t('Note: %allowed_values and %field_type cannot be updated via import.', $t_args)
          . '</em></p>';

      case 'entity_labels.field.import':
        $t_args = [
          '@allowed_values' => 'allowed_values',
          '@field_type' => 'field_type',
        ];
        return '<p>'
          . $this->t('The <strong>Field labels</strong> import form updates field labels from an uploaded CSV file.')
          . '</br><em>'
          . $this->t('Note: @allowed_values and @field_type columns are ignored during import.', $t_args)
          . '</em></p>';

      default:
        return NULL;
    }

  }

}
