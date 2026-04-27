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
    $available_field_keys = $this->fieldManager->getAvailableFieldKeysFromQuery('query.cond=cancer');
    $available_definitions = $this->fieldManager->getAvailableFieldDefinitionsFromQuery('query.cond=cancer');
    $responsible_party_definition = $this->fieldManager->getFieldDefinition('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $protocol_section_definition = $this->fieldManager->getFieldDefinition('protocolSection');
    $alias_definition = $this->fieldManager->getFieldDefinition('protocolSection.identificationModule.nctIdAliases');

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
    $this->assertSame([
      'Type',
      'Investigator_full_name',
      'Investigator_title',
      'Investigator_affiliation',
      'Old_name_title',
      'Old_organization',
    ], $responsible_party_definition['details']);

    // Check that structural parents remain field groups.
    $this->assertTrue($protocol_section_definition['available']);
    $this->assertSame('field_group', $protocol_section_definition['field_type']);
    $this->assertTrue($protocol_section_definition['group_only']);
  }

}
