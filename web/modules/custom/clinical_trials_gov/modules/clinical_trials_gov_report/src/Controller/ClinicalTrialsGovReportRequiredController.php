<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov required metadata report.
 */
class ClinicalTrialsGovReportRequiredController extends ClinicalTrialsGovReportMetadataController {

  /**
   * Constructs a new ClinicalTrialsGovReportRequiredController instance.
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $configFactory,
    ClinicalTrialsGovStudyManagerInterface $studyManager,
    ClinicalTrialsGovPathsManagerInterface $pathsManager,
    DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($messenger, $configFactory, $studyManager, $pathsManager, $dateFormatter);
    $this->queryPaths = $this->getRequiredPaths();
  }

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new static(
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('clinical_trials_gov.study_manager'),
      $container->get('clinical_trials_gov.paths_manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIntro(): array {
    return [
      '#type' => 'item',
      '#markup' => $this->t('This page displays required ClinicalTrials.gov metadata configured in <a href=":settings_url">settings</a>.', [
        ':settings_url' => Url::fromRoute('clinical_trials_gov.settings')->toString(),
      ]),
    ];
  }

}
