<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\Validation\Constraint;

use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates configured ClinicalTrials.gov metadata paths.
 */
class ClinicalTrialsGovMetadataPathConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a new validator.
   */
  public function __construct(
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
  ) {}

  /**
   * Creates the validator from the container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.study_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!is_string($value) || $value === '') {
      return;
    }
    assert($constraint instanceof ClinicalTrialsGovMetadataPathConstraint);

    $metadata = $this->studyManager->getMetadataByPath();
    if (!isset($metadata[$value])) {
      $this->context->addViolation($constraint->message, [
        '%value' => $value,
      ]);
    }
  }

}
