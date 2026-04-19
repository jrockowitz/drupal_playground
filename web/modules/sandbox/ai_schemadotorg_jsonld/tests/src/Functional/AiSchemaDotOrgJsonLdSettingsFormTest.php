<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\node\Entity\NodeType;
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
    'json_field_widget',
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
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $page_node_type = NodeType::load('page');
    $this->assertNotNull($page_node_type, 'The page node type exists.');
    $page_node_type->set('description', 'Basic page description');
    $page_node_type->save();
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
      'description' => 'Tags description',
    ])->save();
    if (!BlockContentType::load('basic')) {
      BlockContentType::create([
        'id' => 'basic',
        'label' => 'Basic block',
        'description' => 'Basic block description',
      ])->save();
    }
    $this->container->get('entity_type.manager')
      ->getStorage('media_type')
      ->create([
        'id' => 'document',
        'label' => 'Document',
        'description' => 'Document description',
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
    $this->assertSession()->pageTextContains('Select the bundles that should get the Schema.org JSON-LD field. Then review the default prompt and optional default JSON-LD used for this entity type.');
    $this->assertSession()->pageTextContains('Note: you can customize the prompt for an individual bundle by clicking Edit field, going to AI Automator Settings, and editing the automator prompt.');
    $this->assertSession()->pageTextContains('Enable the supported entity types you want to manage with this module. After saving, each enabled entity type will appear above with bundle, prompt, and default JSON-LD settings.');
    $this->assertSession()->elementExists('xpath', '//th[@width="20%" and normalize-space()="Name"]');
    $this->assertSession()->elementExists('xpath', '//th[@width="60%" and contains(@class, "priority-medium") and normalize-space()="Description"]');
    $this->assertSession()->elementExists('xpath', '//th[@width="20%" and normalize-space()="Operations"]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[node][bundles][page]"]');
    $this->assertSession()->elementExists('css', 'details#edit-entity-types-node-default-settings');
    $this->assertSession()->elementNotExists('css', 'details#edit-entity-types-node-default-settings[open]');
    $this->assertSession()->fieldExists('entity_types[node][default_prompt]');
    $this->assertSession()->fieldExists('entity_types[node][default_jsonld]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_prompt]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_jsonld]');
    $this->assertSession()->fieldNotExists('breadcrumb_jsonld');
    $this->assertSession()->elementExists('css', 'details#edit-enabled-entity-types');
    $this->assertSession()->elementNotExists('css', 'details#edit-enabled-entity-types[open]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][media]"]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][taxonomy_term]"]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][block_content]"]');
    $this->assertSession()->elementExists('css', 'input[name="enabled_entity_types[entity_types][user]"]');
    $this->assertSession()->elementNotExists('css', 'input[name="enabled_entity_types[entity_types][shortcut]"]');
    $this->assertSession()->linkByHrefExists('/admin/structure/types/manage/page');
    $this->assertSession()->elementNotExists('css', 'a.use-ajax[href="/admin/structure/types/manage/page"]');

    // Enable media, taxonomy terms, and users and check the new sections appear.
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
      'enabled_entity_types[entity_types][media]' => 'media',
      'enabled_entity_types[entity_types][taxonomy_term]' => 'taxonomy_term',
      'enabled_entity_types[entity_types][user]' => 'user',
    ], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->checkboxChecked('enabled_entity_types[entity_types][media]');
    $this->assertSession()->elementNotExists('css', 'input[name="enabled_entity_types[entity_types][media]"][disabled]');
    $this->assertSession()->elementExists('css', 'details#edit-entity-types-media-default-settings');
    $this->assertSession()->elementNotExists('css', 'details#edit-entity-types-media-default-settings[open]');
    $this->assertSession()->fieldExists('entity_types[media][default_prompt]');
    $this->assertSession()->fieldExists('entity_types[media][default_jsonld]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[media][bundles][document]"]');
    $this->assertSession()->pageTextContains('Document description');
    $this->assertSession()->linkByHrefExists('/admin/structure/media/manage/document');
    $this->assertSession()->elementNotExists('css', 'a.use-ajax[href="/admin/structure/media/manage/document"]');

    // Check that the taxonomy term section appears after enabling it.
    $this->assertSession()->elementExists('css', 'details#edit-entity-types-taxonomy-term-default-settings');
    $this->assertSession()->elementNotExists('css', 'details#edit-entity-types-taxonomy-term-default-settings[open]');
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][default_prompt]');
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][default_jsonld]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[taxonomy_term][bundles][tags]"]');
    $this->assertSession()->pageTextContains('Tags description');

    // Check that the user section appears with a synthetic bundle row.
    $this->assertSession()->elementExists('css', 'details#edit-entity-types-user-default-settings');
    $this->assertSession()->elementNotExists('css', 'details#edit-entity-types-user-default-settings[open]');
    $this->assertSession()->fieldExists('entity_types[user][default_prompt]');
    $this->assertSession()->fieldExists('entity_types[user][default_jsonld]');
    $this->assertSession()->elementExists('css', 'input[name="entity_types[user][bundles][user]"]');
    $this->assertSession()->pageTextContains('Individual registered user accounts on a website.');
    $this->assertSession()->linkByHrefExists('/admin/config/people/accounts');

    // Select page and user bundles, save nested defaults, and confirm they persist.
    $this->submitForm([
      'entity_types[node][bundles][page]' => 'page',
      'entity_types[node][default_prompt]' => 'Node prompt override',
      'entity_types[node][default_jsonld]' => '{"@type":"WebPage"}',
      'entity_types[user][bundles][user]' => 'user',
      'entity_types[user][default_prompt]' => 'User prompt override',
      'entity_types[user][default_jsonld]' => '{"@type":"Person"}',
    ], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the node field was created.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'Field was created after saving form.');

    // Check that the user field was created.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('user.user.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'User field was created after saving form.');

    $config = $this->container->get('config.factory')->get('ai_schemadotorg_jsonld.settings');
    $this->assertSame('Node prompt override', $config->get('entity_types.node.default_prompt'));
    $this->assertSame('{"@type":"WebPage"}', $config->get('entity_types.node.default_jsonld'));
    $this->assertSame('User prompt override', $config->get('entity_types.user.default_prompt'));
    $this->assertSame('{"@type":"Person"}', $config->get('entity_types.user.default_jsonld'));
    // Reload and check page and user are pre-checked and disabled.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->checkboxChecked('entity_types[node][bundles][page]');
    $this->assertSession()->elementAttributeContains(
      'css',
      'input[name="entity_types[node][bundles][page]"]',
      'disabled',
      'disabled'
    );
    $this->assertSession()->checkboxChecked('entity_types[user][bundles][user]');
    $this->assertSession()->elementAttributeContains(
      'css',
      'input[name="entity_types[user][bundles][user]"]',
      'disabled',
      'disabled'
    );

    // Check that Operations column shows Edit field, Edit prompt, and Delete field links.
    $this->assertSession()->linkExists('Edit field');
    $this->assertSession()->linkExists('Edit prompt');
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
    $this->assertSession()->elementAttributeContains(
      'css',
      'a.use-ajax[href*="/admin/config/ai/schemadotorg-jsonld/prompt/node/page?destination=/admin/config/ai/schemadotorg-jsonld"]',
      'data-dialog-type',
      'modal'
    );
    $this->assertSession()->elementAttributeContains(
      'css',
      'a.use-ajax[href*="/admin/config/ai/schemadotorg-jsonld/prompt/node/page?destination=/admin/config/ai/schemadotorg-jsonld"]',
      'href',
      'destination=/admin/config/ai/schemadotorg-jsonld'
    );

    // Check that default_jsonld uses a textarea enhanced by json_field_widget.
    $this->assertSession()->elementExists('css', 'textarea[name="entity_types[node][default_jsonld]"]');
    $this->assertSession()->elementExists('css', 'textarea[name="entity_types[node][default_jsonld]"][data-json-editor="default_jsonld_node"]');
    $this->assertSession()->responseContains('default_jsonld_node');
    $this->assertSession()->responseContains('ai_schemadotorg_jsonld.json_widget.js');
    $this->assertSession()->responseContains('260px');
    $this->assertSession()->responseContains('60vh');

    // Uncheck a removable entity type and confirm its config section is removed.
    $this->submitForm([
      'enabled_entity_types[entity_types][media]' => FALSE,
      'enabled_entity_types[entity_types][taxonomy_term]' => 'taxonomy_term',
    ], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('entity_types[media][default_prompt]');
    $this->assertSession()->fieldNotExists('entity_types[media][default_jsonld]');
    $this->assertSession()->checkboxNotChecked('enabled_entity_types[entity_types][media]');

    // Check that field-backed entity types remain checked and disabled.
    $this->assertSession()->checkboxChecked('enabled_entity_types[entity_types][node]');
    $this->assertSession()->elementAttributeContains(
      'css',
      'input[name="enabled_entity_types[entity_types][node]"]',
      'disabled',
      'disabled'
    );

  }

}
