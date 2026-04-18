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
   * Creates field storage (always up-to-date), field instance, AI automator,
   * and form/view display components. Steps 3–5 are skipped if the field
   * instance already existed.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node').
   * @param string $bundle
   *   The bundle machine name (e.g. 'page').
   */
  public function addFieldToEntity(string $entity_type_id, string $bundle): void;

}
