<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovEntityManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovEntityManagerTest extends KernelTestBase {

  /**
   * Modules required for these kernel tests.
   *
   * @var array<string>
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
    'json_field',
    'custom_field',
    'field_group',
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
  }

  /**
   * Tests content type and field creation plus field resolution.
   */
  public function testCreateContentTypeAndFields(): void {
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
      'protocolSection.eligibilityModule',
      'protocolSection.referencesModule.references',
      'protocolSection.statusModule.overallStatus',
      'protocolSection.identificationModule.organization',
    ]);

    // Check that the scalar, enum, and custom fields were created.
    $this->assertSame('string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'))?->getType());
    $this->assertSame('string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle'))?->getType());
    $this->assertSame(300, FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle'))->getSetting('max_length'));
    $this->assertSame('text_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.descriptionModule.briefSummary'))?->getType());
    $this->assertSame(-1, FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.conditionsModule.conditions'))?->getCardinality());
    $this->assertSame('list_string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.statusModule.overallStatus'))?->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.identificationModule.organization'))?->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.sponsorCollaboratorsModule.responsibleParty'))?->getType());
    $this->assertSame('custom', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))?->getType());
    $this->assertSame('map_string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.eligibilityModule'))?->getSetting('columns')['stdAges']['type'] ?? NULL);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.referencesModule.references'))?->getSetting('columns')['citation']['type'] ?? NULL);

    // Check that the bundle field config exists for the created type.
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle')));

    // Check that created fields are added to the default form and view displays.
    $form_display = EntityFormDisplay::load('node.trial.default');
    $this->assertNotNull($form_display);
    $this->assertSame('string_textfield', $form_display->getComponent('field_brief_title')['type'] ?? NULL);
    $this->assertSame('string_textfield', $form_display->getComponent('field_nct_id')['type'] ?? NULL);
    $this->assertSame('custom_stacked', $form_display->getComponent('field_responsible_party')['type'] ?? NULL);

    $view_display = EntityViewDisplay::load('node.trial.default');
    $this->assertNotNull($view_display);
    $this->assertSame('string', $view_display->getComponent('field_nct_id')['type'] ?? NULL);
    $this->assertSame('custom_formatter', $view_display->getComponent('field_responsible_party')['type'] ?? NULL);

    // Check that the promoted custom field is added to the displays.
    $this->assertSame('custom_stacked', $form_display->getComponent('field_location')['type'] ?? NULL);
    $this->assertSame('custom_formatter', $view_display->getComponent('field_location')['type'] ?? NULL);

    // Check that remaining nested structure selections create a field group on the form display.
    $field_groups = $form_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_identification_module', $field_groups);
    $this->assertContains('field_brief_title', $field_groups['group_identification_module']['children']);
    $this->assertContains('field_nct_id', $field_groups['group_identification_module']['children']);
    $this->assertContains('field_organization', $field_groups['group_identification_module']['children']);
    $this->assertNotContains('title', $field_groups['group_identification_module']['children']);

    // Check that remaining nested structure selections create a fieldset on the view display.
    $view_field_groups = $view_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_identification_module', $view_field_groups);
    $this->assertSame('fieldset', $view_field_groups['group_identification_module']['format_type']);
  }

  /**
   * Tests field-name generation and metadata-driven resolution.
   */
  public function testResolveFieldDefinition(): void {
    $long_name = $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations.contacts.phoneExt');
    $alias_name = $this->entityManager->generateFieldName('protocolSection.identificationModule.nctIdAliases');

    // Check that generated field names are deterministic and 32 characters or less.
    $this->assertSame($long_name, $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations.contacts.phoneExt'));
    $this->assertLessThanOrEqual(32, strlen($long_name));
    $this->assertSame('field_nct_id_alias', $alias_name);

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
    $this->assertSame('field_brief_title', $title_definition['field_name']);
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
      'start_date',
      'start_date_type',
    ], $partial_date_struct_definition['details']);

    // Check that text array fields resolve to unlimited multi-value scalar fields.
    $this->assertSame('string', $conditions_definition['field_type']);
    $this->assertSame(-1, $conditions_definition['cardinality']);
    $this->assertSame('string (multiple)', $conditions_definition['display_type_label']);

    // Check that whitelisted structures resolve to custom fields with columns.
    $this->assertSame('custom', $custom_definition['field_type']);
    $this->assertArrayHasKey('fullName', $custom_definition['storage_settings']['columns']);
    $this->assertArrayHasKey('class', $custom_definition['instance_settings']['field_settings']);

    // Check that simple structs resolve to custom fields with display details.
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertSame('custom field', $responsible_party_definition['display_type_label']);
    $this->assertSame([
      'type',
      'investigator_full_name',
      'investigator_title',
      'investigator_affiliation',
      'old_name_title',
      'old_organization',
    ], $responsible_party_definition['details']);

    // Check that MARKUP custom-field columns use formatted long text with plain text format.
    $this->assertSame('custom', $eligibility_module_definition['field_type']);
    $this->assertSame('string_long', $eligibility_module_definition['storage_settings']['columns']['eligibilityCriteria']['type']);
    $this->assertTrue($eligibility_module_definition['instance_settings']['field_settings']['eligibilityCriteria']['formatted']);
    $this->assertSame('plain_text', $eligibility_module_definition['instance_settings']['field_settings']['eligibilityCriteria']['default_format']);
    $this->assertSame('map_string', $eligibility_module_definition['storage_settings']['columns']['stdAges']['type']);
    $this->assertSame('', $eligibility_module_definition['instance_settings']['field_settings']['stdAges']['table_empty']);

    // Check that reference citations use long plain text even without maxChars in the metadata.
    $this->assertSame('custom', $references_definition['field_type']);
    $this->assertSame('string_long', $references_definition['storage_settings']['columns']['citation']['type']);
    $this->assertArrayNotHasKey('formatted', $references_definition['instance_settings']['field_settings']['citation']);

    // Check that whitelisted structured arrays resolve to custom fields.
    $this->assertSame('custom', $group_definition['field_type']);
    $this->assertFalse($group_definition['group_only']);
  }

}
