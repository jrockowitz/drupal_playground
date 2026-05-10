<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovCustomFieldManagerInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovCustomFieldManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovCustomFieldManagerTest extends ClinicalTrialsGovContentTestBase {

  /**
   * The custom field manager under test.
   */
  protected ClinicalTrialsGovCustomFieldManagerInterface $customFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->customFieldManager = $this->container->get('clinical_trials_gov.custom_field_manager');
  }

  /**
   * Tests supported struct resolution for custom fields.
   */
  public function testResolveStructuredFieldDefinition(): void {
    $responsible_party_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $eligibility_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.eligibilityModule');
    $arm_groups_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.armsInterventionsModule.armGroups');
    $interventions_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.armsInterventionsModule.interventions');
    $references_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.referencesModule.references');

    // Check that simple structs resolve as custom fields.
    $this->assertIsArray($responsible_party_definition);
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertSame([
      'Type',
      'InvestigatorFullName',
      'InvestigatorTitle',
      'InvestigatorAffiliation',
      'OldNameTitle',
      'OldOrganization',
    ], $responsible_party_definition['details']);
    $this->assertSame([
      'type',
      'inv_full_name',
      'inv_title',
      'inv_aff',
      'old_name_title',
      'old_org',
    ], $responsible_party_definition['field_details']);

    // Check that MARKUP child fields use formatted long text settings.
    $this->assertIsArray($eligibility_definition);
    $this->assertSame('custom', $eligibility_definition['field_type']);
    $this->assertSame('string_long', $eligibility_definition['storage_settings']['columns']['eligibilityCriteria']['type']);
    $this->assertTrue($eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['formatted']);
    $this->assertSame('plain_text', $eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['default_format']);
    $this->assertSame('map_string', $eligibility_definition['storage_settings']['columns']['stdAges']['type']);
    $this->assertSame('', $eligibility_definition['instance_settings']['field_settings']['stdAges']['table_empty']);
    $this->assertContains('StdAge', $eligibility_definition['details']);
    $this->assertContains('std_age', $eligibility_definition['field_details']);
    $this->assertSame([
      [
        'key' => 'CHILD',
        'label' => 'Child',
      ],
      [
        'key' => 'ADULT',
        'label' => 'Adult',
      ],
      [
        'key' => 'OLDER_ADULT',
        'label' => 'Older Adult',
      ],
    ], $eligibility_definition['instance_settings']['field_settings']['stdAges']['allowed_values']);
    $this->assertSame('string', $eligibility_definition['storage_settings']['columns']['minimumAge']['type']);
    $this->assertSame('string', $eligibility_definition['storage_settings']['columns']['maximumAge']['type']);
    $this->assertNotContains('minimumAge', $eligibility_definition['yaml_columns']);
    $this->assertNotContains('maximumAge', $eligibility_definition['yaml_columns']);

    // Check that supported array structs resolve as custom fields.
    $this->assertIsArray($arm_groups_definition);
    $this->assertSame('custom', $arm_groups_definition['field_type']);
    $this->assertSame('string_long', $arm_groups_definition['storage_settings']['columns']['description']['type']);
    $this->assertSame('map_string', $arm_groups_definition['storage_settings']['columns']['interventionNames']['type']);
    $this->assertSame([
      [
        'key' => 'EXPERIMENTAL',
        'label' => 'Experimental',
      ],
      [
        'key' => 'ACTIVE_COMPARATOR',
        'label' => 'Active Comparator',
      ],
      [
        'key' => 'PLACEBO_COMPARATOR',
        'label' => 'Placebo Comparator',
      ],
      [
        'key' => 'SHAM_COMPARATOR',
        'label' => 'Sham Comparator',
      ],
      [
        'key' => 'NO_INTERVENTION',
        'label' => 'No Intervention',
      ],
      [
        'key' => 'OTHER',
        'label' => 'Other',
      ],
    ], $arm_groups_definition['instance_settings']['field_settings']['type']['allowed_values']);
    $this->assertSame([], $arm_groups_definition['yaml_columns']);

    // Check that interventions support enum, markup, and text-array columns.
    $this->assertIsArray($interventions_definition);
    $this->assertSame('custom', $interventions_definition['field_type']);
    $this->assertSame('string_long', $interventions_definition['storage_settings']['columns']['description']['type']);
    $this->assertSame('map_string', $interventions_definition['storage_settings']['columns']['armGroupLabels']['type']);
    $this->assertSame('map_string', $interventions_definition['storage_settings']['columns']['otherNames']['type']);
    $this->assertSame([
      'BEHAVIORAL',
      'BIOLOGICAL',
      'COMBINATION_PRODUCT',
      'DEVICE',
      'DIAGNOSTIC_TEST',
      'DIETARY_SUPPLEMENT',
      'DRUG',
      'GENETIC',
      'PROCEDURE',
      'RADIATION',
      'OTHER',
    ], array_column($interventions_definition['instance_settings']['field_settings']['type']['allowed_values'], 'key'));
    $this->assertSame([], $interventions_definition['yaml_columns']);

    // Check that policy-backed max length overrides promote long citation text.
    $this->assertIsArray($references_definition);
    $this->assertSame('custom', $references_definition['field_type']);
    $this->assertSame('string_long', $references_definition['storage_settings']['columns']['citation']['type']);
    $this->assertArrayNotHasKey('formatted', $references_definition['instance_settings']['field_settings']['citation']);

    // Check that unsupported nested values fall back to YAML-backed text.
    $locations_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.contactsLocationsModule.locations');
    $this->assertIsArray($locations_definition);
    $this->assertSame('string_long', $locations_definition['storage_settings']['columns']['contacts']['type']);
    $this->assertSame('Facility Contact (YAML)', $locations_definition['instance_settings']['field_settings']['contacts']['label']);
    $this->assertSame('string_long', $locations_definition['storage_settings']['columns']['geoPoint']['type']);
    $this->assertSame('Location Geo Point (YAML)', $locations_definition['instance_settings']['field_settings']['geoPoint']['label']);
    $this->assertSame([
      'contacts',
      'geoPoint',
    ], $locations_definition['yaml_columns']);
  }

}
