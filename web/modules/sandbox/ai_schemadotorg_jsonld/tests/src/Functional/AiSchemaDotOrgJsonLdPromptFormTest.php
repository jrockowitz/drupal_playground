<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;

/**
 * Tests the AI Schema.org JSON-LD prompt form.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdPromptFormTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add the node page AI schema.org JSON-LD automator field.
    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToEntity('node', 'page');

    // Clear cached field definitions to ensure that the new schema.org JSON-LD
    // automator is used.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests prompt form access, values, and save behavior.
   */
  public function testPromptFormUpdatesAutomatorPrompt(): void {
    $prompt_form_path = '/admin/config/ai/schemadotorg-jsonld/prompt/node/page';

    // Check that only users with site configuration access can open the form.
    $editor = $this->drupalCreateUser([
      'access content',
      'edit any page content',
    ]);
    $this->drupalLogin($editor);
    $this->drupalGet($prompt_form_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();


    // Check that the prompt form loads with the expected values.
    $this->drupalLogin($this->adminUser);

    $automator_storage = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator');
    $automator_id = 'node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default';
    $automator = $automator_storage->load($automator_id);
    $this->assertInstanceOf(AiAutomator::class, $automator);
    $expected_prompt = (string) $automator->get('token');

    $this->drupalGet($prompt_form_path);
    $this->assertSession()->fieldExists('prompt');
    $this->assertSession()->fieldValueEquals('prompt', $expected_prompt);
    $this->assertSession()->fieldNotExists('label');

    // Check that submitting the prompt form updates the automator values.
    $updated_prompt = 'Updated prompt token';
    $this->submitForm([
      'prompt' => $updated_prompt,
    ], 'Save');

    $automator = $automator_storage->load($automator_id);
    $this->assertInstanceOf(AiAutomator::class, $automator);
    $this->assertSame($updated_prompt, $automator->get('token'));
    $plugin_config = $automator->get('plugin_config');
    $this->assertSame($updated_prompt, $plugin_config['automator_token']);

    // Check that the edit prompt setting shows and hides the node edit form link.
    // Create a saved page so the edit prompt link can be shown on the edit form.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Prompt test page',
      'status' => 1,
      AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME => '{"@type":"WebPage"}',
    ]);
    $node->save();

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkExists('Edit prompt');

    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('development.edit_prompt', FALSE)
      ->save();
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkNotExists('Edit prompt');

    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('development.edit_prompt', TRUE)
      ->save();
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkExists('Edit prompt');
  }

}
