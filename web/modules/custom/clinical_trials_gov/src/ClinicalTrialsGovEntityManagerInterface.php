<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines entity-management helpers for the import wizard.
 */
interface ClinicalTrialsGovEntityManagerInterface {

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
   * Generates a deterministic Drupal field machine name for a metadata path.
   */
  public function generateFieldName(string $path): string;

  /**
   * Returns the generated ClinicalTrials.gov URL field machine name.
   */
  public function getStudyUrlFieldName(): string;

  /**
   * Returns the generated ClinicalTrials.gov API field machine name.
   */
  public function getStudyApiFieldName(): string;

  /**
   * Resolves a selectable metadata path into Drupal field metadata.
   *
   * @return array
   *   A normalized field definition.
   */
  public function resolveFieldDefinition(string $path): array;

  /**
   * Resolves a whitelisted structured key into custom-field column settings.
   *
   * @return array|null
   *   The custom field definition, or NULL if unsupported.
   */
  public function resolveStructuredFieldDefinition(string $path): ?array;

}
