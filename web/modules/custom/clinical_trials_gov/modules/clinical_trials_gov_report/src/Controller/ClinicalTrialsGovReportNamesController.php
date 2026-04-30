<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovNames;
use Drupal\clinical_trials_gov\ClinicalTrialsGovNamesInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the ClinicalTrials.gov names report.
 */
class ClinicalTrialsGovReportNamesController extends ClinicalTrialsGovReportMetadataController {

  /**
   * Tracks truncated machine names for the current page build.
   */
  protected array $truncatedFieldNames = [];

  /**
   * Builds the names report page.
   */
  public function index(): array {
    $metadata = $this->getDisplayedMetadata();
    $results = $this->buildMetadataTable($metadata);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $this->getContainerClasses(),
      ],
      '#attached' => [
        'library' => $this->getAttachedLibraries(),
      ],
      'intro' => $this->buildIntro(),
      'summary' => [
        '#type' => 'item',
        '#markup' => $this->t('Showing @count fields', ['@count' => count($metadata)]),
      ],
      'abbreviations' => $this->buildAbbreviationsDetails(),
      'results' => $results,
    ];

    $footer = $this->buildFooter();
    if ($footer) {
      $build['footer'] = $footer;
    }

    return $build;
  }

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
    $build = [
      'description' => [
        '#type' => 'item',
        '#markup' => $this->t('This page displays how ClinicalTrials.gov identifiers are converted into Drupal labels and machine names.'),
      ],
    ];

    if ($this->truncatedFieldNames) {
      $items = [];
      foreach ($this->truncatedFieldNames as $piece => $values) {
        $items[] = $piece . ' => ' . $values['drupal_name'] . ' => ' . $values['field_name'];
      }

      $build['warning'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          '#value' => (string) $this->t('The following Drupal field names were truncated.'),
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the abbreviations details widget.
   */
  protected function buildAbbreviationsDetails(): array {
    $rows = [];

    foreach (ClinicalTrialsGovNames::ABBREVIATIONS as $token => $abbreviation) {
      $rows[] = [
        $token,
        $abbreviation,
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Abbreviations'),
      'table' => [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['clinical-trials-gov-table'],
        ],
        '#header' => [
          $this->t('Full token'),
          $this->t('Abbreviation'),
        ],
        '#rows' => $rows,
      ],
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
    $this->truncatedFieldNames = [];

    foreach ($metadata as $path => $row) {
      if (!is_array($row)) {
        continue;
      }

      $piece = (string) ($row['piece'] ?? '');
      $drupal_name = $this->names->normalizePiece($piece);
      $drupal_field_name = $this->buildDrupalFieldName((string) $path, $piece);
      $row_classes = [];

      if ($drupal_name && !str_ends_with($drupal_field_name, $drupal_name)) {
        $row_classes[] = 'color-warning';
        $this->truncatedFieldNames[$piece] = [
          'drupal_name' => $drupal_name,
          'field_name' => $drupal_field_name,
        ];
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
      '#attributes' => [
        'class' => ['clinical-trials-gov-table'],
      ],
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
