<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Entity;

use Drupal\clinical_trials_gov\Traits\ClinicalTrialsGovNodeAccessTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;

/**
 * Blocks manual creation of the ClinicalTrials.gov bundle.
 */
class ClinicalTrialsGovNodeAccessControlHandler extends NodeAccessControlHandler {

  use ClinicalTrialsGovNodeAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $access = $this->checkClinicalTrialsGovCreateAccess(is_string($entity_bundle) ? $entity_bundle : NULL);
    if ($access->isForbidden()) {
      return $return_as_object ? $access : $access->isAllowed();
    }

    return parent::createAccess($entity_bundle, $account, $context, $return_as_object);
  }

}
