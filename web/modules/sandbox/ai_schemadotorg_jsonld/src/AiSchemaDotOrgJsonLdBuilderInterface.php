<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

/**
 * Interface for the AI Schema.org JSON-LD builder service.
 */
interface AiSchemaDotOrgJsonLdBuilderInterface {

  /**
   * The name of the Schema.org JSON-LD field.
   */
  const FIELD_NAME = 'field_schemadotorg_jsonld';

  /**
   * Adds the Schema.org JSON-LD field and related config to an entity bundle.
   *
   * This orchestration method:
   * 1. Creates or updates the field storage configuration.
   * 2. Creates the field instance for the specific bundle.
   * 3. Configures the AI automator for the field (skipped if one already
   *    exists, to avoid overwriting manual customizations).
   * 4. Adds the field to the default form display with the 'json_editor' or
   *    'json_textarea' widget and the automator button (skipped if the
   *    component is already present).
   * 5. Adds the field to the default view display with the 'json' formatter
   *    (skipped if the component is already present).
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node').
   * @param string $bundle
   *   The bundle machine name (e.g. 'page').
   */
  public function addFieldToEntity(string $entity_type_id, string $bundle): void;

}
