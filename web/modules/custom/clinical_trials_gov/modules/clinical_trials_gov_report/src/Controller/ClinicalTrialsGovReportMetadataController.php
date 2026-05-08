<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovMetadataBaseController;
use Drupal\clinical_trials_gov_report\Traits\ClinicalTrialsGovReportMarkupTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov metadata report.
 */
class ClinicalTrialsGovReportMetadataController extends ClinicalTrialsGovMetadataBaseController {

  use ClinicalTrialsGovReportMarkupTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $filterByQueryPaths = FALSE;

  /**
   * Constructs a new ClinicalTrialsGovReportMetadataController instance.
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $configFactory,
    ClinicalTrialsGovStudyManagerInterface $studyManager,
    ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($messenger, $configFactory, $studyManager, $pathsManager);
  }

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new self(
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
      '#markup' => $this->t('This page displays flattened ClinicalTrials.gov fields metadata returned by the API.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFooter(): array {
    $version = $this->studyManager->getVersion();
    $api_url = ClinicalTrialsGovApi::BASE_URL . '/studies/metadata';

    return [
      'api_url' => [
        '#type' => 'item',
        '#markup' => $this->t('<small>ClinicalTrials.gov API: <a href=":url" class="font-monospace">@url</a></small>', [
          ':url' => $api_url,
          '@url' => $api_url,
        ]),
      ],
      'version_separator' => [
        '#type' => 'html_tag',
        '#tag' => 'hr',
      ],
      'version' => [
        '#type' => 'item',
        '#markup' => $this->buildVersionMarkup($version),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAttachedLibraries(): array {
    return [
      'clinical_trials_gov_report/report',
    ];
  }

}
