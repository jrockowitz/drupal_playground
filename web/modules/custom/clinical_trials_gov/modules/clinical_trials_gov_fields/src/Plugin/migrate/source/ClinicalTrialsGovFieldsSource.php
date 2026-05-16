<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_fields\Plugin\migrate\source;

use Drupal\clinical_trials_gov\Plugin\migrate\source\ClinicalTrialsGovSource;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Row;

/**
 * Provides a ClinicalTrials.gov source with normalized trial field values.
 */
#[MigrateSource(id: 'clinical_trials_gov_fields')]
class ClinicalTrialsGovFieldsSource extends ClinicalTrialsGovSource {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $result = parent::prepareRow($row);
    if ($result === FALSE) {
      return FALSE;
    }

    $row->setSourceProperty('normalized_trial_phase', $this->normalizeListValue($row->getSourceProperty('protocolSection.designModule.phases')));
    $row->setSourceProperty('normalized_trial_study_type', $this->normalizeScalarValue($row->getSourceProperty('protocolSection.designModule.studyType')));
    $row->setSourceProperty('normalized_trial_status', $this->normalizeScalarValue($row->getSourceProperty('protocolSection.statusModule.overallStatus')));
    $row->setSourceProperty('normalized_trial_sex', $this->normalizeScalarValue($row->getSourceProperty('protocolSection.eligibilityModule.sex')));
    $row->setSourceProperty('normalized_trial_nct_id', $this->normalizeScalarValue($row->getSourceProperty('protocolSection.identificationModule.nctId')));
    $row->setSourceProperty('normalized_trial_nct_url', $this->normalizeNctUrl($row->getSourceProperty('protocolSection.identificationModule.nctId')));
    $row->setSourceProperty('normalized_trial_age', $this->normalizeListValue($row->getSourceProperty('protocolSection.eligibilityModule.stdAges')));
    $row->setSourceProperty('normalized_trial_condition', $this->normalizeListValue($row->getSourceProperty('protocolSection.conditionsModule.conditions')));
    $row->setSourceProperty('normalized_trial_contact', $this->normalizeContacts($row->getSourceProperty('protocolSection.contactsLocationsModule.centralContacts')));
    $row->setSourceProperty('normalized_trial_location', $this->normalizeLocations($row->getSourceProperty('protocolSection.contactsLocationsModule.locations')));

    return $result;
  }

  /**
   * Normalizes one scalar study value.
   */
  protected function normalizeScalarValue(mixed $value): ?string {
    return is_scalar($value) ? (string) $value : NULL;
  }

  /**
   * Normalizes one list study value.
   */
  protected function normalizeListValue(mixed $value): ?array {
    if (!is_array($value) || !$value) {
      return NULL;
    }

    $normalized_value = array_values(array_filter($value, static fn(mixed $item): bool => is_scalar($item) && ((string) $item !== '')));
    return $normalized_value ?: NULL;
  }

  /**
   * Normalizes the ClinicalTrials.gov study URL for the link field.
   */
  protected function normalizeNctUrl(mixed $value): ?array {
    $nct_id = $this->normalizeScalarValue($value);
    if ($nct_id === NULL || $nct_id === '') {
      return NULL;
    }

    return [
      'uri' => 'https://clinicaltrials.gov/study/' . rawurlencode($nct_id),
    ];
  }

  /**
   * Normalizes central contact values for the destination custom field.
   */
  protected function normalizeContacts(mixed $value): ?array {
    if (!is_array($value) || !$value) {
      return NULL;
    }

    $normalized_contacts = [];
    foreach ($value as $contact) {
      if (!is_array($contact)) {
        continue;
      }

      $normalized_contact = [];
      foreach (['name', 'role', 'phone', 'email'] as $property_name) {
        if (isset($contact[$property_name]) && is_scalar($contact[$property_name]) && ((string) $contact[$property_name] !== '')) {
          $normalized_contact[$property_name] = (string) $contact[$property_name];
        }
      }
      if (isset($contact['phoneExt']) && is_scalar($contact['phoneExt']) && ((string) $contact['phoneExt'] !== '')) {
        $normalized_contact['phone_ext'] = (string) $contact['phoneExt'];
      }

      if ($normalized_contact) {
        $normalized_contacts[] = $normalized_contact;
      }
    }

    return $normalized_contacts ?: NULL;
  }

  /**
   * Normalizes location values for the destination custom field.
   */
  protected function normalizeLocations(mixed $value): ?array {
    if (!is_array($value) || !$value) {
      return NULL;
    }

    $normalized_locations = [];
    foreach ($value as $location) {
      if (!is_array($location)) {
        continue;
      }

      $normalized_location = [];
      foreach (['facility', 'city', 'state', 'zip', 'country', 'status'] as $property_name) {
        if (isset($location[$property_name]) && is_scalar($location[$property_name]) && ((string) $location[$property_name] !== '')) {
          $normalized_location[$property_name] = (string) $location[$property_name];
        }
      }

      if ($normalized_location) {
        $normalized_locations[] = $normalized_location;
      }
    }

    return $normalized_locations ?: NULL;
  }

}
