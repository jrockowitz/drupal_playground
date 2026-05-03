<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\ConfigAction;

use Drupal\clinical_trials_gov\ClinicalTrialsGovSetupManagerInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs the ClinicalTrials.gov setup workflow from a config action.
 */
#[ConfigAction(
  id: 'setUp',
  admin_label: new TranslatableMarkup('Set up ClinicalTrials.gov settings'),
)]
class SetUp implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new SetUp config action.
   */
  public function __construct(
    protected ClinicalTrialsGovSetupManagerInterface $setupManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('clinical_trials_gov.setup_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    if ($configName !== 'clinical_trials_gov.settings') {
      throw new ConfigActionException("The setUp config action only supports clinical_trials_gov.settings.");
    }
    if (!is_array($value)) {
      throw new ConfigActionException('The setUp config action requires an array of settings.');
    }
    if (trim((string) ($value['query'] ?? '')) === '') {
      throw new ConfigActionException('The setUp config action requires a query.');
    }

    $this->setupManager->setUp($value);
  }

}
