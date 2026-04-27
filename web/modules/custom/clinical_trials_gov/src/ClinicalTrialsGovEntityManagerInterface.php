<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines entity-management helpers for the import wizard.
 */
interface ClinicalTrialsGovEntityManagerInterface {

  /**
   * The default content type machine name.
   */
  public const DEFAULT_CONTENT_TYPE = 'trial';

  /**
   * Creates the content type when needed.
   */
  public function createContentType(string $type, string $label, string $description): void;

  /**
   * Creates selected fields for the destination content type.
   */
  public function createFields(string $type, array $fields): void;

  /**
   * Returns whether the field_group module can be used for nested structs.
   */
  public function supportsFieldGroups(): bool;

  /**
   * Generates a deterministic Drupal field machine name for an API key.
   */
  public function generateFieldName(string $api_key): string;

  /**
   * Resolves a selectable API key into Drupal field metadata.
   *
   * @return array
   *   A normalized field definition.
   */
  public function resolveFieldDefinition(string $api_key): array;

  /**
   * Resolves a whitelisted structured key into custom-field column settings.
   *
   * @return array|null
   *   The custom field definition, or NULL if unsupported.
   */
  public function resolveStructuredFieldDefinition(string $api_key): ?array;

}
