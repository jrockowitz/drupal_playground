<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
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
      ->set('view_display_root', 'details_opened')
      ->set('view_display_component', 'visible')
      ->set('view_display_field_group', 'fieldset')
      ->set('form_display_root', 'fieldset')
      ->set('form_display_component', 'readonly')
      ->set('form_display_field_group', 'details_opened')
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
    $this->assertSame('map_string', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.eligibilityModule'))->getSetting('columns')['stdAges']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))->getSetting('columns')['contacts']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations'))->getSetting('columns')['geoPoint']['type']);
    $this->assertSame('string_long', FieldStorageConfig::loadByName('node', $this->entityManager->generateFieldName('protocolSection.referencesModule.references'))->getSetting('columns')['citation']['type']);
    $this->assertSame('link', FieldStorageConfig::loadByName('node', 'trial_nct_url')->getType());
    $this->assertSame('link', FieldStorageConfig::loadByName('node', 'trial_nct_api')->getType());

    // Check that the bundle field config exists for the created type.
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertNotNull(FieldConfig::loadByName('node', 'trial', $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle')));
    $this->assertSame('ClinicalTrials.gov URL', FieldConfig::loadByName('node', 'trial', 'trial_nct_url')->label());
    $this->assertSame('ClinicalTrials.gov API', FieldConfig::loadByName('node', 'trial', 'trial_nct_api')->label());
    $this->assertSame(16, FieldConfig::loadByName('node', 'trial', 'trial_nct_url')->getSetting('link_type'));
    $this->assertSame(0, FieldConfig::loadByName('node', 'trial', 'trial_nct_url')->getSetting('title'));
    $this->assertSame(16, FieldConfig::loadByName('node', 'trial', 'trial_nct_api')->getSetting('link_type'));
    $this->assertSame(0, FieldConfig::loadByName('node', 'trial', 'trial_nct_api')->getSetting('title'));

    // Check that created fields are added to the default form and view displays.
    $form_display = EntityFormDisplay::load('node.trial.default');
    $this->assertNotNull($form_display);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_brief_title')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_nct_id')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_resp_party')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_nct_url')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_nct_api')['type']);

    $view_display = EntityViewDisplay::load('node.trial.default');
    $this->assertNotNull($view_display);
    $this->assertSame('string', $view_display->getComponent('trial_nct_id')['type']);
    $this->assertSame('custom_formatter', $view_display->getComponent('trial_resp_party')['type']);
    $this->assertSame('link', $view_display->getComponent('trial_nct_url')['type']);
    $this->assertSame('link', $view_display->getComponent('trial_nct_api')['type']);
    $this->assertGreaterThan($view_display->getComponent('trial_over_status')['weight'] ?? -1, $view_display->getComponent('trial_nct_url')['weight'] ?? -1);
    $this->assertGreaterThan($view_display->getComponent('trial_nct_url')['weight'] ?? -1, $view_display->getComponent('trial_nct_api')['weight'] ?? -1);

    // Check that the promoted custom field is added to the displays.
    $location_field_name = $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations');
    $this->assertSame('readonly_field_widget', $form_display->getComponent($location_field_name)['type']);
    $this->assertSame('custom_formatter', $view_display->getComponent($location_field_name)['type']);

    // Check that remaining nested structure selections create the configured form field group.
    $field_groups = $form_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_clinical_trials_gov', $field_groups);
    $this->assertSame('fieldset', $field_groups['group_clinical_trials_gov']['format_type']);
    $this->assertContains('trial_nct_url', $field_groups['group_clinical_trials_gov']['children']);
    $this->assertContains('trial_nct_api', $field_groups['group_clinical_trials_gov']['children']);
    $this->assertContains('group_id_mod', $field_groups['group_clinical_trials_gov']['children']);
    $this->assertNotContains('title', $field_groups['group_clinical_trials_gov']['children']);
    $this->assertArrayHasKey('group_id_mod', $field_groups);
    $this->assertSame('group_clinical_trials_gov', $field_groups['group_id_mod']['parent_name']);
    $this->assertContains('trial_brief_title', $field_groups['group_id_mod']['children']);
    $this->assertContains('trial_nct_id', $field_groups['group_id_mod']['children']);
    $this->assertContains('trial_org', $field_groups['group_id_mod']['children']);
    $this->assertNotContains('title', $field_groups['group_id_mod']['children']);
    $this->assertSame('details', $field_groups['group_id_mod']['format_type']);
    $this->assertTrue($field_groups['group_id_mod']['format_settings']['open']);

    // Check that remaining nested structure selections create the configured view field group.
    $view_field_groups = $view_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_clinical_trials_gov', $view_field_groups);
    $this->assertSame('details', $view_field_groups['group_clinical_trials_gov']['format_type']);
    $this->assertTrue($view_field_groups['group_clinical_trials_gov']['format_settings']['open']);
    $this->assertContains('trial_nct_url', $view_field_groups['group_clinical_trials_gov']['children']);
    $this->assertContains('trial_nct_api', $view_field_groups['group_clinical_trials_gov']['children']);
    $this->assertContains('group_id_mod', $view_field_groups['group_clinical_trials_gov']['children']);
    $this->assertArrayHasKey('group_id_mod', $view_field_groups);
    $this->assertSame('group_clinical_trials_gov', $view_field_groups['group_id_mod']['parent_name']);
    $this->assertSame('fieldset', $view_field_groups['group_id_mod']['format_type']);

    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial_hidden')
      ->set('form_display_root', 'details_opened')
      ->set('form_display_component', 'hidden')
      ->set('form_display_field_group', 'none')
      ->set('view_display_root', 'container')
      ->set('view_display_component', 'hidden')
      ->set('view_display_field_group', 'none')
      ->save();

    $this->entityManager->createContentType('trial_hidden', 'Hidden Trial', 'Clinical trial content type');
    $this->entityManager->createFields('trial_hidden', [
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.organization',
    ]);

    $hidden_form_display = EntityFormDisplay::load('node.trial_hidden.default');
    $hidden_view_display = EntityViewDisplay::load('node.trial_hidden.default');

    // Check that hidden display settings skip component and field-group creation.
    $this->assertNotNull($hidden_form_display);
    $this->assertNotNull($hidden_view_display);
    $this->assertNull($hidden_form_display->getComponent('trial_brief_title'));
    $this->assertNull($hidden_view_display->getComponent('trial_brief_title'));
    $hidden_form_field_groups = $hidden_form_display->getThirdPartySettings('field_group');
    $hidden_view_field_groups = $hidden_view_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_clinical_trials_gov', $hidden_form_field_groups);
    $this->assertSame([], $hidden_form_field_groups['group_clinical_trials_gov']['children']);
    $this->assertArrayHasKey('group_clinical_trials_gov', $hidden_view_field_groups);
    $this->assertSame([], $hidden_view_field_groups['group_clinical_trials_gov']['children']);

    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial_nested')
      ->set('field_prefix', 'trial_nested')
      ->set('form_display_root', 'none')
      ->set('form_display_component', 'visible')
      ->set('form_display_field_group', 'details_opened')
      ->set('view_display_root', 'none')
      ->set('view_display_component', 'visible')
      ->set('view_display_field_group', 'fieldset')
      ->save();

    $this->entityManager->createContentType('trial_nested', 'Nested Trial', 'Clinical trial content type');
    $this->entityManager->createFields('trial_nested', [
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.organization',
    ]);

    $nested_form_display = EntityFormDisplay::load('node.trial_nested.default');
    $nested_view_display = EntityViewDisplay::load('node.trial_nested.default');

    // Check that nested field groups still work without the ClinicalTrials.gov root group.
    $this->assertNotNull($nested_form_display);
    $this->assertNotNull($nested_view_display);
    $nested_form_field_groups = $nested_form_display->getThirdPartySettings('field_group');
    $nested_view_field_groups = $nested_view_display->getThirdPartySettings('field_group');
    $this->assertArrayNotHasKey('group_clinical_trials_gov', $nested_form_field_groups);
    $this->assertArrayNotHasKey('group_clinical_trials_gov', $nested_view_field_groups);
    $this->assertArrayHasKey('group_id_mod', $nested_form_field_groups);
    $this->assertSame('', $nested_form_field_groups['group_id_mod']['parent_name']);
    $this->assertArrayHasKey('group_id_mod', $nested_view_field_groups);
    $this->assertSame('', $nested_view_field_groups['group_id_mod']['parent_name']);

    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('field_prefix', 'trial')
      ->save();

    // Check that the teaser display only contains the selected summary fields.
    $teaser_display = EntityViewDisplay::load('node.trial.teaser');
    $this->assertNotNull($teaser_display);

    $brief_summary_field_name = $this->entityManager->generateFieldName('protocolSection.descriptionModule.briefSummary');
    $condition_field_name = $this->entityManager->generateFieldName('protocolSection.conditionsModule.conditions');
    $keyword_field_name = $this->entityManager->generateFieldName('protocolSection.conditionsModule.keywords');
    $minimum_age_field_name = $this->entityManager->generateFieldName('protocolSection.eligibilityModule.minimumAge');
    $maximum_age_field_name = $this->entityManager->generateFieldName('protocolSection.eligibilityModule.maximumAge');
    $standard_ages_field_name = $this->entityManager->generateFieldName('protocolSection.eligibilityModule.stdAges');

    $this->assertSame('text_summary_or_trimmed', $teaser_display->getComponent($brief_summary_field_name)['type']);
    $this->assertSame('string', $teaser_display->getComponent($condition_field_name)['type']);
    $this->assertSame('string', $teaser_display->getComponent($keyword_field_name)['type']);
    $this->assertSame('string', $teaser_display->getComponent($minimum_age_field_name)['type']);
    $this->assertSame('string', $teaser_display->getComponent($maximum_age_field_name)['type']);
    $this->assertSame('list_default', $teaser_display->getComponent($standard_ages_field_name)['type']);
    $this->assertNull($teaser_display->getComponent('trial_nct_url'));
    $this->assertNull($teaser_display->getComponent('trial_resp_party'));
    $this->assertSame([], $teaser_display->getThirdPartySettings('field_group'));
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
      'inv_full_name',
      'inv_title',
      'inv_aff',
      'old_name_title',
      'old_org',
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

    // Check that the default prefix is used when no override is saved.
    $this->assertSame('trial_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));

    $this->config('clinical_trials_gov.settings')
      ->set('field_prefix', 'study')
      ->save();

    // Check that changing the configured prefix changes generated field names.
    $this->assertSame('study_nct_id', $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId'));
  }

}
