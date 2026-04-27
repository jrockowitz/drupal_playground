<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Curates wizard field options from a vetted set of study field indexes.
 */
class ClinicalTrialsGovFieldManager implements ClinicalTrialsGovFieldManagerInterface {

  /**
   * Field indexes observed in the two reference studies.
   */
  protected const AVAILABLE_FIELD_KEYS = [
    'annotationSection.annotationModule.unpostedAnnotation.unpostedEvents',
    'annotationSection.annotationModule.unpostedAnnotation.unpostedResponsibleParty',
    'derivedSection.conditionBrowseModule.ancestors',
    'derivedSection.conditionBrowseModule.meshes',
    'derivedSection.interventionBrowseModule.ancestors',
    'derivedSection.interventionBrowseModule.meshes',
    'derivedSection.miscInfoModule.submissionTracking.estimatedResultsFirstSubmitDate',
    'derivedSection.miscInfoModule.submissionTracking.submissionInfos',
    'derivedSection.miscInfoModule.versionHolder',
    'hasResults',
    'protocolSection.armsInterventionsModule.armGroups',
    'protocolSection.armsInterventionsModule.interventions',
    'protocolSection.conditionsModule.conditions',
    'protocolSection.conditionsModule.keywords',
    'protocolSection.contactsLocationsModule.centralContacts',
    'protocolSection.contactsLocationsModule.locations',
    'protocolSection.contactsLocationsModule.overallOfficials',
    'protocolSection.descriptionModule.briefSummary',
    'protocolSection.descriptionModule.detailedDescription',
    'protocolSection.designModule.designInfo.allocation',
    'protocolSection.designModule.designInfo.interventionModel',
    'protocolSection.designModule.designInfo.maskingInfo.masking',
    'protocolSection.designModule.designInfo.maskingInfo.whoMasked',
    'protocolSection.designModule.designInfo.observationalModel',
    'protocolSection.designModule.designInfo.primaryPurpose',
    'protocolSection.designModule.designInfo.timePerspective',
    'protocolSection.designModule.enrollmentInfo.count',
    'protocolSection.designModule.enrollmentInfo.type',
    'protocolSection.designModule.patientRegistry',
    'protocolSection.designModule.phases',
    'protocolSection.designModule.studyType',
    'protocolSection.eligibilityModule.eligibilityCriteria',
    'protocolSection.eligibilityModule.healthyVolunteers',
    'protocolSection.eligibilityModule.maximumAge',
    'protocolSection.eligibilityModule.minimumAge',
    'protocolSection.eligibilityModule.samplingMethod',
    'protocolSection.eligibilityModule.sex',
    'protocolSection.eligibilityModule.stdAges',
    'protocolSection.eligibilityModule.studyPopulation',
    'protocolSection.identificationModule.acronym',
    'protocolSection.identificationModule.briefTitle',
    'protocolSection.identificationModule.nctId',
    'protocolSection.identificationModule.officialTitle',
    'protocolSection.identificationModule.orgStudyIdInfo.id',
    'protocolSection.identificationModule.organization.class',
    'protocolSection.identificationModule.organization.fullName',
    'protocolSection.ipdSharingStatementModule.ipdSharing',
    'protocolSection.outcomesModule.primaryOutcomes',
    'protocolSection.oversightModule.isFdaRegulatedDevice',
    'protocolSection.oversightModule.isFdaRegulatedDrug',
    'protocolSection.oversightModule.oversightHasDmc',
    'protocolSection.sponsorCollaboratorsModule.collaborators',
    'protocolSection.sponsorCollaboratorsModule.leadSponsor.class',
    'protocolSection.sponsorCollaboratorsModule.leadSponsor.name',
    'protocolSection.sponsorCollaboratorsModule.responsibleParty.type',
    'protocolSection.statusModule.completionDateStruct.date',
    'protocolSection.statusModule.completionDateStruct.type',
    'protocolSection.statusModule.expandedAccessInfo.hasExpandedAccess',
    'protocolSection.statusModule.lastKnownStatus',
    'protocolSection.statusModule.lastUpdatePostDateStruct.date',
    'protocolSection.statusModule.lastUpdatePostDateStruct.type',
    'protocolSection.statusModule.lastUpdateSubmitDate',
    'protocolSection.statusModule.overallStatus',
    'protocolSection.statusModule.primaryCompletionDateStruct.date',
    'protocolSection.statusModule.primaryCompletionDateStruct.type',
    'protocolSection.statusModule.startDateStruct.date',
    'protocolSection.statusModule.startDateStruct.type',
    'protocolSection.statusModule.statusVerifiedDate',
    'protocolSection.statusModule.studyFirstPostDateStruct.date',
    'protocolSection.statusModule.studyFirstPostDateStruct.type',
    'protocolSection.statusModule.studyFirstSubmitDate',
    'protocolSection.statusModule.studyFirstSubmitQcDate',
  ];

  /**
   * Required fields for every import configuration.
   */
  protected const REQUIRED_FIELD_KEYS = [
    'protocolSection.identificationModule.nctId',
    'protocolSection.identificationModule.briefTitle',
    'protocolSection.descriptionModule.briefSummary',
  ];

  /**
   * Cached ordered list of curated field keys.
   */
  protected ?array $availableFieldKeys = NULL;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRequiredFieldKeys(): array {
    return self::REQUIRED_FIELD_KEYS;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldKeysFromQuery(string $query): array {
    if ($this->availableFieldKeys !== NULL) {
      return $this->availableFieldKeys;
    }

    $metadata = $this->manager->getStudyMetadata();
    $available_keys = [];

    foreach (array_merge(self::AVAILABLE_FIELD_KEYS, self::REQUIRED_FIELD_KEYS) as $api_key) {
      if (!isset($metadata[$api_key])) {
        continue;
      }
      $available_keys[$api_key] = TRUE;

      foreach ($this->getAncestorFieldKeys($api_key, $metadata) as $ancestor_key) {
        $available_keys[$ancestor_key] = TRUE;
      }
    }

    $ordered_keys = [];
    foreach (array_keys($metadata) as $api_key) {
      if (!isset($available_keys[$api_key])) {
        continue;
      }
      $ordered_keys[] = $api_key;
    }

    $this->availableFieldKeys = $ordered_keys;
    return $this->availableFieldKeys;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldDefinitionsFromQuery(string $query): array {
    $definitions = [];

    foreach ($this->getAvailableFieldKeysFromQuery($query) as $api_key) {
      $definitions[$api_key] = $this->getFieldDefinition($api_key);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(string $api_key): array {
    $definition = $this->entityManager->resolveFieldDefinition($api_key);
    $available = in_array($api_key, $this->getAvailableFieldKeysFromQuery(''));

    $definition['available'] = $available;
    if (!$available) {
      $definition['selectable'] = FALSE;
      $definition['reason'] = 'Not included in the vetted field list.';
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(array $api_keys): array {
    $definitions = [];

    foreach ($api_keys as $api_key) {
      if (!is_string($api_key) || $api_key === '') {
        continue;
      }
      $definitions[$api_key] = $this->getFieldDefinition($api_key);
    }

    return $definitions;
  }

  /**
   * Returns ancestor keys for one API key.
   */
  protected function getAncestorFieldKeys(string $api_key, array $metadata): array {
    $ancestor_keys = [];
    $last_dot = strrpos($api_key, '.');

    while ($last_dot !== FALSE) {
      $api_key = substr($api_key, 0, $last_dot);
      if (isset($metadata[$api_key])) {
        $ancestor_keys[] = $api_key;
      }
      $last_dot = strrpos($api_key, '.');
    }

    return array_reverse($ancestor_keys);
  }

}
