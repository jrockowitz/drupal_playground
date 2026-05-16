<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_fields\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Node hook implementations for ClinicalTrials.gov fixed fields.
 */
class ClinicalTrialsGovFieldsNodeHooks {

  /**
   * Constructs a new ClinicalTrialsGovFieldsNodeHooks instance.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_node_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    $type = $this->configFactory->get('clinical_trials_gov.settings')->get('type');
    if (($node->bundle() !== $type) || !$node->hasField('field_trial_title')) {
      return;
    }

    $title = $node->get('field_trial_title')->value;
    if (is_string($title) && $title !== '') {
      $node->setTitle($title);
    }
  }

}
