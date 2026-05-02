<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Converts ClinicalTrials.gov pieces into Drupal names and labels.
 */
class ClinicalTrialsGovNames implements ClinicalTrialsGovNamesInterface {

  /**
   * Abbreviations for normalized snake_case name tokens.
   */
  public const ABBREVIATIONS = [
    // Phrases.
    'denom_count_group' => 'de_count_grp',
    'event_group' => 'evt_grp',
    'intervention_browse' => 'int_brow',
    'other_event' => 'oth_evt',
    'outcome_analysis_ci' => 'out_anl_ci',
    'serious_event' => 'ser_evt',
    'unposted_event' => 'unposted_evt',
    // Words.
    'access' => 'acc',
    'achievement' => 'achieve',
    'affected' => 'aff',
    'affiliation' => 'aff',
    'agreement' => 'agree',
    'analysis' => 'anal',
    'analyze' => 'anal',
    'analyzed' => 'anal',
    'anticipated' => 'ant',
    'assignment' => 'assign',
    'baseline' => 'base',
    'collaborator' => 'collab',
    'comment' => 'com',
    'completion' => 'comp',
    'condition' => 'cond',
    'creation' => 'create',
    'description' => 'desc',
    'estimated' => 'est',
    'expanded' => 'exp',
    'identification' => 'id',
    'inferiority' => 'inf',
    'intervention' => 'int',
    'investigator' => 'inv',
    'limit' => 'lim',
    'limitations' => 'lim',
    'measure' => 'meas',
    'module' => 'mod',
    'mortality' => 'mort',
    'organization' => 'org',
    'outcome' => 'out',
    'population' => 'pop',
    'primary' => 'prim',
    'reference' => 'ref',
    'references' => 'ref',
    'regulated' => 'reg',
    'responsible' => 'resp',
    'results' => 'res',
    'selected' => 'sel',
    'serious' => 'ser',
    'statistical' => 'sta',
    'struct' => 'str',
    'submission' => 'sub',
    'value' => 'val',
    'violation' => 'viol',
  ];

  /**
   * Friendly field-name stems keyed by ClinicalTrials.gov piece.
   */
  protected const FIELD_NAMES = [
    'NCTId' => 'nct_id',
    'NCTIdAlias' => 'nct_id_alias',
    'IPDSharingStatement' => 'ipd_sharing_statement',
    'IPDSharingTimeFrame' => 'ipd_sharing_time_frame',
    'IPDSharingAccessCriteria' => 'ipd_sharing_access_crit',
    'NPtrsToThisExpAccNCTId' => 'nptrs_exp_act_nct_id',
  ];

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

  /**
   * Constructs a new ClinicalTrialsGovNames service.
   */
  public function __construct(
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ConfigFactoryInterface $configFactory,
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
    $piece = trim($piece, '_');

    foreach (self::ABBREVIATIONS as $token => $abbreviation) {
      $piece = preg_replace('#(^|_)' . $token . '(_|$)#', '$1' . $abbreviation . '$2', $piece);
    }

    return $piece;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(string $piece): string {
    return $this->buildMachineName($this->getConfiguredFieldPrefix(), $piece, self::FIELD_NAME_MAX_LENGTH);
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
    $metadata = $this->studyManager->getMetadataByPiece($piece);
    $title = (string) ($metadata['title'] ?? '');
    if ($title) {
      return $title;
    }

    if (!$piece || str_contains($piece, ' ') || str_contains($piece, '-')) {
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
    if ($parent_piece && str_starts_with($piece, $parent_piece)) {
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

  /**
   * Returns the configured Drupal field prefix.
   */
  protected function getConfiguredFieldPrefix(): string {
    $field_prefix = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('field_prefix');
    $field_prefix = trim($field_prefix);

    if (!$field_prefix) {
      return '';
    }

    return $field_prefix . '_';
  }

}
