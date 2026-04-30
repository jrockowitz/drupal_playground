<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovMetadataBaseController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov metadata report.
 */
class ClinicalTrialsGovReportMetadataController extends ClinicalTrialsGovMetadataBaseController {

  /**
   * {@inheritdoc}
   */
  protected bool $filter = FALSE;

  public function __construct(
    ClinicalTrialsGovManagerInterface $manager,
    ConfigFactoryInterface $configFactory,
    MessengerInterface $messenger,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($manager, $configFactory, $messenger);
  }

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore-next-line */
    return new self(
      $container->get('clinical_trials_gov.manager'),
      $container->get('config.factory'),
      $container->get('messenger'),
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
    $version = $this->manager->getVersion();
    $api_url = ClinicalTrialsGovApi::BASE_URL . '/studies/metadata';

    return [
      'api_url' => [
        '#type' => 'item',
        '#markup' => $this->t('ClinicalTrials.gov API: <a href=":url" class="font-monospace">@url</a>', [
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

  /**
   * Builds the version line markup.
   */
  protected function buildVersionMarkup(array $version): string {
    $api_version = (string) ($version['apiVersion'] ?? '');
    $timestamp = (string) ($version['dataTimestamp'] ?? '');
    $formatted_timestamp = $timestamp;

    if ($timestamp !== '') {
      $date_time = strtotime($timestamp . ' UTC');
      if ($date_time !== FALSE) {
        $formatted_timestamp = $this->dateFormatter->format($date_time, 'custom', 'F j Y \a\t g:i a');
      }
    }

    return '<small>' . $this->t('Version: @version and Last Updated: @updated', [
      '@version' => $api_version,
      '@updated' => $formatted_timestamp,
    ]) . '</small>';
  }

}
