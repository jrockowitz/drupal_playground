<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Fetches and organizes data from the ClinicalTrials.gov API.
 */
class ClinicalTrialsGovManager implements ClinicalTrialsGovManagerInterface {

  /**
   * Per-instance cache of flattened study arrays, keyed by NCT ID.
   */
  protected array $studyCache = [];

  /**
   * Per-instance cache of the flattened metadata tree.
   */
  protected ?array $metadataByPathCache = NULL;

  /**
   * Per-instance cache of metadata rows keyed by piece.
   */
  protected ?array $metadataByPieceCache = NULL;

  /**
   * Per-instance cache of the raw enums response.
   */
  protected ?array $enumsCache = NULL;

  public function __construct(
    protected ClinicalTrialsGovApiInterface $api,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStudies(array $parameters): array {
    return $this->api->get('/studies', $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): array {
    return $this->api->get('/version');
  }

  /**
   * {@inheritdoc}
   */
  public function getStudy(string $nct_id): array {
    if (!array_key_exists($nct_id, $this->studyCache)) {
      $data = $this->api->get('/studies/' . $nct_id);
      $this->studyCache[$nct_id] = $this->flattenStudy($data);
    }
    return $this->studyCache[$nct_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataByPath(?string $path = NULL): array {
    if ($this->metadataByPathCache === NULL) {
      $data = $this->api->get('/studies/metadata');
      $this->metadataByPathCache = $this->flattenMetadata($data);
    }
    if ($path === NULL) {
      return $this->metadataByPathCache;
    }
    return $this->metadataByPathCache[$path] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataByPiece(?string $piece = NULL): array {
    if ($this->metadataByPieceCache === NULL) {
      $this->metadataByPieceCache = [];
      foreach ($this->getMetadataByPath() as $metadata) {
        if (!is_array($metadata)) {
          continue;
        }
        $metadata_piece = (string) ($metadata['piece'] ?? '');
        if (!$metadata_piece) {
          continue;
        }
        $this->metadataByPieceCache[$metadata_piece] = $metadata;
      }
    }
    if ($piece === NULL) {
      return $this->metadataByPieceCache;
    }
    return $this->metadataByPieceCache[$piece] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnums(): array {
    if ($this->enumsCache === NULL) {
      $this->enumsCache = $this->api->get('/studies/enums');
    }
    return $this->enumsCache;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnum(string $enum_type): array {
    foreach ($this->getEnums() as $enum) {
      if (!is_array($enum)) {
        continue;
      }
      if (($enum['type'] ?? '') === $enum_type) {
        return array_values(array_filter(
          array_map(
            fn($item) => is_array($item) ? ($item['value'] ?? NULL) : (is_string($item) ? $item : NULL),
            is_array($enum['values'] ?? NULL) ? $enum['values'] : []
          ),
          fn($value) => $value !== NULL
        ));
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumAsAllowedValues(string $enum_type, bool $key_label = FALSE): array {
    foreach ($this->getEnums() as $enum_definition) {
      if (!is_array($enum_definition) || ($enum_definition['type'] ?? '') !== $enum_type) {
        continue;
      }

      $allowed_values = [];
      foreach (($enum_definition['values'] ?? []) as $value) {
        if (!is_array($value) || !isset($value['value'])) {
          continue;
        }

        $allowed_values[(string) $value['value']] = (string) ($value['legacyValue'] ?? $value['value']);
      }

      if (!$key_label) {
        return $allowed_values;
      }

      $normalized = [];
      foreach ($allowed_values as $key => $label) {
        $normalized[] = [
          'key' => $key,
          'label' => $label,
        ];
      }
      return $normalized;
    }

    return [];
  }

  /**
   * Recursively flattens nested study data to dot-notation Index field keys.
   *
   * Associative arrays (objects) are recursed into. Lists and scalar values
   * are stored as-is under their full dotted key path.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix) ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }

    if (is_array($data) && array_is_list($data)) {
      $result = [$prefix => $data];
      foreach ($data as $item) {
        if (!is_array($item) || array_is_list($item)) {
          continue;
        }
        $result += $this->flattenStudy($item, $prefix);
      }
      return $result;
    }

    return [$prefix => $data];
  }

  /**
   * Recursively flattens the metadata tree to dot-notation name-path rows.
   */
  protected function flattenMetadata(array $items, string $parent = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $path = ($parent && $name) ? $parent . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if (!$child_name) {
          continue;
        }
        $children[] = ($path) ? $path . '.' . $child_name : $child_name;
      }
      $rows[$path] = [
        'path' => $path,
        'parent' => $parent,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'maxChars' => isset($item['maxChars']) ? (int) $item['maxChars'] : NULL,
        'isEnum' => !empty($item['isEnum']),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
        'rules' => (string) ($item['rules'] ?? ''),
        'altPieceNames' => array_values(array_filter(
          is_array($item['altPieceNames'] ?? NULL) ? $item['altPieceNames'] : [],
          fn(mixed $value): bool => is_string($value) && $value !== ''
        )),
        'synonyms' => !empty($item['synonyms']),
        'dedLinkLabel' => (string) (($item['dedLink']['label'] ?? '')),
        'dedLinkUrl' => (string) (($item['dedLink']['url'] ?? '')),
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $path);
      }
    }
    return $rows;
  }

}
