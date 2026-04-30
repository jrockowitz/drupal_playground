<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovCustomFieldManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovCustomFieldManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovCustomFieldManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'node',
    'field',
    'text',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'custom_field',
    'field_group',
  ];

  /**
   * The custom field manager under test.
   */
  protected ClinicalTrialsGovCustomFieldManagerInterface $customFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->customFieldManager = $this->container->get('clinical_trials_gov.custom_field_manager');
  }

  /**
   * Tests supported struct resolution for custom fields.
   */
  public function testResolveStructuredFieldDefinition(): void {
    $responsible_party_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $eligibility_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.eligibilityModule');
    $references_definition = $this->customFieldManager->resolveStructuredFieldDefinition('protocolSection.referencesModule.references');

    // Check that simple structs resolve as custom fields.
    $this->assertIsArray($responsible_party_definition);
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertSame([
      'type',
      'inv_full_name',
      'inv_title',
      'inv_affil',
      'old_name_title',
      'old_org',
    ], $responsible_party_definition['details']);

    // Check that MARKUP child fields use formatted long text settings.
    $this->assertIsArray($eligibility_definition);
    $this->assertSame('custom', $eligibility_definition['field_type']);
    $this->assertSame('string_long', $eligibility_definition['storage_settings']['columns']['eligibilityCriteria']['type']);
    $this->assertTrue($eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['formatted']);
    $this->assertSame('plain_text', $eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['default_format']);
    $this->assertSame('map_string', $eligibility_definition['storage_settings']['columns']['stdAges']['type']);
    $this->assertSame('', $eligibility_definition['instance_settings']['field_settings']['stdAges']['table_empty']);

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
