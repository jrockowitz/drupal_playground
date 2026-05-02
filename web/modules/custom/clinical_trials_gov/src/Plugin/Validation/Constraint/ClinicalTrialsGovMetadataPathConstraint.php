<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that a ClinicalTrials.gov metadata path exists.
 */
#[Constraint(
  id: 'ClinicalTrialsGovMetadataPath',
  label: new TranslatableMarkup('ClinicalTrials.gov metadata path', [], ['context' => 'Validation']),
  type: ['string']
)]
class ClinicalTrialsGovMetadataPathConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   */
  public string $message = 'The metadata path "%value" is not valid.';

}
