<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Fetches and organizes data from the ClinicalTrials.gov API.
 */
class ClinicalTrialsGovManager implements ClinicalTrialsGovManagerInterface {

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
  public function getStudy(string $nct_id): array {
    $data = $this->api->get('/studies/' . $nct_id);
    return $this->flattenStudy($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyMetadata(): array {
    static $metadata = NULL;
    if ($metadata === NULL) {
      $data = $this->api->get('/studies/metadata');
      $metadata = $this->flattenMetadata($data);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyFieldMetadata(string $index_field): ?array {
    return $this->getStudyMetadata()[$index_field] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnums(): array {
    static $enums = NULL;
    if ($enums === NULL) {
      $enums = $this->api->get('/studies/enums');
    }
    return is_array($enums) ? $enums : [];
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
   * Recursively flattens nested study data to dot-notation Index field keys.
   *
   * Associative arrays (objects) are recursed into. Lists and scalar values
   * are stored as-is under their full dotted key path.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }
    return [$prefix => $data];
  }

  /**
   * Recursively flattens the metadata tree to dot-notation name-path rows.
   */
  protected function flattenMetadata(array $items, string $prefix = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $key = ($prefix !== '' && $name !== '') ? $prefix . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if ($child_name === '') {
          continue;
        }
        $children[] = ($key !== '') ? $key . '.' . $child_name : $child_name;
      }
      $rows[$key] = [
        'key' => $key,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $key);
      }
    }
    return $rows;
  }

}
