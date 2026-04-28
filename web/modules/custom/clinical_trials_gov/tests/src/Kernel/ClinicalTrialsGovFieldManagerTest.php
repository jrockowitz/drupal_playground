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
   * Modules required for these kernel tests.
   *
   * @var array
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

    // Check that the curated key list keeps required and structural parents.
    $this->assertContains('protocolSection', $available_field_keys);
    $this->assertContains('protocolSection.sponsorCollaboratorsModule.responsibleParty', $available_field_keys);
    $this->assertContains('protocolSection.identificationModule.nctId', $available_field_keys);

    // Check that metadata-only rows outside the curated list are excluded.
    $this->assertNotContains('protocolSection.identificationModule.nctIdAliases', $available_field_keys);
    $this->assertArrayNotHasKey('protocolSection.identificationModule.nctIdAliases', $available_definitions);
    $this->assertFalse($alias_definition['available']);
    $this->assertFalse($alias_definition['selectable']);

    // Check that simple structs remain custom fields with detail rows.
    $this->assertTrue($responsible_party_definition['available']);
    $this->assertSame('custom', $responsible_party_definition['field_type']);
    $this->assertArrayNotHasKey('available', $resolved_responsible_party_definition);
    $this->assertSame('custom', $resolved_responsible_party_definition['field_type']);
    $this->assertSame([
      'type',
      'investigator_full_name',
      'investigator_title',
      'investigator_affiliation',
      'old_name_title',
      'old_organization',
    ], $responsible_party_definition['details']);

    // Check that structural parents remain field groups.
    $this->assertTrue($protocol_section_definition['available']);
    $this->assertSame('field_group', $protocol_section_definition['field_type']);
    $this->assertTrue($protocol_section_definition['group_only']);

    // Check that brief title still maps to node title and keeps a generated field.
    $this->assertSame('title', $title_definition['destination_property']);
    $this->assertSame('field_brief_title', $title_definition['field_name']);
    $this->assertSame('string', $title_definition['field_type']);
    $this->assertSame(300, $title_definition['storage_settings']['max_length']);

    // Check that MARKUP custom-field columns use formatted long text with plain text format.
    $this->assertSame('custom', $eligibility_definition['field_type']);
    $this->assertSame('string_long', $eligibility_definition['storage_settings']['columns']['eligibilityCriteria']['type']);
    $this->assertTrue($eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['formatted']);
    $this->assertSame('plain_text', $eligibility_definition['instance_settings']['field_settings']['eligibilityCriteria']['default_format']);
    $this->assertSame('map_string', $eligibility_definition['storage_settings']['columns']['stdAges']['type']);
    $this->assertSame('', $eligibility_definition['instance_settings']['field_settings']['stdAges']['table_empty']);
  }

}
