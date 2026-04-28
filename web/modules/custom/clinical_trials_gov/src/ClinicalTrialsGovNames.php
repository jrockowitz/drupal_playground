<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Converts ClinicalTrials.gov pieces into Drupal names and labels.
 */
class ClinicalTrialsGovNames implements ClinicalTrialsGovNamesInterface {

  /**
   * Friendly field-name stems keyed by ClinicalTrials.gov piece.
   */
  protected const FIELD_NAMES = [
    'NCTId' => 'nct_id',
    'NCTIdAlias' => 'nct_id_alias',
    'BriefTitle' => 'brief_title',
    'BriefSummary' => 'brief_summary',
    'OfficialTitle' => 'official_title',
    'OrgStudyIdInfo' => 'org_study_id_info',
    'IPDSharingStatement' => 'ipd_sharing_statement',
    'IPDSharingTimeFrame' => 'ipd_sharing_time_frame',
    'IPDSharingAccessCriteria' => 'ipd_sharing_access_criteria',
  ];

  /**
   * The Drupal field prefix.
   */
  protected const FIELD_PREFIX = 'field_';

  /**
   * The Drupal field group prefix.
   */
  protected const GROUP_PREFIX = 'group_';

  /**
   * The Drupal field name max length.
   */
  protected const FIELD_NAME_MAX_LENGTH = 32;

  /**
   * The Drupal field group name max length.
   */
  protected const GROUP_NAME_MAX_LENGTH = 64;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalizePiece(string $piece): string {
    if (isset(self::FIELD_NAMES[$piece])) {
      return self::FIELD_NAMES[$piece];
    }

    $piece = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', '_', $piece) ?? $piece;
    $piece = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $piece) ?? $piece;
    $piece = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $piece) ?? '');

    return trim($piece, '_');
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(string $piece): string {
    return $this->buildMachineName(self::FIELD_PREFIX, $piece, self::FIELD_NAME_MAX_LENGTH);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupName(string $piece): string {
    return $this->buildMachineName(self::GROUP_PREFIX, $piece, self::GROUP_NAME_MAX_LENGTH);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(string $piece): string {
    $metadata = $this->manager->getMetadataByPiece($piece);
    $title = (string) ($metadata['title'] ?? '');
    if ($title !== '') {
      return $title;
    }

    if ($piece === '' || str_contains($piece, ' ') || str_contains($piece, '-')) {
      return $piece;
    }

    $piece = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $piece) ?? $piece;
    $piece = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $piece) ?? $piece;

    return trim($piece);
  }

  /**
   * {@inheritdoc}
   */
  public function getDetailLabel(string $piece, string $parent_piece = ''): string {
    if ($parent_piece !== '' && str_starts_with($piece, $parent_piece)) {
      $piece = substr($piece, strlen($parent_piece)) ?: $piece;
    }

    return $this->normalizePiece($piece);
  }

  /**
   * Builds a deterministic machine name from a ClinicalTrials.gov piece.
   */
  protected function buildMachineName(string $prefix, string $piece, int $max_length): string {
    $machine_name = $prefix . $this->normalizePiece($piece);
    if (strlen($machine_name) <= $max_length) {
      return $machine_name;
    }

    $hash = substr(hash('sha256', $piece), 0, 8);
    $prefix_length = $max_length - 1 - strlen($hash);
    $prefix_value = substr($machine_name, 0, $prefix_length);

    return rtrim($prefix_value, '_') . '_' . $hash;
  }

}
