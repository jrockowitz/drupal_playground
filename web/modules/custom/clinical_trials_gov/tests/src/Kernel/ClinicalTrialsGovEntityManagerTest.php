<?php

// cspell:ignore elig

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovEntityManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovEntityManagerTest extends ClinicalTrialsGovContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'readonly_field_widget',
  ];

  /**
   * The entity manager under test.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('field_prefix', 'trial')
      ->save();
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
  }

  /**
   * Tests content type creation, field creation, and field resolution.
   */
  public function testEntityManager(): void {
    $this->entityManager->createContentType('trial', 'Trial', 'Clinical trial content type');

    // Check that the content type is created with the expected label.
    $node_type = NodeType::load('trial');
    $this->assertNotNull($node_type);
    $this->assertSame('Trial', $node_type->label());

    $this->entityManager->createFields('trial', [
      'protocolSection.contactsLocationsModule.locations',
      'protocolSection.contactsLocationsModule.locations.facility',
      'protocolSection.contactsLocationsModule.locations.status',
      'protocolSection.contactsLocationsModule.locations.contacts',
      'protocolSection.identificationModule',
      'protocolSection.sponsorCollaboratorsModule.responsibleParty',
      'protocolSection.conditionsModule.conditions',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
      'protocolSection.descriptionModule.briefSummary',
      'protocolSection.conditionsModule.keywords',
      'protocolSection.eligibilityModule',
      'protocolSection.eligibilityModule.minimumAge',
      'protocolSection.eligibilityModule.maximumAge',
      'protocolSection.eligibilityModule.stdAges',
      'protocolSection.referencesModule.references',
      'protocolSection.statusModule.overallStatus',
      'protocolSection.identificationModule.organization',
    ]);

    // Check that the scalar, enum, and custom fields were created.
    $this->assertSame('string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'))->getType());
    $this->assertSame('string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle'))->getType());
    $this->assertSame(300, FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle'))->getSetting('max_length'));
    $this->assertSame('text_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.descriptionModule.briefSummary'))->getType());
    $this->assertSame(-1, FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.conditionsModule.conditions'))->getCardinality());
    $this->assertSame('list_string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.statusModule.overallStatus'))->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.organization'))->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.sponsorCollaboratorsModule.responsibleParty'))->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))->getType());
    $this->assertSame('map_string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.eligibilityModule'))->getSetting('columns')['std_age']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))->getSetting('columns')['contact']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))->getSetting('columns')['geo_point']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.referencesModule.references'))->getSetting('columns')['citation']['type']);
    // Check that the bundle field config exists for the created type.
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle')));

    // Check that field creation also creates an empty teaser display.
    $teaser_display = EntityViewDisplay::load('node.trial.teaser');
    $this->assertNotNull($teaser_display);
    $this->assertArrayNotHasKey($this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'), $teaser_display->getComponents());
    $this->assertArrayNotHasKey($this->entityManager->generateFieldName('protocolSection.descriptionModule.briefSummary'), $teaser_display->getComponents());

    $teaser_display->setComponent('title', [
      'type' => 'string',
      'label' => 'hidden',
      'weight' => -5,
      'region' => 'content',
      'settings' => [
        'link_to_entity' => FALSE,
      ],
    ])->save();

    $this->entityManager->createFields('trial', [
      'protocolSection.identificationModule.nctId',
    ]);

    $teaser_display = EntityViewDisplay::load('node.trial.teaser');
    $this->assertNotNull($teaser_display);
    $this->assertArrayHasKey('title', $teaser_display->getComponents());
    $this->assertArrayNotHasKey($this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'), $teaser_display->getComponents());

    $long_name = $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations.contacts.phoneExt');
    $alias_name = $this->entityManager->generateFieldName('protocolSection.identificationModule.nctIdAliases');

    // Check that generated field names are deterministic and 32 characters or less.
    $this->assertSame($long_name, $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations.contacts.phoneExt'));
    $this->assertLessThanOrEqual(32, strlen($long_name));
    $this->assertSame('trial_nct_id_alias', $alias_name);

    $title_definition = $this->entityManager->resolveFieldDefinition('protocolSection.identificationModule.briefTitle');
    $enum_definition = $this->entityManager->resolveFieldDefinition('protocolSection.statusModule.overallStatus');
    $partial_date_definition = $this->entityManager->resolveFieldDefinition('protocolSection.statusModule.startDateStruct.date');
    $partial_date_struct_definition = $this->entityManager->resolveFieldDefinition('protocolSection.statusModule.startDateStruct');
    $conditions_definition = $this->entityManager->resolveFieldDefinition('protocolSection.conditionsModule.conditions');
    $custom_definition = $this->entityManager->resolveFieldDefinition('protocolSection.identificationModule.organization');
    $responsible_party_definition = $this->entityManager->resolveFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $eligibility_module_definition = $this->entityManager->resolveFieldDefinition('protocolSection.eligibilityModule');
    $references_definition = $this->entityManager->resolveFieldDefinition('protocolSection.referencesModule.references');
    $group_definition = $this->entityManager->resolveFieldDefinition('protocolSection.contactsLocationsModule.locations');

    // Check that title maps to the node title property.
    $this->assertSame('title', $title_definition['destination_property']);
    $this->assertSame('trial_brief_title', $title_definition['field_name']);
    $this->assertSame('string', $title_definition['field_type']);
    $this->assertSame(300, $title_definition['storage_settings']['max_length']);

    // Check that enums resolve to list_string with allowed values.
    $this->assertSame('list_string', $enum_definition['field_type']);
    $this->assertNotEmpty($enum_definition['storage_settings']['allowed_values']);

    // Check that partial date leaves resolve to date fields.
    $this->assertSame('datetime', $partial_date_definition['field_type']);
    $this->assertSame('date', $partial_date_definition['display_type_label']);

    // Check that partial date structures resolve to custom fields.
    $this->assertSame('custom', $partial_date_struct_definition['field_type']);
    $this->assertSame([
      'StartDate',
      'StartDateType',
    ], $partial_date_struct_definition['details']);

    // Check that text array fields resolve to unlimited multi-value scalar fields.
    $this->assertSame('string', $conditions_definition['field_type']);
    $this->assertSame(-1, $conditions_definition['cardinality']);
    $this->assertSame('string (multiple)', $conditions_definition['display_type_label']);

    // Check that whitelisted structures resolve to custom fields with columns.
    $this->assertSame('custom', $custom_definition['field_type']);
    $this->assertArrayHasKey('org_full_name', $custom_definition['storage_settings']['columns']);
    $this->assertArrayHasKey('org_class', $custom_definition['instance_settings']['field_settings']);

    // Check that simple structs resolve to custom fields with display details.
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertSame('custom field', $responsible_party_definition['display_type_label']);
    $this->assertSame([
      'Type',
      'InvestigatorFullName',
      'InvestigatorTitle',
      'InvestigatorAffiliation',
      'OldNameTitle',
      'OldOrganization',
    ], $responsible_party_definition['details']);

    // Check that MARKUP custom-field columns use formatted long text with plain text format.
    $this->assertSame('custom', $eligibility_module_definition['field_type']);
    $this->assertSame('string_long', $eligibility_module_definition['storage_settings']['columns']['elig_criteria']['type']);
    $this->assertTrue($eligibility_module_definition['instance_settings']['field_settings']['elig_criteria']['formatted']);
    $this->assertSame('plain_text', $eligibility_module_definition['instance_settings']['field_settings']['elig_criteria']['default_format']);
    $this->assertSame('map_string', $eligibility_module_definition['storage_settings']['columns']['std_age']['type']);
    $this->assertSame('', $eligibility_module_definition['instance_settings']['field_settings']['std_age']['table_empty']);

    // Check that reference citations use long plain text even without maxChars in the metadata.
    $this->assertSame('custom', $references_definition['field_type']);
    $this->assertSame('string_long', $references_definition['storage_settings']['columns']['citation']['type']);
    $this->assertArrayNotHasKey('formatted', $references_definition['instance_settings']['field_settings']['citation']);

    // Check that whitelisted structured arrays resolve to custom fields.
    $this->assertSame('custom', $group_definition['field_type']);
    $this->assertFalse($group_definition['group_only']);

    // Check that the default prefix is used when no override is saved.
    $this->assertSame('trial_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));

    $this->config('clinical_trials_gov.settings')
      ->set('field_prefix', 'study')
      ->save();

    // Check that changing the configured prefix changes generated field names.
    $this->assertSame('study_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));
  }

}
