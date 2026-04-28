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

    $metadata = $this->manager->getMetadataByPath();
    $available_keys = [];

    foreach (array_merge(self::AVAILABLE_FIELD_KEYS, self::REQUIRED_FIELD_KEYS) as $path) {
      if (!isset($metadata[$path])) {
        continue;
      }
      $available_keys[$path] = TRUE;

      foreach ($this->getAncestorFieldKeys($path, $metadata) as $ancestor_key) {
        $available_keys[$ancestor_key] = TRUE;
      }
    }

    $ordered_keys = [];
    foreach (array_keys($metadata) as $path) {
      if (!isset($available_keys[$path])) {
        continue;
      }
      $ordered_keys[] = $path;
    }

    $this->availableFieldKeys = $ordered_keys;
    return $this->availableFieldKeys;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldDefinitionsFromQuery(string $query): array {
    $definitions = [];

    foreach ($this->getAvailableFieldKeysFromQuery($query) as $path) {
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(string $path): array {
    $definition = $this->entityManager->resolveFieldDefinition($path);
    $available = in_array($path, $this->getAvailableFieldKeysFromQuery(''));

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
  public function getFieldDefinitions(array $paths): array {
    $definitions = [];

    foreach ($paths as $path) {
      if (!is_string($path) || $path === '') {
        continue;
      }
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * Returns ancestor keys for one identifier path.
   */
  protected function getAncestorFieldKeys(string $path, array $metadata): array {
    $ancestor_keys = [];
    $last_dot = strrpos($path, '.');

    while ($last_dot !== FALSE) {
      $path = substr($path, 0, $last_dot);
      if (isset($metadata[$path])) {
        $ancestor_keys[] = $path;
      }
      $last_dot = strrpos($path, '.');
    }

    return array_reverse($ancestor_keys);
  }

}
