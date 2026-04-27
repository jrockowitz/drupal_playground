<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Converts ClinicalTrials.gov API study data into Drupal render arrays.
 */
class ClinicalTrialsGovBuilder implements ClinicalTrialsGovBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildStudiesList(array $studies, ?string $study_route = NULL): array {
    $rows = [];
    foreach ($studies as $study) {
      $identification = $study['protocolSection']['identificationModule'] ?? [];
      $status_module = $study['protocolSection']['statusModule'] ?? [];
      $design_module = $study['protocolSection']['designModule'] ?? [];
      $conditions_module = $study['protocolSection']['conditionsModule'] ?? [];

      $nct_id = $identification['nctId'] ?? '';
      $title = $identification['briefTitle'] ?? '';
      $status = $status_module['overallStatus'] ?? '';
      $phases = implode(', ', $design_module['phases'] ?? []);
      $conditions = implode(', ', $conditions_module['conditions'] ?? []);

      if ($nct_id !== '' && $study_route !== NULL) {
        $nct_cell = $this->t('<a href=":url">@nct</a>', [
          ':url' => Url::fromRoute($study_route, ['nctId' => $nct_id])->toString(),
          '@nct' => $nct_id,
        ]);
      }
      else {
        $nct_cell = $nct_id;
      }

      $rows[] = [$nct_cell, $title, $status, $phases, $conditions];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('NCT ID'),
        $this->t('Title'),
        $this->t('Overall status'),
        $this->t('Phases'),
        $this->t('Conditions'),
      ],
      '#rows' => $rows,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildStudy(array $study, string $nct_id): array {
    $metadata = $this->manager->getStudyMetadata();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov-report-study'],
      ],
      'summary' => [
        '#type' => 'details',
        '#title' => $this->t('Study summary'),
        '#open' => TRUE,
        'content' => $this->buildSummary($study),
      ],
      'data_table' => $this->buildDataTable($study, $metadata),
      'study_link' => $this->buildStudyLink($nct_id),
      'api_url' => $this->buildApiUrl($nct_id),
    ];
  }

  /**
   * Builds the study summary section.
   */
  protected function buildSummary(array $study): array {
    $build = [
      '#type' => 'container',
    ];

    $status = (string) $this->getStudyValue($study, 'protocolSection.statusModule.overallStatus', '');
    $phases = $this->normalizeStringList($this->getStudyValue($study, 'protocolSection.designModule.phases', []));
    $study_type = (string) $this->getStudyValue($study, 'protocolSection.designModule.studyType', '');
    $nct_id = (string) $this->getStudyValue($study, 'protocolSection.identificationModule.nctId', '');
    $lead_sponsor = (string) $this->getStudyValue($study, 'protocolSection.sponsorCollaboratorsModule.leadSponsor.name', '');

    $overview_items = [];
    if ($status !== '') {
      $overview_items[] = $this->buildLabelValueMarkup((string) $this->t('Status'), $status);
    }
    if ($phases !== []) {
      $overview_items[] = $this->buildLabelValueMarkup((string) $this->t('Phases'), implode(', ', $phases));
    }
    if ($study_type !== '') {
      $overview_items[] = $this->buildLabelValueMarkup((string) $this->t('Study type'), $study_type);
    }
    $identity_items = [];
    if ($nct_id !== '') {
      $identity_items[] = $this->buildLabelValueMarkup((string) $this->t('NCT ID'), $nct_id);
    }
    if ($lead_sponsor !== '') {
      $identity_items[] = $this->buildLabelValueMarkup((string) $this->t('Sponsor'), $lead_sponsor);
    }

    $facts_items = $this->buildFactsItems($study);
    $overview_items = array_merge($overview_items, $identity_items, $facts_items);
    if ($overview_items !== []) {
      $build['overview'] = $this->buildSummaryFieldset((string) $this->t('Study overview'), $overview_items);
    }

    $conditions = $this->normalizeStringList($this->getStudyValue($study, 'protocolSection.conditionsModule.conditions', []));
    if ($conditions !== []) {
      $build['conditions'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Conditions'),
        'items' => [
          '#theme' => 'item_list',
          '#items' => $conditions,
        ],
      ];
    }

    $brief_summary = (string) $this->getStudyValue($study, 'protocolSection.descriptionModule.briefSummary', '');
    if ($brief_summary !== '') {
      $build['brief_summary'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Summary'),
        'content' => [
          '#markup' => nl2br(Html::escape($brief_summary)),
        ],
      ];
    }

    $interventions = $this->getStudyValue($study, 'protocolSection.armsInterventionsModule.interventions', []);
    if (is_array($interventions) && $interventions !== []) {
      $items = [];
      foreach ($interventions as $intervention) {
        if (!is_array($intervention)) {
          continue;
        }
        $name = (string) ($intervention['name'] ?? '');
        $type = (string) ($intervention['type'] ?? '');
        $description = (string) ($intervention['description'] ?? '');
        $item = $name;
        if ($type !== '') {
          $item .= ' (' . $type . ')';
        }
        if ($description !== '') {
          $item .= ': ' . $description;
        }
        if ($item !== '') {
          $items[] = $item;
        }
      }
      if ($items !== []) {
        $build['interventions'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Interventions'),
          'items' => [
            '#theme' => 'item_list',
            '#items' => $items,
          ],
        ];
      }
    }

    $primary_outcomes = $this->getStudyValue($study, 'protocolSection.outcomesModule.primaryOutcomes', []);
    if (is_array($primary_outcomes) && $primary_outcomes !== []) {
      $items = [];
      foreach ($primary_outcomes as $outcome) {
        if (!is_array($outcome)) {
          continue;
        }
        $measure = (string) ($outcome['measure'] ?? '');
        $time_frame = (string) ($outcome['timeFrame'] ?? '');
        if ($measure === '') {
          continue;
        }
        $items[] = ($time_frame !== '') ? ($measure . ' (' . $time_frame . ')') : $measure;
      }
      if ($items !== []) {
        $build['primary_outcomes'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Primary outcomes'),
          'items' => [
            '#theme' => 'item_list',
            '#items' => $items,
          ],
        ];
      }
    }

    $eligibility = (string) $this->getStudyValue($study, 'protocolSection.eligibilityModule.eligibilityCriteria', '');
    if ($eligibility !== '') {
      $build['eligibility'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Eligibility'),
        'content' => [
          '#markup' => nl2br(Html::escape($eligibility)),
        ],
      ];
    }

    $locations = $this->getStudyValue($study, 'protocolSection.contactsLocationsModule.locations', []);
    if (is_array($locations) && $locations !== []) {
      $rows = [];
      foreach ($locations as $location) {
        if (!is_array($location)) {
          continue;
        }
        $rows[] = [
          (string) ($location['facility'] ?? ''),
          implode(', ', array_filter([
            (string) ($location['city'] ?? ''),
            (string) ($location['state'] ?? ''),
            (string) ($location['country'] ?? ''),
          ])),
          (string) ($location['status'] ?? ''),
        ];
      }
      if ($rows !== []) {
        $build['locations'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Locations'),
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Facility'),
              $this->t('Location'),
              $this->t('Status'),
            ],
            '#rows' => $rows,
            '#sticky' => FALSE,
          ],
        ];
      }
    }

    return $build;
  }

  /**
   * Builds a summary fieldset.
   */
  protected function buildSummaryFieldset(string $title, array $items): array {
    return [
      '#type' => 'fieldset',
      '#title' => $title,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Builds bold label and value markup for summary lists.
   */
  protected function buildLabelValueMarkup(string $label, string $value): array {
    return [
      '#markup' => '<strong>' . Html::escape($label) . ':</strong> ' . Html::escape($value),
    ];
  }

  /**
   * Builds the list of study fact items.
   */
  protected function buildFactsItems(array $study): array {
    $items = [];

    $start_date = (string) $this->getStudyValue($study, 'protocolSection.statusModule.startDateStruct.date', '');
    if ($start_date !== '') {
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Start date'), $start_date);
    }

    $completion_date = (string) $this->getStudyValue($study, 'protocolSection.statusModule.completionDateStruct.date', '');
    if ($completion_date !== '') {
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Completion date'), $completion_date);
    }

    $enrollment_count = $this->getStudyValue($study, 'protocolSection.designModule.enrollmentInfo.count', NULL);
    $enrollment_type = (string) $this->getStudyValue($study, 'protocolSection.designModule.enrollmentInfo.type', '');
    if ($enrollment_count !== NULL) {
      $value = (string) $enrollment_count;
      if ($enrollment_type !== '') {
        $value .= ' (' . $enrollment_type . ')';
      }
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Enrollment'), $value);
    }

    $sex = (string) $this->getStudyValue($study, 'protocolSection.eligibilityModule.sex', '');
    if ($sex !== '') {
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Sex'), $sex);
    }

    $minimum_age = (string) $this->getStudyValue($study, 'protocolSection.eligibilityModule.minimumAge', '');
    $maximum_age = (string) $this->getStudyValue($study, 'protocolSection.eligibilityModule.maximumAge', '');
    if ($minimum_age !== '' || $maximum_age !== '') {
      $age_range = (($minimum_age !== '') ? $minimum_age : (string) $this->t('N/A')) . ' - ' . (($maximum_age !== '') ? $maximum_age : (string) $this->t('N/A'));
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Age range'), $age_range);
    }

    $healthy_volunteers = $this->getStudyValue($study, 'protocolSection.eligibilityModule.healthyVolunteers', NULL);
    if ($healthy_volunteers !== NULL) {
      $items[] = $this->buildLabelValueMarkup((string) $this->t('Healthy volunteers'), (string) ($healthy_volunteers ? $this->t('Yes') : $this->t('No')));
    }

    return $items;
  }

  /**
   * Builds the flattened study data table.
   */
  protected function buildDataTable(array $study, array $metadata): array {
    $rows = [];
    foreach ($study as $key => $value) {
      $field_metadata = $metadata[$key] ?? [];
      $title = (string) ($field_metadata['title'] ?? $field_metadata['name'] ?? $key);
      $description = (string) ($field_metadata['description'] ?? '');
      $data_type = (string) ($field_metadata['sourceType'] ?? '');

      $field_markup = '<strong>' . Html::escape($title) . '</strong>';
      if ($data_type !== '') {
        $field_markup .= ' (' . Html::escape($data_type) . ')';
      }
      if ($description !== '') {
        $field_markup .= '<br/>' . Html::escape($description);
      }
      $field_markup .= '<br/><small>' . Html::escape($key) . '</small>';

      $rows[] = [
        [
          'data' => [
            '#markup' => $field_markup,
          ],
        ],
        [
          'data' => $this->buildValueElement($value),
        ],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Study data'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $rows,
        '#sticky' => FALSE,
      ],
    ];
  }

  /**
   * Builds the API URL item displayed at the bottom of the page.
   */
  protected function buildApiUrl(string $nct_id): array {
    $url = ClinicalTrialsGovApi::BASE_URL . '/studies/' . rawurlencode($nct_id);

    return [
      '#type' => 'item',
      '#markup' => $this->t('ClinicalTrials.gov API: <a href=":url">@url</a>', [
        ':url' => $url,
        '@url' => $url,
      ]),
    ];
  }

  /**
   * Builds the public ClinicalTrials.gov study link.
   */
  protected function buildStudyLink(string $nct_id): array {
    $url = 'https://clinicaltrials.gov/study/' . rawurlencode($nct_id);

    return [
      '#type' => 'item',
      '#markup' => $this->t('ClinicalTrials.gov URL: <a href=":url">@url</a>', [
        ':url' => $url,
        '@url' => $url,
      ]),
    ];
  }

  /**
   * Builds a Drupal render array for a flattened study value.
   */
  protected function buildValueElement(mixed $value): array {
    if ($value === NULL) {
      return [
        '#markup' => '—',
      ];
    }

    if (is_bool($value)) {
      return [
        '#markup' => $value ? (string) $this->t('Yes') : (string) $this->t('No'),
      ];
    }

    if (!is_array($value)) {
      $string_value = (string) $value;
      return [
        '#markup' => nl2br(Html::escape($string_value)),
      ];
    }

    if ($value === []) {
      return [
        '#markup' => '—',
      ];
    }

    if (array_is_list($value)) {
      $first_item = $value[0] ?? NULL;
      if (!is_array($first_item)) {
        return [
          '#theme' => 'item_list',
          '#items' => array_map(function ($item): string {
            if ($item === NULL) {
              return '—';
            }
            return is_scalar($item) ? (string) $item : (json_encode($item) ?: '');
          }, $value),
        ];
      }

      return $this->buildNestedTable($value);
    }

    return $this->buildAssociativeList($value);
  }

  /**
   * Builds a nested table for a list of objects.
   */
  protected function buildNestedTable(array $rows): array {
    $header = array_map('strval', array_keys($rows[0]));
    $table_rows = [];

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $table_row = [];
      foreach ($header as $key) {
        $table_row[] = [
          'data' => $this->buildValueElement($row[$key] ?? NULL),
        ];
      }
      $table_rows[] = $table_row;
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $table_rows,
      '#sticky' => FALSE,
    ];
  }

  /**
   * Builds a label-value list for associative arrays.
   */
  protected function buildAssociativeList(array $values): array {
    $items = [];
    foreach ($values as $key => $value) {
      $text = is_scalar($value) || $value === NULL ? (string) ($value ?? '—') : json_encode($value);
      $items[] = $this->t('@key: @value', [
        '@key' => (string) $key,
        '@value' => (string) $text,
      ]);
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Gets a study value from the flat array with a fallback.
   */
  protected function getStudyValue(array $study, string $key, mixed $default): mixed {
    return $study[$key] ?? $default;
  }

  /**
   * Normalizes a mixed value into a string list.
   */
  protected function normalizeStringList(mixed $values): array {
    if (!is_array($values)) {
      return [];
    }

    $items = [];
    foreach ($values as $value) {
      if ($value === NULL || is_array($value)) {
        continue;
      }
      $items[] = (string) $value;
    }
    return $items;
  }

}
