<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests AiSchemaDotOrgJsonLdSettingsForm.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdSettingsFormTest extends AiSchemaDotOrgJsonLdTestBase {
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the media type used by the settings form assertions.
    $this->createMediaType('file', [
      'id' => 'document',
      'label' => 'Document',
    ]);
  }

  /**
   * Tests the settings form behavior.
   */
  public function testSettingsForm(): void {
    // Log in the administrator user to configure Schema.org JSON-LD settings.
    $this->drupalLogin($this->adminUser);

    // Check that enabling entity types works as expected.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->fieldExists('entity_types[node][bundles][page]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_prompt]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_jsonld]');

    // Enable node and media entity types for Schema.org JSON-LD configuration.
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
      'enabled_entity_types[entity_types][media]' => 'media',
    ], 'Save configuration');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->checkboxChecked('enabled_entity_types[entity_types][media]');
    $this->assertSession()->fieldExists('entity_types[media][default_prompt]');
    $this->assertSession()->fieldExists('entity_types[media][default_jsonld]');

    // Enable the page and document bundles and save their default settings.
    $this->submitForm([
      'entity_types[node][bundles][page]' => 'page',
      'entity_types[node][default_prompt]' => 'Node prompt override',
      'entity_types[node][default_jsonld]' => '{"@type":"WebPage"}',
      'entity_types[media][bundles][document]' => 'document',
      'entity_types[media][default_prompt]' => 'Media prompt override',
      'entity_types[media][default_jsonld]' => '{"@type":"MediaObject"}',
    ], 'Save configuration');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->fieldValueEquals('entity_types[node][default_prompt]', 'Node prompt override');
    $this->assertSession()->fieldValueEquals('entity_types[node][default_jsonld]', '{"@type":"WebPage"}');
    $this->assertSession()->fieldValueEquals('entity_types[media][default_prompt]', 'Media prompt override');
    $this->assertSession()->fieldValueEquals('entity_types[media][default_jsonld]', '{"@type":"MediaObject"}');

    // Check that entity types with field_ai_schemadotorg_jsonld field are disabled via 'Enabled entity types'.
    $this->assertSession()->checkboxChecked('enabled_entity_types[entity_types][node]');
    $this->assertSession()->fieldDisabled('enabled_entity_types[entity_types][node]');
    $this->assertSession()->checkboxChecked('enabled_entity_types[entity_types][media]');
    $this->assertSession()->fieldDisabled('enabled_entity_types[entity_types][media]');

    // Check that checking off node bundles works as expected.
    $this->assertSession()->checkboxChecked('entity_types[node][bundles][page]');
    $this->assertSession()->fieldDisabled('entity_types[node][bundles][page]');
    $this->assertSession()->checkboxChecked('entity_types[media][bundles][document]');
    $this->assertSession()->fieldDisabled('entity_types[media][bundles][document]');

    $field_config_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_config');

    $field_config = $field_config_storage
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config);

    $field_config = $field_config_storage
      ->load('media.document.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config);
  }

}
