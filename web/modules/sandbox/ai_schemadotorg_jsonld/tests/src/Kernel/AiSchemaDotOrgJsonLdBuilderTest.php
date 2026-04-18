<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests AiSchemaDotOrgJsonLdBuilder.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'options',
    'token',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('entity_form_display');
    $this->installEntitySchema('entity_view_display');
    $this->installEntitySchema('ai_automator');
    $this->installConfig(['system', 'field', 'node', 'ai_schemadotorg_jsonld']);

    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
  }

  /**
   * Tests addFieldToEntity creates all required config.
   */
  public function testAddField(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
    $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);

    $builder->addFieldToEntity('node', 'page');

    // Check that field storage exists.
    $field_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->load('node.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_storage, 'FieldStorageConfig exists.');

    // Check that field instance exists and is translatable.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'FieldConfig exists.');
    $this->assertTrue($field_config->isTranslatable(), 'FieldConfig is translatable.');

    // Check that AI automator config exists.
    $automator = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNotNull($automator, 'AiAutomator config entity exists.');

    // Check that AI automator status field storage exists.
    $status_field_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->load('node.' . AiAutomatorStatusField::FIELD_NAME);
    $this->assertNotNull($status_field_storage, 'AI automator status FieldStorageConfig exists.');

    // Check that AI automator status field config exists.
    $status_field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiAutomatorStatusField::FIELD_NAME);
    $this->assertNotNull($status_field_config, 'AI automator status FieldConfig exists.');

    // Check that form display includes the field at weight 99.
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.page.default');
    $component = $form_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'Form display component exists.');
    $this->assertSame(99, $component['weight'], 'Form display weight is 99.');

    // Check that view display includes the field at weight 99.
    $view_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.page.default');
    $component = $view_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'View display component exists.');
    $this->assertSame(99, $component['weight'], 'View display weight is 99.');

    // Check idempotency — calling again must not throw.
    $builder->addFieldToEntity('node', 'page');
    $this->addToAssertionCount(1);

    // Check cascade delete — deleting FieldConfig should remove the automator.
    FieldConfig::load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->delete();
    $this->container->get('entity_type.manager')->getStorage('ai_automator')->resetCache();
    $automator_after_delete = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNull($automator_after_delete, 'AiAutomator is deleted when FieldConfig is deleted.');
  }

}
