<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests AiSchemaDotOrgJsonLdSettingsForm.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'node',
    'field',
    'field_ui',
    'file',
    'options',
    'field_widget_actions',
    'json_field',
    'taxonomy',
    'block_content',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    if (!BlockContentType::load('basic')) {
      BlockContentType::create([
        'id' => 'basic',
        'label' => 'Basic block',
      ])->save();
    }
    $this->container->get('entity_type.manager')
      ->getStorage('media_type')
      ->create([
        'id' => 'document',
        'label' => 'Document',
        'source' => 'file',
      ])
      ->save();
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
  }

  /**
   * Tests the settings form.
   */
  public function testSettingsForm(): void {
    // Check that the settings form loads.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->statusCodeEquals(200);

    // Check that node renders nested settings fields by default.
    $this->assertSession()->elementExists('css', 'input[name="entity_types[node][bundles][page]"]');
    $this->assertSession()->fieldExists('entity_types[node][prompt]');
    $this->assertSession()->fieldExists('entity_types[node][default_jsonld]');
    $this->assertSession()->fieldNotExists('entity_types[media][prompt]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_jsonld]');
    $this->assertSession()->fieldExists('breadcrumb_jsonld');
    $this->assertSession()->elementExists('css', 'details#edit-enabled-entity-types');
    $this->assertSession()->elementNotExists('css', 'details#edit-enabled-entity-types[open]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][media]"]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][taxonomy_term]"]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][block_content]"]');
    $this->assertSession()->elementNotExists('css', 'input[name="enabled_entity_types[entity_types][shortcut]"]');
    $this->assertSession()->linkByHrefExists('/admin/structure/types/manage/page');
    $this->assertSession()->elementNotExists('css', 'a.use-ajax[href="/admin/structure/types/manage/page"]');

    // Enable media and taxonomy terms and check the new sections appear.
    $this->submitForm([
      'enabled_entity_types[entity_types][media]' => 'media',
      'enabled_entity_types[entity_types][taxonomy_term]' => 'taxonomy_term',
    ], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('entity_types[media][prompt]');
    $this->assertSession()->fieldExists('entity_types[media][default_jsonld]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[media][bundles][document]"]');
    $this->assertSession()->linkByHrefExists('/admin/structure/media/manage/document');
    $this->assertSession()->elementNotExists('css', 'a.use-ajax[href="/admin/structure/media/manage/document"]');

    // Check that the taxonomy term section appears after enabling it.
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][prompt]');
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][default_jsonld]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[taxonomy_term][bundles][tags]"]');

    // Select page bundle and save.
    $this->submitForm(['entity_types[node][bundles][page]' => 'page'], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Check that field was created.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'Field was created after saving form.');

    // Reload and check page is pre-checked and disabled.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->checkboxChecked('entity_types[node][bundles][page]');
    $this->assertSession()->elementAttributeContains(
      'css',
      'input[name="entity_types[node][bundles][page]"]',
      'disabled',
      'disabled'
    );

    // Check that Operations column shows Edit field and Delete field links.
    $this->assertSession()->linkExists('Edit field');
    $this->assertSession()->linkExists('Delete field');
    $this->assertSession()->elementAttributeContains(
      'css',
      'a.use-ajax[href*="node.page.field_schemadotorg_jsonld?destination=/admin/config/ai/schemadotorg-jsonld"]',
      'data-dialog-type',
      'modal'
    );
    $this->assertSession()->elementAttributeContains(
      'css',
      'a.use-ajax[href*="node.page.field_schemadotorg_jsonld?destination=/admin/config/ai/schemadotorg-jsonld"]',
      'href',
      'destination=/admin/config/ai/schemadotorg-jsonld'
    );

    // Check that default_jsonld uses textarea widget when json_field_widget
    // is absent from $modules (the base case in this test class).
    $this->assertSession()->elementExists('css', 'textarea[name="entity_types[node][default_jsonld]"]');
  }

}
