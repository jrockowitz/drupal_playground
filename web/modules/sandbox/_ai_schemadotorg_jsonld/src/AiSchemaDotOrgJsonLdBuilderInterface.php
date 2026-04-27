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
   * Adds the Schema.org JSON-LD field to one or more bundles.
   *
   * Bundle lists support explicit bundle IDs or a single '*' wildcard to target
   * all current bundles of the supported entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node').
   * @param array $bundles
   *   The bundle machine names or ['*'].
   */
  public function addFieldToBundles(string $entity_type_id, array $bundles): void;

  /**
   * Adds the Schema.org JSON-LD field and related config to an entity bundle.
   *
   * This method initializes missing entity type settings automatically before
   * creating the field, automator, and display configuration for the bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node').
   * @param string $bundle
   *   The bundle machine name (e.g. 'page').
   */
  public function addFieldToBundle(string $entity_type_id, string $bundle): void;

}
