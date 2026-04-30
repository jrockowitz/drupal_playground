<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for readonly field hook behavior.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovReadonlyHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'readonly_field_widget',
    'node',
    'field',
    'text',
    'link',
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
      ->save();
  }

  /**
   * Tests that mapped fields and system links become readonly.
   *
   * Unrelated fields should stay editable.
   */
  public function testReadonlyMappedFieldsOnly(): void {
    $entity_manager = $this->container->get('clinical_trials_gov.entity_manager');
    $entity_manager->createContentType('trial', 'Trial', 'Clinical trial content type');
    $entity_manager->createFields('trial', [
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_manual_notes',
      'entity_type' => 'node',
      'type' => 'string',
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_manual_notes',
      'entity_type' => 'node',
      'bundle' => 'trial',
      'label' => 'Manual notes',
    ])->save();

    $form_display = EntityFormDisplay::load('node.trial.default');
    $form_display?->setComponent('field_manual_notes', [
      'type' => 'string_textfield',
      'weight' => 99,
      'region' => 'content',
    ])->save();

    $view_display = EntityViewDisplay::load('node.trial.default');
    $view_display?->setComponent('field_manual_notes', [
      'type' => 'string',
      'label' => 'above',
      'weight' => 99,
      'region' => 'content',
    ])->save();

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('readonly', FALSE)
      ->set('fields', [
        'field_trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
        'field_trial_nct_id' => 'protocolSection.identificationModule.nctId',
      ])
      ->save();

    $node = Node::create(['type' => 'trial']);
    $editable_display = EntityFormDisplay::collectRenderDisplay($node, 'default');

    // Check that readonly mode off preserves the editable widgets.
    $this->assertSame('string_textfield', $editable_display->getComponent('field_trial_brief_title')['type'] ?? NULL);
    $this->assertSame('string_textfield', $editable_display->getComponent('field_trial_nct_id')['type'] ?? NULL);
    $this->assertSame('link_default', $editable_display->getComponent('trial_nct_url')['type'] ?? NULL);
    $this->assertSame('link_default', $editable_display->getComponent('trial_nct_api')['type'] ?? NULL);
    $this->assertSame('string_textfield', $editable_display->getComponent('field_manual_notes')['type'] ?? NULL);

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('readonly', TRUE)
      ->save();

    $readonly_display = EntityFormDisplay::collectRenderDisplay($node, 'default');

    // Check that mapped ClinicalTrials.gov fields and system links switch to readonly.
    $this->assertSame('readonly_field_widget', $readonly_display->getComponent('field_trial_brief_title')['type'] ?? NULL);
    $this->assertSame('readonly_field_widget', $readonly_display->getComponent('field_trial_nct_id')['type'] ?? NULL);
    $this->assertSame('readonly_field_widget', $readonly_display->getComponent('trial_nct_url')['type'] ?? NULL);
    $this->assertSame('readonly_field_widget', $readonly_display->getComponent('trial_nct_api')['type'] ?? NULL);
    $this->assertSame('string_textfield', $readonly_display->getComponent('field_manual_notes')['type'] ?? NULL);
  }

}
