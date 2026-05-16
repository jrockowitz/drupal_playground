<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityDisplayManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovEntityDisplayManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovEntityDisplayManagerTest extends ClinicalTrialsGovContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'readonly_field_widget',
  ];

  /**
   * The entity display manager under test.
   */
  protected ClinicalTrialsGovEntityDisplayManagerInterface $entityDisplayManager;

  /**
   * The entity manager used for field provisioning helpers.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->entityDisplayManager = $this->container->get('clinical_trials_gov.entity_display_manager');
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
  }

  /**
   * Tests display component and field-group creation.
   */
  public function testEntityDisplayManager(): void {
    $selected_fields = [
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
    ];

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

    $field_definitions = $this->createBundleAndFieldDefinitions('trial', 'Trial', $selected_fields);
    $this->resetDisplays('trial');

    $this->entityDisplayManager->createFieldDisplayComponents('trial', $field_definitions);
    $this->entityDisplayManager->createFieldGroups('trial', $selected_fields, $field_definitions);

    $form_display = EntityFormDisplay::load('node.trial.default');
    $view_display = EntityViewDisplay::load('node.trial.default');

    // Check that created fields are added to the default form and view displays.
    $this->assertNotNull($form_display);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_brief_title')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_nct_id')['type']);
    $this->assertSame('readonly_field_widget', $form_display->getComponent('trial_resp_party')['type']);

    $this->assertNotNull($view_display);
    $this->assertSame('string', $view_display->getComponent('trial_nct_id')['type']);
    $this->assertSame('custom_formatter', $view_display->getComponent('trial_resp_party')['type']);

    // Check that the promoted custom field is added to the displays.
    $location_field_name = $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations');
    $this->assertSame('readonly_field_widget', $form_display->getComponent($location_field_name)['type']);
    $this->assertSame('custom_formatter', $view_display->getComponent($location_field_name)['type']);

    // Check that remaining nested structure selections create the configured form field group.
    $field_groups = $form_display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_clinical_trials_gov', $field_groups);
    $this->assertSame('fieldset', $field_groups['group_clinical_trials_gov']['format_type']);
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
    $this->assertContains('group_id_mod', $view_field_groups['group_clinical_trials_gov']['children']);
    $this->assertArrayHasKey('group_id_mod', $view_field_groups);
    $this->assertSame('group_clinical_trials_gov', $view_field_groups['group_id_mod']['parent_name']);
    $this->assertSame('fieldset', $view_field_groups['group_id_mod']['format_type']);

    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial_hidden')
      ->set('field_prefix', 'trial_hidden')
      ->set('form_display_root', 'details_opened')
      ->set('form_display_component', 'hidden')
      ->set('form_display_field_group', 'none')
      ->set('view_display_root', 'container')
      ->set('view_display_component', 'hidden')
      ->set('view_display_field_group', 'none')
      ->save();

    $hidden_fields = [
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.organization',
    ];
    $hidden_field_definitions = $this->createBundleAndFieldDefinitions('trial_hidden', 'Hidden Trial', $hidden_fields);
    $this->resetDisplays('trial_hidden');

    $this->entityDisplayManager->createFieldDisplayComponents('trial_hidden', $hidden_field_definitions);
    $this->entityDisplayManager->createFieldGroups('trial_hidden', $hidden_fields, $hidden_field_definitions);

    $hidden_form_display = EntityFormDisplay::load('node.trial_hidden.default');
    $hidden_view_display = EntityViewDisplay::load('node.trial_hidden.default');

    // Check that hidden display settings skip component creation but keep the root field groups.
    $this->assertNotNull($hidden_form_display);
    $this->assertNotNull($hidden_view_display);
    $this->assertNull($hidden_form_display->getComponent('trial_hidden_brief_title'));
    $this->assertNull($hidden_view_display->getComponent('trial_hidden_brief_title'));
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

    $nested_fields = [
      'protocolSection.identificationModule',
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.organization',
    ];
    $nested_field_definitions = $this->createBundleAndFieldDefinitions('trial_nested', 'Nested Trial', $nested_fields);
    $this->resetDisplays('trial_nested');

    $this->entityDisplayManager->createFieldDisplayComponents('trial_nested', $nested_field_definitions);
    $this->entityDisplayManager->createFieldGroups('trial_nested', $nested_fields, $nested_field_definitions);

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

    // Check that display generation does not create or modify the teaser display.
    $teaser_display = EntityViewDisplay::load('node.trial.teaser');
    $this->assertNull($teaser_display);
  }

  /**
   * Creates a bundle and returns field definitions for display generation.
   */
  protected function createBundleAndFieldDefinitions(string $type, string $label, array $selected_fields): array {
    if (!NodeType::load($type)) {
      $this->entityManager->createContentType($type, $label, 'Clinical trial content type');
    }
    $this->entityManager->createFields($type, $selected_fields);

    return $this->buildFieldDefinitions($selected_fields);
  }

  /**
   * Builds display field definitions including generated system link fields.
   */
  protected function buildFieldDefinitions(array $selected_fields): array {
    $field_definitions = [];
    foreach ($selected_fields as $path) {
      $field_definitions[$path] = $this->entityManager->resolveFieldDefinition($path);
    }

    return $field_definitions;
  }

  /**
   * Deletes default and teaser displays for a bundle.
   */
  protected function resetDisplays(string $type): void {
    if ($form_display = EntityFormDisplay::load('node.' . $type . '.default')) {
      $form_display->delete();
    }
    if ($default_view_display = EntityViewDisplay::load('node.' . $type . '.default')) {
      $default_view_display->delete();
    }
    if ($teaser_view_display = EntityViewDisplay::load('node.' . $type . '.teaser')) {
      $teaser_view_display->delete();
    }
  }

}
