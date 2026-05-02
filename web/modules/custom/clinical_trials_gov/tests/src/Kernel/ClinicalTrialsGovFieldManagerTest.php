<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovFieldManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFieldManagerTest extends KernelTestBase {

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
   * The field manager under test.
   */
  protected ClinicalTrialsGovFieldManagerInterface $fieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('field_prefix', 'trial')
      ->set('title_path', 'protocolSection.identificationModule.briefTitle')
      ->set('required_paths', [
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.descriptionModule.briefSummary',
      ])
      ->save();
    $this->fieldManager = $this->container->get('clinical_trials_gov.field_manager');
  }

  /**
   * Tests curated field selection and definition decoration.
   */
  public function testAvailableFieldDefinitions(): void {
    $available_field_keys = $this->fieldManager->getAvailableFieldKeys();
    $available_definitions = $this->fieldManager->getAvailableFieldDefinitions();
    $resolved_responsible_party_definition = $this->fieldManager->resolveFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $responsible_party_definition = $this->fieldManager->getFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $protocol_section_definition = $this->fieldManager->getFieldDefinition('protocolSection');
    $alias_definition = $this->fieldManager->getFieldDefinition('protocolSection.identificationModule.nctIdAliases');
    $title_definition = $this->fieldManager->resolveFieldDefinition('protocolSection.identificationModule.briefTitle');
    $eligibility_definition = $this->fieldManager->resolveFieldDefinition('protocolSection.eligibilityModule');
    $references_definition = $this->fieldManager->resolveFieldDefinition('protocolSection.referencesModule.references');

    // Check that no paths are available until discovery has populated the allow-list.
    $this->assertSame([], $available_field_keys);
    $this->assertSame([], $available_definitions);
    $this->assertFalse($alias_definition['available']);
    $this->assertFalse($alias_definition['selectable']);

    // Check that simple structs still resolve as custom fields even before paths are discovered.
    $this->assertFalse($responsible_party_definition['available']);
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertArrayNotHasKey('available', $resolved_responsible_party_definition);
    $this->assertSame('custom', $resolved_responsible_party_definition['field_type']);

    // Check that structural parents still resolve as field groups even before paths are discovered.
    $this->assertFalse($protocol_section_definition['available']);
    $this->assertSame('field_group', $protocol_section_definition['field_type']);
    $this->assertTrue($protocol_section_definition['group_only']);

    // Check that brief title still maps to node title and keeps a generated field.
    $this->assertSame('title', $title_definition['destination_property']);
    $this->assertSame('trial_brief_title', $title_definition['field_name']);
    $this->assertSame('string', $title_definition['field_type']);
    $this->assertSame(300, $title_definition['storage_settings']['max_length']);

    // Check that supported structs still integrate as custom fields.
    $this->assertSame('custom', $eligibility_definition['field_type']);
    $this->assertSame('custom', $references_definition['field_type']);

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('title_path', 'protocolSection.identificationModule.nctId')
      ->set('required_paths', [
        'protocolSection.identificationModule.nctId',
        'protocolSection.descriptionModule.briefSummary',
      ])
      ->set('query_paths', [
        'protocolSection.statusModule.overallStatus',
      ])
      ->save();
    $this->container->get('kernel')->rebuildContainer();
    $field_manager = $this->container->get('clinical_trials_gov.field_manager');
    $configured_available_field_keys = $field_manager->getAvailableFieldKeys();
    $configured_available_definitions = $field_manager->getAvailableFieldDefinitions();
    $configured_title_definition = $field_manager->resolveFieldDefinition('protocolSection.identificationModule.nctId');
    $configured_brief_title_definition = $field_manager->resolveFieldDefinition('protocolSection.identificationModule.briefTitle');

    // Check that saved paths become the authoritative allow-list with required, title, and ancestors added.
    $this->assertContains('protocolSection', $configured_available_field_keys);
    $this->assertContains('protocolSection.statusModule', $configured_available_field_keys);
    $this->assertContains('protocolSection.statusModule.overallStatus', $configured_available_field_keys);
    $this->assertContains('protocolSection.identificationModule.nctId', $configured_available_field_keys);
    $this->assertContains('protocolSection.descriptionModule.briefSummary', $configured_available_field_keys);
    $this->assertNotContains('protocolSection.sponsorCollaboratorsModule.responsibleParty', $configured_available_field_keys);
    $this->assertArrayHasKey('protocolSection.statusModule.overallStatus', $configured_available_definitions);
    $this->assertArrayNotHasKey('protocolSection.sponsorCollaboratorsModule.responsibleParty', $configured_available_definitions);

    // Check that the configured title path gets the title mapping instead of the hard-coded brief title path.
    $this->assertSame('title', $configured_title_definition['destination_property']);
    $this->assertNull($configured_brief_title_definition['destination_property']);
  }

}
