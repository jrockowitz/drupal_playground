<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovNamesInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov names report.
 */
class ClinicalTrialsGovReportNamesController extends ClinicalTrialsGovReportMetadataController {

  public function __construct(
    ClinicalTrialsGovManagerInterface $manager,
    ConfigFactoryInterface $configFactory,
    MessengerInterface $messenger,
    DateFormatterInterface $dateFormatter,
    protected ClinicalTrialsGovNamesInterface $names,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
  ) {
    parent::__construct($manager, $configFactory, $messenger, $dateFormatter);
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
      $container->get('clinical_trials_gov.names'),
      $container->get('clinical_trials_gov.field_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIntro(): array {
    return [
      '#type' => 'item',
      '#markup' => $this->t('This page displays how ClinicalTrials.gov identifiers are converted into Drupal labels and machine names.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerClasses(): array {
    return ['clinical-trials-gov-report-names'];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMetadataTable(array $metadata): array {
    $rows = [];

    foreach ($metadata as $path => $row) {
      if (!is_array($row)) {
        continue;
      }

      $piece = (string) ($row['piece'] ?? '');
      $drupal_name = $this->names->normalizePiece($piece);
      $drupal_field_name = $this->buildDrupalFieldName((string) $path, $piece);
      $row_classes = [];

      if (($drupal_name !== '') && !str_ends_with($drupal_field_name, $drupal_name)) {
        $row_classes[] = 'color-warning';
      }

      $rows[] = [
        'data' => [
          $this->buildPrimarySecondaryCell(
            primary: (string) ($row['title'] ?? ''),
            secondary: '',
            depth: substr_count((string) ($row['path'] ?? ''), '.'),
          ),
          $this->buildTextCell($piece),
          $this->buildTextCell($this->names->getDisplayLabel($piece)),
          $this->buildTextCell($drupal_name),
          $this->buildTextCell($drupal_field_name),
        ],
        'class' => $row_classes,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('ClinicalTrials.gov Field Title'),
        $this->t('ClinicalTrials.gov Identifier'),
        $this->t('Drupal Field Label'),
        $this->t('Drupal Name'),
        $this->t('Drupal Field Name'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No metadata returned.'),
    ];
  }

  /**
   * Builds the report field name for one metadata row.
   */
  protected function buildDrupalFieldName(string $path, string $piece): string {
    $definition = $this->fieldManager->resolveFieldDefinition($path);

    if (!empty($definition['group_only']) || (($definition['field_type'] ?? '') === 'field_group')) {
      return $this->names->getGroupName($piece);
    }

    return $this->names->getFieldName($piece);
  }

}
