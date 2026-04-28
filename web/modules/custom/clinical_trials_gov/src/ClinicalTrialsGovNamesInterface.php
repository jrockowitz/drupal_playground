<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines naming helpers for ClinicalTrials.gov metadata pieces.
 */
interface ClinicalTrialsGovNamesInterface {

  /**
   * Normalizes a ClinicalTrials.gov piece into a Drupal-friendly stem.
   */
  public function normalizePiece(string $piece): string;

  /**
   * Builds a Drupal field machine name from a ClinicalTrials.gov piece.
   */
  public function getFieldName(string $piece): string;

  /**
   * Builds a Drupal field group machine name from a ClinicalTrials.gov piece.
   */
  public function getGroupName(string $piece): string;

  /**
   * Builds a human-readable label from a ClinicalTrials.gov piece or title.
   */
  public function getDisplayLabel(string $piece): string;

  /**
   * Builds a normalized custom-field detail label.
   */
  public function getDetailLabel(string $piece, string $parent_piece = ''): string;

}
